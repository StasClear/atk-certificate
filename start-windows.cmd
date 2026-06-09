@echo off
setlocal
cd /d "%~dp0"

set "PHP_BIN=php"
where php >nul 2>nul
if errorlevel 1 set "PHP_BIN="

if "%PHP_BIN%"=="" (
    for /f "delims=" %%P in ('dir /b /s "C:\laragon\bin\php\php.exe" "C:\laragon\bin\php\php-*\php.exe" "D:\laragon\bin\php\php.exe" "D:\laragon\bin\php\php-*\php.exe" 2^>nul') do (
        set "PHP_BIN=%%P"
    )
)

if "%PHP_BIN%"=="" (
    echo PHP is not installed or is not available in PATH.
    echo Install PHP 8.1+ or Laragon, then run this file again.
    pause
    exit /b 1
)

if not exist config.local.php (
    copy config.example.php config.local.php >nul
    echo Created config.local.php from config.example.php.
    echo Check data_dir in config.local.php if your Yandex Disk folder has another path.
)

start "" "http://127.0.0.1:8090/certificate.php"
"%PHP_BIN%" -S 127.0.0.1:8090
