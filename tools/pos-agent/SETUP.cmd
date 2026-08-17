@echo off
setlocal
rem Servora POS Agent one-click setup: pair, then install the service.
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

if not exist "%~dp0servora-pos-agent.exe" (
    echo servora-pos-agent.exe is not next to SETUP.cmd.
    echo Extract the whole zip first, then run me from the extracted folder.
    pause
    exit /b 1
)

echo.
echo == Step 1 of 2: pair with your Servora server ====================
echo Have three things ready - the server address and pairing code are
echo shown when a manager adds this terminal under Sales ^> POS Sync,
echo and you need the folder the POS exports its reports into (run
echo "servora-pos-agent.exe discover" first if unsure).
echo.
"%~dp0servora-pos-agent.exe" pair
if %errorlevel% neq 0 (
    echo.
    echo Pairing failed - nothing was installed. Get a fresh code from
    echo Sales ^> POS Sync and run me again.
    pause
    exit /b 1
)

echo.
echo == Step 2 of 2: install the Windows service ======================
sc query ServoraPosAgent >nul 2>&1
if %errorlevel% equ 0 (
    echo A previous install exists - replacing it...
    "%~dp0servora-pos-agent.exe" uninstall
)
"%~dp0servora-pos-agent.exe" install
if %errorlevel% neq 0 (
    echo.
    echo The service install failed - see the message above.
    pause
    exit /b 1
)

echo.
echo Done. The agent is running now and starts itself after reboots -
echo nobody needs to stay signed in on this terminal.
echo.
echo It checks the export folder every hour. To test right away, export
echo a report from the POS and run: servora-pos-agent.exe sync
echo Then check Sales ^> POS Sync in Servora - the upload appears there.
pause
