//go:build windows

package main

import (
	"encoding/binary"
	"fmt"
	"strings"
	"unsafe"

	"golang.org/x/sys/windows"
)

// The VOZY lesson (Dotty's KLCC, 2026-08-17): naming a paper form is not
// enough. SumatraPDF selects the form by its DEVMODE paper-size id, but it
// inherits everything else — including orientation — from the printer's
// defaults, and some label drivers normalise a landscape-shaped form
// (70x40) to portrait (40x70) under the default orientation, which swaps
// the axes and prints every label sideways. Nor can the defaults simply be
// fixed on the PC: they are shared with whatever else prints to that queue
// (PrintNode, test pages), which may need the opposite orientation.
//
// So the agent stops trusting inheritance. Before each job that names a
// paper, it builds the DEVMODE itself: select the form, then MEASURE — via
// the driver's own CreateDC — what physical page each orientation actually
// produces, keep the one that matches the form's reported dimensions, and
// install it as the per-user default of the account the agent runs as
// (SYSTEM, level 9). Per-user defaults are invisible to other accounts, so
// the interactive user's PrintNode/browser printing keeps its own settings.

var (
	procOpenPrinter        = winspool.NewProc("OpenPrinterW")
	procClosePrinter       = winspool.NewProc("ClosePrinter")
	procDocumentProperties = winspool.NewProc("DocumentPropertiesW")
	procSetPrinter         = winspool.NewProc("SetPrinterW")

	gdi32             = windows.NewLazySystemDLL("gdi32.dll")
	procCreateDC      = gdi32.NewProc("CreateDCW")
	procDeleteDC      = gdi32.NewProc("DeleteDC")
	procGetDeviceCaps = gdi32.NewProc("GetDeviceCaps")
)

const (
	dcPapers = 2 // array of uint16 paper ids, parallel to DC_PAPERNAMES

	dmOutBuffer = 2
	dmInBuffer  = 8

	dmFieldOrientation = 0x00000001
	dmFieldPaperSize   = 0x00000002
	dmFieldPaperLength = 0x00000004
	dmFieldPaperWidth  = 0x00000008

	dmOrientPortrait  = 1
	dmOrientLandscape = 2

	// GetDeviceCaps indexes.
	capLogPixelsX     = 88
	capLogPixelsY     = 90
	capPhysicalWidth  = 110
	capPhysicalHeight = 111

	// DEVMODEW byte offsets (fixed by the Win32 ABI).
	offFields      = 72
	offOrientation = 76
	offPaperSize   = 78
	offPaperLength = 80
	offPaperWidth  = 82
)

// form is one driver paper form: its DEVMODE id and its size in tenths of
// a millimetre, exactly as DeviceCapabilities reports them.
type form struct {
	id     uint16
	wTenth int32
	hTenth int32
}

// candidate is one DEVMODE configuration worth trying, most-likely first.
type candidate struct {
	orientation int16
	explicitWL  bool // also set dmPaperWidth/Length, for drivers that ignore the id
}

func (c candidate) String() string {
	o := "portrait"
	if c.orientation == dmOrientLandscape {
		o = "landscape"
	}
	if c.explicitWL {
		return o + "+size"
	}
	return o
}

// applyPaperDevMode makes the named form actually take effect for the
// account the agent runs as, and returns a one-line description of what it
// measured and chose. A non-nil error means "nothing was changed" — the
// caller prints anyway, because the legacy inherit-the-defaults path is
// still correct on well-behaved drivers.
func applyPaperDevMode(printerName, paperName string) (string, error) {
	chosen, desc, err := chooseDevMode(printerName, paperName)
	if err != nil {
		return desc, err
	}

	h, err := openPrinter(printerName)
	if err != nil {
		return desc, err
	}
	defer procClosePrinter.Call(h)

	// PRINTER_INFO_9: the per-user default DEVMODE of the calling user.
	info := struct{ pDevMode *byte }{&chosen[0]}
	ret, _, callErr := procSetPrinter.Call(h, 9, uintptr(unsafe.Pointer(&info)), 0)
	if ret == 0 {
		return desc, fmt.Errorf("SetPrinter(level 9) failed for %q: %v", printerName, callErr)
	}

	return desc, nil
}

