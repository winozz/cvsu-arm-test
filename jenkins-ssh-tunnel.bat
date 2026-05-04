@echo off
REM ================================================================
REM  Jenkins SSH Tunnel (localhost.run)
REM ================================================================
REM  Exposes your local Jenkins (http://localhost:8081) publicly
REM  via localhost.run — no account, no install (uses built-in SSH).
REM
REM  Usage:
REM    Double-click this file  OR  run from a terminal.
REM    Press Ctrl+C to stop the tunnel.
REM
REM  The public URL will appear in the output, e.g.:
REM    https://abc123def.lhr.life
REM ================================================================

setlocal

set "JENKINS_PORT=8081"

echo.
echo ============================================================
echo   Jenkins SSH Tunnel  ^>  localhost.run
echo   Target : http://localhost:%JENKINS_PORT%
echo ============================================================
echo.

REM Check SSH is available
where ssh >nul 2>&1
if errorlevel 1 (
    echo ERROR: ssh.exe not found in PATH.
    echo OpenSSH is built into Windows 10/11. Check that the
    echo "OpenSSH Client" optional feature is enabled in:
    echo   Settings ^> Apps ^> Optional features ^> OpenSSH Client
    pause
    exit /b 1
)

for /f "tokens=2" %%v in ('ssh -V 2^>^&1') do echo [+] %%%v
echo.

echo [*] Checking Jenkins is reachable on localhost:%JENKINS_PORT%...
powershell -NoProfile -Command "try { $r = Invoke-WebRequest -Uri 'http://localhost:%JENKINS_PORT%/' -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop; Write-Host '[+] Jenkins responded HTTP' $r.StatusCode } catch { Write-Host '[!] WARNING: Jenkins did not respond - tunnel will still start but may not work' }"
echo.

echo [*] Starting SSH tunnel via localhost.run...
echo     The public URL will appear below once connected.
echo     Share the https://xxxx.lhr.life URL with your team.
echo     Press Ctrl+C to stop.
echo ============================================================
echo.

ssh -o StrictHostKeyChecking=no -o ServerAliveInterval=30 ^
    -R 80:localhost:%JENKINS_PORT% nokey@localhost.run

echo.
echo ============================================================
echo   Tunnel stopped.
echo ============================================================
pause
