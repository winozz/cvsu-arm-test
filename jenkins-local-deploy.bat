@echo off
REM ================================================================
REM  Jenkins Local Dev Deploy Script - CVSU ARM Test
REM ================================================================
REM  Purpose:
REM    - Fetch Docker image from GHCR
REM    - Run locally for development/testing
REM    - Tunnel via Cloudflare for temporary public access
REM ================================================================

setlocal enabledelayedexpansion

set "DEPLOY_START_RAW=%TIME%"
for /f "tokens=1-4 delims=:." %%a in ("%TIME%") do (
    set /a "START_S=(%%a * 3600) + (1%%b %% 100 * 60) + (1%%c %% 100)"
)
goto :after_functions

:log
    for /f "tokens=1-4 delims=:." %%a in ("%TIME%") do (
        set /a "NOW_S=(%%a * 3600) + (1%%b %% 100 * 60) + (1%%c %% 100)"
    )
    set /a "ELAPSED=!NOW_S! - !START_S!"
    echo [%TIME%] [+!ELAPSED!s] %~1
    goto :eof

:after_functions

set REGISTRY=ghcr.io
set IMAGE_NAME=winozz/cvsu-arm-test
set GITHUB_ACTOR=winozz

if not defined WORKSPACE set WORKSPACE=C:\Users\user\Documents\laravel-training\cvsu-arm-test

if not defined GITHUB_TOKEN (
    echo ERROR: GITHUB_TOKEN is not defined. Add Secret text credential with ID "github-token" in Jenkins Environment.
    exit /b 1
)
call :log "GITHUB_TOKEN is defined"

if not defined APP_KEY (
    echo ERROR: APP_KEY is not defined. Add Secret text credential with ID "laravel-app-key" in Jenkins Environment.
    exit /b 1
)
call :log "APP_KEY is defined"

if not defined IMAGE_TAG set IMAGE_TAG=latest
if not defined LOCAL_PORT set LOCAL_PORT=9091
if not defined CONTAINER_NAME set CONTAINER_NAME=cvsu-arm-test-dev
if not defined TUNNEL_NAME set TUNNEL_NAME=cvsu-arm-test-tunnel

echo ============================================================
echo  Jenkins Local Dev Deploy - CVSU ARM Test
echo ============================================================
call :log "Deploy started"


docker --version >nul 2>&1
if errorlevel 1 (
    call :log "ERROR: Docker is not installed or not in PATH"
    exit /b 1
)
call :log "Docker is available"
echo.
call :log "=== Parameters ==="
echo   IMAGE_TAG:      %IMAGE_TAG%
echo   LOCAL_PORT:     %LOCAL_PORT%
echo   CONTAINER_NAME: %CONTAINER_NAME%
echo   TUNNEL_NAME:    %TUNNEL_NAME%
echo.
call :log "=== Fixed Config ==="
echo   REGISTRY:       %REGISTRY%
echo   IMAGE:          %IMAGE_NAME%:%IMAGE_TAG%
echo.

call :log "[1/4] Logging in to GHCR..."
echo %GITHUB_TOKEN% | docker login %REGISTRY% -u %GITHUB_ACTOR% --password-stdin
if errorlevel 1 (
    call :log "WARNING: Docker login failed - will try cached image"
) else (
    call :log "GHCR login OK."
)
echo.

call :log "[2/4] Pulling image %REGISTRY%/%IMAGE_NAME%:%IMAGE_TAG%..."
call :log "Pulling... (this can take 2-10 min on first pull, ~10s if up to date)"
docker pull --platform linux/amd64 %REGISTRY%/%IMAGE_NAME%:%IMAGE_TAG%
if errorlevel 1 (
    call :log "WARNING: Pull failed - checking for cached image..."
    docker image inspect %REGISTRY%/%IMAGE_NAME%:%IMAGE_TAG% >nul 2>&1
    if errorlevel 1 (
        call :log "ERROR: No cached image available and pull failed"
        exit /b 1
    )
    call :log "Using cached image."
) else (
    call :log "Pull complete."
)

call :log "Image info:"
for /f "tokens=*" %%d in ('docker inspect --format "{{.Id}}" %REGISTRY%/%IMAGE_NAME%:%IMAGE_TAG% 2^>nul') do echo   Image ID: %%d
for /f "tokens=*" %%d in ('docker inspect --format "{{.Created}}" %REGISTRY%/%IMAGE_NAME%:%IMAGE_TAG% 2^>nul') do echo   Created:  %%d
echo.

call :log "[3/4] Cleaning up existing container..."
docker rm -f %CONTAINER_NAME% >nul 2>&1
call :log "Old container removed."
echo.

