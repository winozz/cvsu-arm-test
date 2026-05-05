pipeline {
    agent any

    parameters {
        choice(
            name: 'TUNNEL_TYPE',
            choices: ['lan', 'ssh', 'cloudflare', 'tailscale', 'none'],
            description: 'Tunnel: lan = local network IP:port (no install needed), ssh = localhost.run, cloudflare = trycloudflare.com, tailscale = fixed IP:port (requires Tailscale), none = skip'
        )
        string(name: 'IMAGE_TAG',      defaultValue: 'latest',             description: 'Docker image tag to deploy')
        string(name: 'LOCAL_PORT',     defaultValue: '9091',               description: 'Host port for the container')
        string(name: 'CONTAINER_NAME', defaultValue: 'cvsu-arm-test-dev',  description: 'Docker container name')
    }

    stages {
        stage('Pull Image') {
            steps {
                withCredentials([string(credentialsId: 'GITHUB_TOKEN', variable: 'GITHUB_TOKEN')]) {
                    bat '''
                        echo %GITHUB_TOKEN% | docker login ghcr.io -u winozz --password-stdin
                        docker pull --platform linux/amd64 ghcr.io/winozz/cvsu-arm-test:%IMAGE_TAG%
                    '''
                }
            }
        }

        stage('Deploy Container') {
            steps {
                withCredentials([
                    string(credentialsId: 'APP_KEY', variable: 'APP_KEY'),
                    string(credentialsId: 'GOOGLE_CLIENT_SECRET', variable: 'GOOGLE_CLIENT_SECRET')
                ]) {
                    bat '''
                        docker rm -f %CONTAINER_NAME% >nul 2>&1
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
                            -e GOOGLE_CLIENT_ID=577658695283-rn74vhl7f3hacitp0ispdnrg0rdhmhl4.apps.googleusercontent.com ^
                            -e GOOGLE_CLIENT_SECRET=%GOOGLE_CLIENT_SECRET% ^
                            -e GOOGLE_REDIRECT_URI=http://localhost:%LOCAL_PORT%/auth/google/callback ^
                            ghcr.io/winozz/cvsu-arm-test:%IMAGE_TAG%
                    '''
                }
            }
        }

        stage('Health Check') {
            steps {
                bat '''
                    setlocal enabledelayedexpansion
                    set RETRY=0
                    :hc_loop
                    set /a RETRY+=1
                    ping -n 4 127.0.0.1 >nul 2>&1
                    docker exec %CONTAINER_NAME% curl -s -o /dev/null -w "%%{http_code}" http://127.0.0.1:8080/up >hc_tmp.txt 2>nul
                    set /p HC=<hc_tmp.txt & del hc_tmp.txt 2>nul
                    if "!HC!"=="200" ( echo [PASS] HTTP 200 & goto :hc_done )
                    if !RETRY! geq 15 ( echo [WARN] health check timed out ^(last HTTP !HC!^) & goto :hc_done )
                    echo Attempt !RETRY!/15 HTTP !HC! - retrying...
                    goto :hc_loop
                    :hc_done
                '''
            }
        }

        stage('Tunnel') {
            steps {
                bat '''
                    if /i "%TUNNEL_TYPE%"=="none" ( echo Tunnel skipped. & exit /b 0 )
                    if /i "%TUNNEL_TYPE%"=="lan" (
                        setlocal enabledelayedexpansion
                        for /f "usebackq delims=" %%I in (`powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.*' -and $_.InterfaceAlias -notlike '*Loopback*' -and $_.InterfaceAlias -notlike '*WSL*' -and $_.InterfaceAlias -notlike '*vEthernet*' } | Select-Object -First 1 -ExpandProperty IPAddress) 2>$null"`) do set LAN_IP=%%I
                        if not defined LAN_IP ( echo WARNING: Could not detect LAN IP & exit /b 0 )
                        echo.
                        echo ====================================================
                        echo   LAN URL:     http://!LAN_IP!:%LOCAL_PORT%
                        echo   Jenkins URL: http://!LAN_IP!:8081
                        echo   (Anyone on the same network can use these URLs)
                        echo ====================================================
                        exit /b 0
                    )
                    if /i "%TUNNEL_TYPE%"=="tailscale" (
                        where tailscale >nul 2>&1
                        if errorlevel 1 ( echo ERROR: tailscale.exe not found. Install from https://tailscale.com/download/windows & exit /b 1 )
                        for /f "usebackq delims=" %%I in (`tailscale ip -4 2^>nul`) do set TS_IP=%%I
                        if not defined TS_IP ( echo ERROR: Tailscale is not connected. Run: tailscale up & exit /b 1 )
                        echo.
                        echo ====================================================
                        echo   Tailscale URL: http://!TS_IP!:%LOCAL_PORT%
                        echo   Jenkins URL:   http://!TS_IP!:8081
                        echo ====================================================
                        exit /b 0
                    )
                    set "TLOG=%WORKSPACE%\\ssh-tunnel.err"
                    set "JLOG=%WORKSPACE%\\ssh-jenkins.err"
                    if exist "%TLOG%" del "%TLOG%"
                    if exist "%JLOG%" del "%JLOG%"
                    where ssh >nul 2>&1
                    if errorlevel 1 ( echo WARNING: ssh.exe not found - skipping & exit /b 0 )
                    powershell -NoProfile -Command "Start-Process -FilePath 'ssh' -ArgumentList @('-o','StrictHostKeyChecking=no','-o','ServerAliveInterval=30','-o','ExitOnForwardFailure=yes','-R','80:127.0.0.1:%LOCAL_PORT%','nokey@localhost.run') -RedirectStandardOutput '%WORKSPACE%\\ssh-tunnel.out' -RedirectStandardError '%TLOG%' -WindowStyle Hidden | Out-Null"
                    powershell -NoProfile -Command "Start-Process -FilePath 'ssh' -ArgumentList @('-o','StrictHostKeyChecking=no','-o','ServerAliveInterval=30','-o','ExitOnForwardFailure=yes','-R','80:127.0.0.1:8081','nokey@localhost.run') -RedirectStandardOutput '%WORKSPACE%\\ssh-jenkins.out' -RedirectStandardError '%JLOG%' -WindowStyle Hidden | Out-Null"
                    echo Waiting for tunnel URLs...
                    set WAIT=0
                    set TDONE=0
                    :tw_loop
                    ping -n 3 127.0.0.1 >nul 2>&1
                    set /a WAIT+=3
                    set TURL=
                    set JURL=
                    for /f "usebackq delims=" %%X in (`powershell -NoProfile -Command "if (Test-Path '%TLOG%') { $m = Select-String '%TLOG%' -Pattern 'https://\\S+\\.lhr\\.life' | Select-Object -Last 1; if ($m) { [regex]::Match($m.Line,'https://\\S+\\.lhr\\.life').Value } }"`) do set TURL=%%X
                    for /f "usebackq delims=" %%X in (`powershell -NoProfile -Command "if (Test-Path '%JLOG%') { $m = Select-String '%JLOG%' -Pattern 'https://\\S+\\.lhr\\.life' | Select-Object -Last 1; if ($m) { [regex]::Match($m.Line,'https://\\S+\\.lhr\\.life').Value } }"`) do set JURL=%%X
                    if defined TURL if defined JURL set TDONE=1
                    if %WAIT% geq 45 set TDONE=1
                    if "!TDONE!"=="0" goto :tw_loop
                    echo.
                    echo ====================================================
                    if defined TURL ( echo   App URL:     !TURL! ) else ( echo   App URL:     [tunnel not ready] )
                    if defined JURL ( echo   Jenkins URL: !JURL! ) else ( echo   Jenkins URL: [tunnel not ready] )
                    echo ====================================================
                '''
            }
        }
    }

    post {
        always {
            echo 'Build finished.'
        }
    }
}