// chooseDevMode finds the form and measures each candidate configuration
// against the driver until one yields the form's own dimensions.
func chooseDevMode(printerName, paperName string) ([]byte, string, error) {
	f, err := findForm(printerName, paperName)
	if err != nil {
		return nil, "", err
	}

	base, err := defaultDevMode(printerName)
	if err != nil {
		return nil, "", err
	}

	wantW := float64(f.wTenth) / 10
	wantH := float64(f.hTenth) / 10

	candidates := []candidate{
		{dmOrientPortrait, false},
		{dmOrientLandscape, false},
		{dmOrientPortrait, true},
		{dmOrientLandscape, true},
	}

	var measured []string

	for _, c := range candidates {
		buf := make([]byte, len(base))
		copy(buf, base)

		fields := binary.LittleEndian.Uint32(buf[offFields:])
		fields |= dmFieldPaperSize | dmFieldOrientation
		binary.LittleEndian.PutUint16(buf[offPaperSize:], f.id)
		binary.LittleEndian.PutUint16(buf[offOrientation:], uint16(c.orientation))
		if c.explicitWL {
			fields |= dmFieldPaperWidth | dmFieldPaperLength
			binary.LittleEndian.PutUint16(buf[offPaperWidth:], uint16(f.wTenth))
			binary.LittleEndian.PutUint16(buf[offPaperLength:], uint16(f.hTenth))
		}
		binary.LittleEndian.PutUint32(buf[offFields:], fields)

		merged, err := mergeDevMode(printerName, buf)
		if err != nil {
			measured = append(measured, fmt.Sprintf("%s: %v", c, err))
			continue
		}

		gotW, gotH, err := measurePage(printerName, merged)
		if err != nil {
			measured = append(measured, fmt.Sprintf("%s: %v", c, err))
			continue
		}

		measured = append(measured, fmt.Sprintf("%s: %.0fx%.0fmm", c, gotW, gotH))

		if withinMM(gotW, wantW, 2) && withinMM(gotH, wantH, 2) {
			desc := fmt.Sprintf("paper %q (%.0fx%.0fmm) via %s [%s]",
				paperName, wantW, wantH, c, strings.Join(measured, ", "))
			return merged, desc, nil
		}
	}

	desc := fmt.Sprintf("paper %q (%.0fx%.0fmm): no orientation matched [%s]",
		paperName, wantW, wantH, strings.Join(measured, ", "))
	return nil, desc, fmt.Errorf("driver for %q never produced a %.0fx%.0fmm page", printerName, wantW, wantH)
}

// findForm resolves a form name the way SumatraPDF does — against
// DC_PAPERNAMES — but also keeps the id and dimensions.
func findForm(printerName, paperName string) (form, error) {
	device, err := windows.UTF16PtrFromString(printerName)
	if err != nil {
		return form{}, err
	}

	count, _, _ := procDeviceCapabilities.Call(
		uintptr(unsafe.Pointer(device)), 0, dcPapernames, 0, 0)
	if int32(count) <= 0 {
		return form{}, fmt.Errorf("the driver for %q reports no paper forms", printerName)
	}
	n := int(count)

	names := make([]uint16, n*64)
	if ret, _, _ := procDeviceCapabilities.Call(
		uintptr(unsafe.Pointer(device)), 0, dcPapernames,
		uintptr(unsafe.Pointer(&names[0])), 0); int32(ret) <= 0 {
		return form{}, fmt.Errorf("DC_PAPERNAMES failed for %q", printerName)
	}

	ids := make([]uint16, n)
	if ret, _, _ := procDeviceCapabilities.Call(
		uintptr(unsafe.Pointer(device)), 0, dcPapers,
		uintptr(unsafe.Pointer(&ids[0])), 0); int32(ret) <= 0 {
		return form{}, fmt.Errorf("DC_PAPERS failed for %q", printerName)
	}

	sizes := make([]point, n)
	if ret, _, _ := procDeviceCapabilities.Call(
		uintptr(unsafe.Pointer(device)), 0, dcPapersize,
		uintptr(unsafe.Pointer(&sizes[0])), 0); int32(ret) <= 0 {
		return form{}, fmt.Errorf("DC_PAPERSIZE failed for %q", printerName)
	}

	for i := 0; i < n; i++ {
		name := windows.UTF16ToString(names[i*64 : (i+1)*64])
		if strings.EqualFold(name, paperName) {
			return form{id: ids[i], wTenth: sizes[i].x, hTenth: sizes[i].y}, nil
		}
	}

	return form{}, fmt.Errorf("the driver for %q has no paper form named %q", printerName, paperName)
}