call :log "[4/4] Running container on port %LOCAL_PORT%..."
call :log "Starting container..."
docker run -d --name %CONTAINER_NAME% ^
    -p %LOCAL_PORT%:8080 ^
    -e APP_KEY=%APP_KEY% ^
    -e APP_URL=http://127.0.0.1:%LOCAL_PORT% ^
    -e APP_ENV=staging ^
    -e APP_DEBUG=false ^
    -e LOG_CHANNEL=stderr ^
    -e DB_CONNECTION=sqlite ^
    -e SESSION_DRIVER=database ^
    -e QUEUE_CONNECTION=database ^
    -e CACHE_STORE=database ^
    -e HEALTHCHECK_PATH=/up ^
    %REGISTRY%/%IMAGE_NAME%:%IMAGE_TAG%

if errorlevel 1 (
    call :log "ERROR: Failed to start container"
    exit /b 1
)

call :log "Container started: %CONTAINER_NAME%"
echo.

call :log "Waiting for Laravel application to start (up to 60 seconds)..."
set HTTP_CODE=000
set RETRY_COUNT=0
:health_check_loop
set /a RETRY_COUNT+=1
ping -n 4 127.0.0.1 >nul 2>&1
docker exec %CONTAINER_NAME% curl -s -o /dev/null -w "%%{http_code}" http://127.0.0.1:8080/up >temp_http.txt 2>nul
if exist temp_http.txt (
    set /p HTTP_CODE=<temp_http.txt
    del temp_http.txt
)
if "%HTTP_CODE%"=="200" (
    call :log "Health check PASSED on attempt %RETRY_COUNT% (HTTP %HTTP_CODE%)"
    goto :health_done
)
if %RETRY_COUNT% geq 20 (
    call :log "WARNING: Health check failed after 20 attempts (HTTP %HTTP_CODE%)"
    call :log "Container logs (last 20 lines):"
    docker logs --tail 20 %CONTAINER_NAME%
    goto :health_done
)
call :log "  Attempt %RETRY_COUNT%: HTTP %HTTP_CODE% - retrying..."
goto :health_check_loop
:health_done
echo.

call :log "Container Status:"
docker inspect --format "  Name: {{.Name}} | Status: {{.State.Status}}" %CONTAINER_NAME%
docker stats %CONTAINER_NAME% --no-stream --format "  CPU: {{.CPUPerc}} | Mem: {{.MemUsage}}"
echo.

REM ---------------------------------------------------------------
REM  BONUS: Tunnel for temporary public access
REM  TUNNEL_TYPE parameter: ssh (default) | cloudflare | none
REM ---------------------------------------------------------------
if not defined TUNNEL_TYPE set "TUNNEL_TYPE=ssh"
call :log "[BONUS] Tunnel type: %TUNNEL_TYPE%"
echo.

if /i "%TUNNEL_TYPE%"=="none" (
    call :log "Tunnel skipped (TUNNEL_TYPE=none)."
    goto :skip_tunnel
)
if /i "%TUNNEL_TYPE%"=="cloudflare" goto :do_cloudflare_tunnel
REM default: ssh
goto :do_ssh_tunnel

REM ---------------------------------------------------------------
REM  SSH tunnel via localhost.run (built-in OpenSSH, no download)
REM ---------------------------------------------------------------
:do_ssh_tunnel
call :log "Starting SSH tunnel (localhost.run)..."
powershell -NoProfile -Command "Get-Process ssh -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -like '*localhost.run*' } | Stop-Process -Force -ErrorAction SilentlyContinue" >nul 2>&1

set "SSH_TUNNEL_LOG=%WORKSPACE%\ssh-tunnel.log"
if exist "%SSH_TUNNEL_LOG%" del "%SSH_TUNNEL_LOG%"

where ssh >nul 2>&1
if errorlevel 1 (
    call :log "WARNING: ssh.exe not found in PATH - skipping tunnel"
    goto :skip_tunnel
)

powershell -NoProfile -Command "Start-Process -FilePath 'ssh' -ArgumentList @('-o','StrictHostKeyChecking=no','-o','ServerAliveInterval=30','-o','ExitOnForwardFailure=yes','-R','80:127.0.0.1:%LOCAL_PORT%','nokey@localhost.run') -RedirectStandardOutput '%SSH_TUNNEL_LOG%' -RedirectStandardError '%SSH_TUNNEL_LOG%' -WindowStyle Hidden -PassThru | Select-Object -ExpandProperty Id | Out-File '%WORKSPACE%\ssh-tunnel.pid'"

call :log "Waiting for tunnel URL..."
set "TUNNEL_URL="
set "TUNNEL_WAIT=0"
:ssh_wait_loop
ping -n 3 127.0.0.1 >nul 2>&1
set /a TUNNEL_WAIT+=3
for /f "usebackq delims=" %%X in (`powershell -NoProfile -Command "if (Test-Path '%SSH_TUNNEL_LOG%') { $m = Select-String -Path '%SSH_TUNNEL_LOG%' -Pattern 'https://\S+\.lhr\.life' | Select-Object -Last 1; if ($m) { [regex]::Match($m.Line,'https://\S+\.lhr\.life').Value } }"`) do set "TUNNEL_URL=%%X"
if defined TUNNEL_URL goto :tunnel_found
if %TUNNEL_WAIT% geq 30 (
    call :log "WARNING: Tunnel URL not found after 30 seconds"
    if exist "%SSH_TUNNEL_LOG%" type "%SSH_TUNNEL_LOG%"
    goto :skip_tunnel
)
goto :ssh_wait_loop

