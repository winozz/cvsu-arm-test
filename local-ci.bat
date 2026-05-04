@echo off
REM ================================================================
REM  Local CI/CD Pipeline - CVSU ARM Test
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

set IMAGE_NAME=cvsu-arm-test
set IMAGE_TAG=local-test
set CONTAINER_NAME=cvsu-arm-test-local-ci
set LOCAL_PORT=8081

echo ============================================================
echo  Local CI/CD Pipeline - CVSU ARM Test
echo ============================================================
call :log "Pipeline started"
echo.

call :log "[1/5] Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --no-progress
if errorlevel 1 (
    call :log "ERROR: Composer install failed"
    exit /b 1
)
echo.

call :log "[2/5] Setting up test environment..."
copy /Y .env.example .env >nul
if not exist database mkdir database
type nul > database\database.sqlite
php artisan key:generate --no-interaction
if errorlevel 1 (
    call :log "ERROR: Failed to generate application key"
    exit /b 1
)
php artisan migrate --force --no-interaction
if errorlevel 1 (
    call :log "ERROR: Failed to run migrations"
    exit /b 1
)
for /f "usebackq delims=" %%K in (`powershell -NoProfile -Command "$line = (Select-String -Path '.env' -Pattern '^APP_KEY=' | Select-Object -First 1).Line; if ($line) { $line.Split('=', 2)[1] }"`) do set "APP_KEY_VALUE=%%K"
if not defined APP_KEY_VALUE (
    call :log "ERROR: APP_KEY could not be read from .env"
    exit /b 1
)
echo.

call :log "[3/5] Running test suite..."
php artisan test
if errorlevel 1 (
    call :log "ERROR: Tests failed"
    exit /b 1
)
echo.

call :log "[4/5] Building Docker image..."
docker build -f docker/app/Dockerfile -t %IMAGE_NAME%:%IMAGE_TAG% .
if errorlevel 1 (
    call :log "ERROR: Docker build failed"
    exit /b 1
)
echo.

call :log "[5/5] Testing Docker container locally..."
docker stop %CONTAINER_NAME% >nul 2>&1
docker rm %CONTAINER_NAME% >nul 2>&1
docker run -d --name %CONTAINER_NAME% ^
    -p %LOCAL_PORT%:8080 ^
    -e APP_KEY=%APP_KEY_VALUE% ^
    -e APP_URL=http://localhost:%LOCAL_PORT% ^
    -e APP_ENV=testing ^
    -e LOG_CHANNEL=stderr ^
    -e DB_CONNECTION=sqlite ^
    -e SESSION_DRIVER=database ^
    -e QUEUE_CONNECTION=database ^
    -e CACHE_STORE=database ^
    -e HEALTHCHECK_PATH=/up ^
    %IMAGE_NAME%:%IMAGE_TAG%
if errorlevel 1 (
    call :log "ERROR: Failed to start local CI container"
    exit /b 1
)

call :log "Container started. Waiting for app to be ready..."
timeout /t 10 /nobreak >nul
set HTTP_CODE=000
for /f "tokens=*" %%h in ('docker exec %CONTAINER_NAME% curl -s -o /dev/null -w "%%{http_code}" http://localhost:8080/up 2^>nul') do set HTTP_CODE=%%h
if not "%HTTP_CODE%"=="200" (
    call :log "ERROR: Health check failed (HTTP %HTTP_CODE%)"
    docker logs %CONTAINER_NAME%
    exit /b 1
)

call :log "Health check PASSED (HTTP %HTTP_CODE%)"
docker inspect --format "  Name: {{.Name}} | Status: {{.State.Status}}" %CONTAINER_NAME%
docker stats %CONTAINER_NAME% --no-stream --format "  CPU: {{.CPUPerc}} | Mem: {{.MemUsage}}"
echo.

for /f "tokens=1-4 delims=:." %%a in ("%TIME%") do (
    set /a "END_S=(%%a * 3600) + (1%%b %% 100 * 60) + (1%%c %% 100)"
)
set /a "TOTAL_S=!END_S! - !START_S!"

echo ============================================================
call :log "LOCAL CI/CD PIPELINE COMPLETED SUCCESSFULLY"
echo ============================================================
echo   App:    http://localhost:%LOCAL_PORT%
echo   Health: http://localhost:%LOCAL_PORT%/up
echo   Logs:   docker logs -f %CONTAINER_NAME%
echo   Total time: !TOTAL_S! seconds
echo ============================================================
