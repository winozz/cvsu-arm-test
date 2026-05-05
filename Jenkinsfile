pipeline {
    agent any

    parameters {
        choice(
            name: 'TUNNEL_TYPE',
            choices: ['ssh', 'cloudflare', 'none'],
            description: 'Tunnel: ssh = localhost.run (no download), cloudflare = trycloudflare.com, none = skip'
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
                withCredentials([string(credentialsId: 'APP_KEY', variable: 'APP_KEY')]) {
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
                    set "TLOG=%WORKSPACE%\\ssh-tunnel.log"
                    if exist "%TLOG%" del "%TLOG%"
                    where ssh >nul 2>&1
                    if errorlevel 1 ( echo WARNING: ssh.exe not found - skipping & exit /b 0 )
                    powershell -NoProfile -Command "Start-Process -FilePath 'ssh' -ArgumentList @('-o','StrictHostKeyChecking=no','-o','ServerAliveInterval=30','-o','ExitOnForwardFailure=yes','-R','80:127.0.0.1:%LOCAL_PORT%','nokey@localhost.run') -RedirectStandardOutput '%TLOG%' -RedirectStandardError '%TLOG%' -WindowStyle Hidden | Out-Null"
                    echo Waiting for tunnel URL...
                    set WAIT=0
                    set TDONE=0
                    :tw_loop
                    ping -n 3 127.0.0.1 >nul 2>&1
                    set /a WAIT+=3
                    set TURL=
                    for /f "usebackq delims=" %%X in (`powershell -NoProfile -Command "if (Test-Path '%TLOG%') { $m = Select-String '%TLOG%' -Pattern 'https://\\S+\\.lhr\\.life' | Select-Object -Last 1; if ($m) { [regex]::Match($m.Line,'https://\\S+\\.lhr\\.life').Value } }"`) do set TURL=%%X
                    if defined TURL set TDONE=1
                    if %WAIT% geq 30 ( echo WARNING: tunnel URL not ready after 30s & if exist "%TLOG%" type "%TLOG%" & set TDONE=1 )
                    if "%TDONE%"=="0" goto :tw_loop
                    if defined TURL ( echo. & echo ==================================================== & echo   Public URL: %TURL% & echo ==================================================== )
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