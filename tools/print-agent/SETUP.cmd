@echo off
setlocal
rem Servora Print Agent one-click setup: pair, then install the service.
rem Double-click me from the unzipped folder. Details in README.md.

rem The service install needs administrator rights - relaunch elevated.
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Requesting administrator access - accept the prompt that appears...
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

rem An elevated shell starts in System32 - come back to this folder.
cd /d "%~dp0"

if not exist "%~dp0servora-print-agent.exe" (
    echo servora-print-agent.exe is not next to SETUP.cmd.
    echo Extract the whole zip first, then run me from the extracted folder.
    pause
    exit /b 1
)

if not exist "%~dp0SumatraPDF.exe" (
    echo SumatraPDF.exe is missing from this folder. The agent refuses to
    echo print without its own bundled copy - re-download the install zip.
    pause
    exit /b 1
)

echo.
echo == Step 1 of 2: pair with your Servora server ====================
echo Have the server address and pairing code ready - both are shown
echo when a manager adds this PC under Labels ^> Print Agents.
echo.
"%~dp0servora-print-agent.exe" pair
if %errorlevel% neq 0 (
    echo.
    echo Pairing failed - nothing was installed. Get a fresh code from
    echo Labels ^> Print Agents and run me again.
    pause
    exit /b 1
)

echo.
echo == Step 2 of 2: install the Windows service ======================
sc query ServoraPrintAgent >nul 2>&1
if %errorlevel% equ 0 (
    echo A previous install exists - replacing it...
    "%~dp0servora-print-agent.exe" uninstall
)
"%~dp0servora-print-agent.exe" install
if %errorlevel% neq 0 (
    echo.
    echo The service install failed - see the message above.
    pause
    exit /b 1
)

echo.
echo Done. The agent is running now and starts itself after reboots -
echo nobody needs to stay signed in on this PC.
echo.
echo Next, in Servora: Labels ^> Label Printers - set your printer's
echo driver to "Servora agent" and pick this PC.
pause
