@echo off
REM ================================================================
REM  Jenkins Cloudflare Tunnel
REM ================================================================
REM  Exposes your local Jenkins (http://localhost:8081) publicly
REM  via trycloudflare.com — no Cloudflare account needed.
REM
REM  Usage:
REM    Double-click this file  OR  run it from a terminal window.
REM    Press Ctrl+C to stop the tunnel.
REM
REM  The public URL will appear in the output below, e.g.:
REM    https://example-word-here.trycloudflare.com
REM ================================================================

setlocal enabledelayedexpansion

set "JENKINS_PORT=8081"

REM Resolve script directory (strip trailing backslash)
set "SCRIPT_DIR=%~dp0"
if "%SCRIPT_DIR:~-1%"=="\" set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

set "CLOUDFLARED_EXE=%SCRIPT_DIR%\cloudflared.exe"

echo.
echo ============================================================
echo   Jenkins Cloudflare Tunnel
echo   Target : http://localhost:%JENKINS_PORT%
echo   Storage: %SCRIPT_DIR%\cloudflared.exe
echo ============================================================
echo.

REM ----------------------------------------------------------------
REM  Download cloudflared if not already present
REM ----------------------------------------------------------------
if not exist "%CLOUDFLARED_EXE%" (
    echo [*] cloudflared not found - downloading portable binary...
    powershell -NoProfile -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile '%CLOUDFLARED_EXE%' -UseBasicParsing"
    if errorlevel 1 (
        echo.
        echo ERROR: Failed to download cloudflared. Check your internet connection.
        pause
        exit /b 1
    )
    echo [+] cloudflared downloaded successfully.
    echo.
)

echo [*] cloudflared version:
"%CLOUDFLARED_EXE%" --version
echo.

echo [*] Checking Jenkins is reachable on port %JENKINS_PORT%...
powershell -NoProfile -Command "try { $r = Invoke-WebRequest -Uri 'http://localhost:%JENKINS_PORT%/' -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop; Write-Host '[+] Jenkins responded HTTP' $r.StatusCode } catch { Write-Host '[!] WARNING: Jenkins did not respond - tunnel will still start but may not work' }"
echo.

echo [*] Starting Cloudflare quick tunnel...
echo     The public URL will appear below once connected.
echo     Share the https://xxxx.trycloudflare.com URL with your team.
echo     Press Ctrl+C to stop the tunnel.
echo ============================================================
echo.

"%CLOUDFLARED_EXE%" tunnel --url http://localhost:%JENKINS_PORT% --no-autoupdate --protocol http2

echo.
echo ============================================================
echo   Tunnel stopped.
echo ============================================================
pause