REM ---------------------------------------------------------------
REM  Cloudflare tunnel via trycloudflare.com (downloads cloudflared.exe)
REM ---------------------------------------------------------------
:do_cloudflare_tunnel
call :log "Starting Cloudflare tunnel (trycloudflare.com)..."
set "CLOUDFLARED_EXE=%WORKSPACE%\cloudflared.exe"
if not exist "%CLOUDFLARED_EXE%" (
    call :log "Downloading portable cloudflared..."
    powershell -NoProfile -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile '%CLOUDFLARED_EXE%' -UseBasicParsing"
    if errorlevel 1 (
        call :log "WARNING: Failed to download cloudflared - skipping tunnel"
        goto :skip_tunnel
    )
    call :log "cloudflared downloaded."
)

set "CF_TUNNEL_LOG=%WORKSPACE%\cloudflared-tunnel.log"
set "CF_TUNNEL_ERR=%WORKSPACE%\cloudflared-tunnel-err.log"
taskkill /F /IM cloudflared.exe >nul 2>&1
if exist "%CF_TUNNEL_LOG%" del "%CF_TUNNEL_LOG%"
if exist "%CF_TUNNEL_ERR%" del "%CF_TUNNEL_ERR%"

set "CF_HOME=%WORKSPACE%\.cf-home"
if exist "%CF_HOME%" rmdir /s /q "%CF_HOME%"
mkdir "%CF_HOME%" 2>nul

powershell -NoProfile -Command "$env:USERPROFILE = '%CF_HOME%'; $env:HOME = '%CF_HOME%'; $env:BUILD_ID = 'dontKillMe'; $env:JENKINS_NODE_COOKIE = 'dontKillMe'; Start-Process -FilePath '%CLOUDFLARED_EXE%' -ArgumentList @('tunnel','--url','http://127.0.0.1:%LOCAL_PORT%','--no-autoupdate','--protocol','http2') -WorkingDirectory '%WORKSPACE%' -RedirectStandardError '%CF_TUNNEL_LOG%' -RedirectStandardOutput '%CF_TUNNEL_ERR%' -WindowStyle Hidden | Out-Null"

call :log "Waiting for tunnel URL..."
set "TUNNEL_URL="
set "TUNNEL_WAIT=0"
:cf_wait_loop
ping -n 3 127.0.0.1 >nul 2>&1
set /a TUNNEL_WAIT+=3
for /f "usebackq delims=" %%X in (`powershell -NoProfile -Command "if (Test-Path '%CF_TUNNEL_LOG%') { $m = Select-String -Path '%CF_TUNNEL_LOG%' -Pattern 'https://\S+trycloudflare\.com' | Select-Object -Last 1; if ($m) { [regex]::Match($m.Line,'https://\S+trycloudflare\.com').Value } }"`) do set "TUNNEL_URL=%%X"
if defined TUNNEL_URL goto :tunnel_found
if %TUNNEL_WAIT% geq 30 (
    call :log "WARNING: Tunnel URL not found after 30 seconds"
    if exist "%CF_TUNNEL_LOG%" type "%CF_TUNNEL_LOG%"
    goto :skip_tunnel
)
goto :cf_wait_loop

:tunnel_found
echo.
echo ============================================================
call :log "Tunnel is LIVE!"
call :log "  Type:       %TUNNEL_TYPE%"
call :log "  Public URL: %TUNNEL_URL%"
echo ============================================================
echo.

:skip_tunnel
for /f "tokens=1-4 delims=:." %%a in ("%TIME%") do (
    set /a "END_S=(%%a * 3600) + (1%%b %% 100 * 60) + (1%%c %% 100)"
)
set /a "TOTAL_S=!END_S! - !START_S!"

echo ============================================================
call :log "Local Dev Deployment Complete!"
echo   Container:  %CONTAINER_NAME%
echo   Image:      %REGISTRY%/%IMAGE_NAME%:%IMAGE_TAG%
echo   Local URL:  http://127.0.0.1:%LOCAL_PORT%
echo   Status:     http://127.0.0.1:%LOCAL_PORT%/up
echo   Docker:     docker logs -f %CONTAINER_NAME%
echo   Total time: !TOTAL_S! seconds
echo ============================================================
echo.
call :log "To stop the container:"
echo   docker stop %CONTAINER_NAME%
echo.
call :log "To view logs:"
echo   docker logs -f %CONTAINER_NAME%
echo.
call :log "To remove container:"
echo   docker rm %CONTAINER_NAME%
echo.
call :log "Ready for testing!"
echo ============================================================
exit /b 0