func openPrinter(printerName string) (uintptr, error) {
	name, err := windows.UTF16PtrFromString(printerName)
	if err != nil {
		return 0, err
	}

	var h uintptr
	ret, _, callErr := procOpenPrinter.Call(
		uintptr(unsafe.Pointer(name)), uintptr(unsafe.Pointer(&h)), 0)
	if ret == 0 {
		return 0, fmt.Errorf("OpenPrinter failed for %q: %v", printerName, callErr)
	}
	return h, nil
}

// defaultDevMode is what the driver hands the agent's account today —
// per-user default if one exists, global printing defaults otherwise.
func defaultDevMode(printerName string) ([]byte, error) {
	h, err := openPrinter(printerName)
	if err != nil {
		return nil, err
	}
	defer procClosePrinter.Call(h)

	name, _ := windows.UTF16PtrFromString(printerName)

	size, _, _ := procDocumentProperties.Call(0, h, uintptr(unsafe.Pointer(name)), 0, 0, 0)
	if int32(size) <= 0 {
		return nil, fmt.Errorf("DocumentProperties size query failed for %q", printerName)
	}

	buf := make([]byte, int(int32(size)))
	ret, _, _ := procDocumentProperties.Call(0, h, uintptr(unsafe.Pointer(name)),
		uintptr(unsafe.Pointer(&buf[0])), 0, dmOutBuffer)
	if int32(ret) < 0 {
		return nil, fmt.Errorf("DocumentProperties failed for %q", printerName)
	}

	return buf, nil
}

// mergeDevMode round-trips a candidate through the driver so it can
// sanitise the public fields and settle its private ones — the same
// validation its own preferences dialog performs on OK.
func mergeDevMode(printerName string, in []byte) ([]byte, error) {
	h, err := openPrinter(printerName)
	if err != nil {
		return nil, err
	}
	defer procClosePrinter.Call(h)

	name, _ := windows.UTF16PtrFromString(printerName)

	out := make([]byte, len(in))
	ret, _, _ := procDocumentProperties.Call(0, h, uintptr(unsafe.Pointer(name)),
		uintptr(unsafe.Pointer(&out[0])), uintptr(unsafe.Pointer(&in[0])),
		dmInBuffer|dmOutBuffer)
	if int32(ret) < 0 {
		return nil, fmt.Errorf("DocumentProperties merge failed for %q", printerName)
	}

	return out, nil
}

// measurePage asks the driver what physical page a DEVMODE produces, in
// millimetres. This is the ground truth every guess is checked against.
func measurePage(printerName string, devMode []byte) (w, h float64, err error) {
	driver, _ := windows.UTF16PtrFromString("WINSPOOL")
	device, _ := windows.UTF16PtrFromString(printerName)

	hdc, _, callErr := procCreateDC.Call(
		uintptr(unsafe.Pointer(driver)), uintptr(unsafe.Pointer(device)), 0,
		uintptr(unsafe.Pointer(&devMode[0])))
	if hdc == 0 {
		return 0, 0, fmt.Errorf("CreateDC failed for %q: %v", printerName, callErr)
	}
	defer procDeleteDC.Call(hdc)

	cap := func(index uintptr) float64 {
		v, _, _ := procGetDeviceCaps.Call(hdc, index)
		return float64(int32(v))
	}

	dpiX, dpiY := cap(capLogPixelsX), cap(capLogPixelsY)
	if dpiX <= 0 || dpiY <= 0 {
		return 0, 0, fmt.Errorf("GetDeviceCaps reported no resolution for %q", printerName)
	}

	w = cap(capPhysicalWidth) / dpiX * 25.4
	h = cap(capPhysicalHeight) / dpiY * 25.4
	return w, h, nil
}

func withinMM(got, want, tolerance float64) bool {
	diff := got - want
	return diff >= -tolerance && diff <= tolerance
}

// probeCommand is the support tool for the next VOZY: it prints what every
// candidate configuration measures on this PC's driver, changing nothing,
// so a driver's axis behaviour can be diagnosed over chat instead of by
// sacrificing labels.
func probeCommand(printerName, paperName string) error {
	_, desc, err := chooseDevMode(printerName, paperName)
	fmt.Println(desc)
	if err != nil {
		return err
	}
	fmt.Println("A job naming this paper will print with the configuration marked above.")
	return nil
}
