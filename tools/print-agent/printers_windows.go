//go:build windows

package main

import (
	"fmt"
	"unsafe"

	"github.com/alexbrainman/printer"
	"golang.org/x/sys/windows"
)

// WindowsEnumerator reports the queues this PC can print to, and — the part
// that matters — the PAPER FORM NAMES each printer's driver offers, because
// "the job names its paper" is the module's whole defence against drivers
// scaling labels onto the wrong stock.
type WindowsEnumerator struct{}

func (WindowsEnumerator) List() ([]PrinterInfo, error) {
	names, err := printer.ReadNames()
	if err != nil {
		return nil, fmt.Errorf("EnumPrinters failed: %w", err)
	}

	defaultName, _ := printer.Default()

	infos := make([]PrinterInfo, 0, len(names))

	for _, name := range names {
		papers, err := paperForms(name)
		if err != nil {
			// The plan's honest fallback: a printer whose driver won't
			// enumerate still gets reported by name — the picker's
			// free-text paper path covers it.
			papers = nil
		}

		infos = append(infos, PrinterInfo{
			Name:      name,
			IsDefault: name == defaultName,
			Papers:    papers,
		})
	}

	return infos, nil
}

var (
	winspool               = windows.NewLazySystemDLL("winspool.drv")
	procDeviceCapabilities = winspool.NewProc("DeviceCapabilitiesW")
)

const (
	dcPapernames = 16 // array of [64]uint16 form names
	dcPapersize  = 3  // array of POINT, tenths of a millimetre
)

type point struct{ x, y int32 }

// paperForms asks the printer's driver which forms it offers, via
// DeviceCapabilities(DC_PAPERNAMES) + DC_PAPERSIZE — the same list the
// Windows print dialog shows, which is exactly the list a saved
// `paper=<form>` has to come from.
func paperForms(printerName string) ([]Paper, error) {
	device, err := windows.UTF16PtrFromString(printerName)
	if err != nil {
		return nil, err
	}

	// First call with a nil buffer returns the count.
	count, _, callErr := procDeviceCapabilities.Call(
		uintptr(unsafe.Pointer(device)), 0, dcPapernames, 0, 0)
	if int32(count) <= 0 {
		return nil, fmt.Errorf("DC_PAPERNAMES count failed for %q: %v", printerName, callErr)
	}

	names := make([]uint16, int(count)*64)
	ret, _, callErr := procDeviceCapabilities.Call(
		uintptr(unsafe.Pointer(device)), 0, dcPapernames,
		uintptr(unsafe.Pointer(&names[0])), 0)
	if int32(ret) <= 0 {
		return nil, fmt.Errorf("DC_PAPERNAMES failed for %q: %v", printerName, callErr)
	}

	// Sizes are optional garnish on the picker; names alone are enough.
	var sizes []point
	sizeCount, _, _ := procDeviceCapabilities.Call(
		uintptr(unsafe.Pointer(device)), 0, dcPapersize, 0, 0)
	if int32(sizeCount) == int32(count) {
		sizes = make([]point, int(sizeCount))
		ret, _, _ := procDeviceCapabilities.Call(
			uintptr(unsafe.Pointer(device)), 0, dcPapersize,
			uintptr(unsafe.Pointer(&sizes[0])), 0)
		if int32(ret) <= 0 {
			sizes = nil
		}
	}

	papers := make([]Paper, 0, int(count))

	for i := 0; i < int(count); i++ {
		name := windows.UTF16ToString(names[i*64 : (i+1)*64])
		if name == "" {
			continue
		}

		p := Paper{Name: name}
		if sizes != nil {
			p.Size = fmt.Sprintf("%.0f x %.0f mm",
				float64(sizes[i].x)/10, float64(sizes[i].y)/10)
		}

		papers = append(papers, p)
	}

	return papers, nil
}
