@echo off
setlocal enabledelayedexpansion
title Restore Database DTC (Windows)

echo ==============================================================================
echo                 RESTORE DATABASE SYSTEM (WINDOWS / XAMPP)
echo ==============================================================================
echo.

:: 1. Tentukan direktori script
set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%"

:: 2. Cari executable mysql.exe
set "MYSQL_CMD="
where mysql.exe >nul 2>nul
if %ERRORLEVEL% equ 0 (
    set "MYSQL_CMD=mysql.exe"
) else if exist "C:\xampp\mysql\bin\mysql.exe" (
    set "MYSQL_CMD=C:\xampp\mysql\bin\mysql.exe"
) else if exist "D:\xampp\mysql\bin\mysql.exe" (
    set "MYSQL_CMD=D:\xampp\mysql\bin\mysql.exe"
) else if exist "E:\xampp\mysql\bin\mysql.exe" (
    set "MYSQL_CMD=E:\xampp\mysql\bin\mysql.exe"
)

if "%MYSQL_CMD%"=="" (
    echo [ERROR] mysql.exe tidak ditemukan di sistem maupun folder XAMPP!
    echo Silakan pastikan XAMPP terinstall di C:\xampp atau tambahkan mysql\bin ke PATH.
    echo.
    pause
    exit /b 1
)

:: 3. Konfigurasi Database Default
set "DB_HOST=localhost"
set "DB_NAME=dtc_v1"
set "DB_USER=root"
set "DB_PASS="
set "DB_PORT=3306"

:: Cek PHP untuk auto-detect dari config/config.php
set "CONFIG_FILE=%SCRIPT_DIR%..\config\config.php"
set "PHP_BIN="
if exist "C:\xampp\php\php.exe" set "PHP_BIN=C:\xampp\php\php.exe"
if exist "D:\xampp\php\php.exe" set "PHP_BIN=D:\xampp\php\php.exe"
if "%PHP_BIN%"=="" where php.exe >nul 2>nul && set "PHP_BIN=php.exe"

if not "%PHP_BIN%"=="" if exist "%CONFIG_FILE%" (
    for /f "usebackq tokens=1-4 delims=|" %%a in (`%PHP_BIN% -r "require '%CONFIG_FILE:\=/%'; echo DB_NAME.'|'.DB_HOST.'|'.DB_USER.'|'.DB_PASS;" 2^>nul`) do (
        if not "%%a"=="" set "DB_NAME=%%a"
        if not "%%b"=="" set "DB_HOST=%%b"
        if not "%%c"=="" set "DB_USER=%%c"
        if not "%%d"=="" set "DB_PASS=%%d"
    )
)

:: 4. Deteksi File Backup & Argumen
set "RESTORE_FILE="
set "AUTO_YES=0"

:: Parsing argumen 1 sampai 4
for %%A in ("%~1" "%~2" "%~3" "%~4") do (
    if /i "%%~A"=="/y" set "AUTO_YES=1"
    if /i "%%~A"=="-y" set "AUTO_YES=1"
    if /i "%%~A"=="--force" set "AUTO_YES=1"
)

:: Periksa apakah argumen pertama adalah file SQL
if not "%~1"=="" if not "%~1"=="/y" if not "%~1"=="-y" if not "%~1"=="--force" (
    if exist "%~1" set "RESTORE_FILE=%~1"
    if exist "%SCRIPT_DIR%%~1" set "RESTORE_FILE=%SCRIPT_DIR%%~1"
)

:: Jika belum ditentukan, cari backup_latest.sql, backup.sql, atau backup_*.sql terbaru
if "%RESTORE_FILE%"=="" (
    if exist "backup_latest.sql" (
        set "RESTORE_FILE=backup_latest.sql"
    ) else if exist "backup.sql" (
        set "RESTORE_FILE=backup.sql"
    ) else (
        for /f "delims=" %%F in ('dir /b /o-d backup_*.sql 2^>nul') do (
            if "!RESTORE_FILE!"=="" set "RESTORE_FILE=%%F"
        )
    )
)

if "%RESTORE_FILE%"=="" goto :no_file_found
if not exist "%RESTORE_FILE%" goto :no_file_found

for %%F in ("%RESTORE_FILE%") do set "FILE_SIZE=%%~zF"

:: Override database lewat argumen jika ada
if not "%~2"=="" if not "%~2"=="/y" if not "%~2"=="-y" set "DB_NAME=%~2"
if not "%~3"=="" if not "%~3"=="/y" if not "%~3"=="-y" set "DB_USER=%~3"
if not "%~4"=="" if not "%~4"=="/y" if not "%~4"=="-y" set "DB_PASS=%~4"

echo [*] Target Host       : %DB_HOST%:%DB_PORT%
echo [*] Database Target   : %DB_NAME%
echo [*] User Database     : %DB_USER%
echo [*] File Backup Input : %RESTORE_FILE%
echo [*] Ukuran File       : %FILE_SIZE% bytes
echo [*] Executable        : %MYSQL_CMD%
echo ------------------------------------------------------------------------------
echo [PERINGATAN KRITIS]
echo Proses ini akan MENIMPA SELURUH DATA pada database '%DB_NAME%'!
echo ------------------------------------------------------------------------------

if "%AUTO_YES%"=="1" goto :do_restore

set /p "CONFIRM=Apakah Anda yakin ingin me-restore database '%DB_NAME%'? (Y/T): "
if /i not "%CONFIRM%"=="y" (
    echo.
    echo [BATAL] Proses restore dibatalkan oleh pengguna.
    echo.
    pause
    exit /b 0
)

:do_restore
echo.
echo [*] Memastikan database '%DB_NAME%' tersedia di MySQL...
set "PASS_PARAM="
if not "%DB_PASS%"=="" set "PASS_PARAM=-p%DB_PASS%"

"%MYSQL_CMD%" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% %PASS_PARAM% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>restore_err.tmp
if not %ERRORLEVEL% equ 0 goto :failed

echo [*] Sedang me-restore data dari '%RESTORE_FILE%'...
"%MYSQL_CMD%" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% %PASS_PARAM% --default-character-set=utf8mb4 %DB_NAME% < "%RESTORE_FILE%" 2>restore_err.tmp
set "RESTORE_EXIT=%ERRORLEVEL%"

if not %RESTORE_EXIT% equ 0 goto :failed
if exist "restore_err.tmp" del "restore_err.tmp" >nul 2>&1

echo.
echo ==============================================================================
echo [OK] RESTORE DATABASE BERHASIL!
echo ==============================================================================
echo Database '%DB_NAME%' telah berhasil dipulihkan dari:
echo %RESTORE_FILE%
echo ==============================================================================
goto :end

:no_file_found
echo [ERROR] Tidak ditemukan file backup .sql untuk di-restore!
echo Pastikan file backup berada di folder yang sama dengan script ini.
echo Contoh file: backup_latest.sql atau backup_dtc_v1_YYYYMMDD_HHMMSS.sql
echo.
echo Anda juga bisa men-drag and drop file .sql langsung ke script ini.
goto :end

:failed
echo.
echo ==============================================================================
echo [ERROR] RESTORE DATABASE GAGAL! (Exit Code: %RESTORE_EXIT%)
echo ==============================================================================
if exist "restore_err.tmp" (
    type "restore_err.tmp"
    del "restore_err.tmp" >nul 2>&1
)
echo.
echo TIPS TROUBLESHOOTING:
echo 1. Pastikan service MySQL di XAMPP Control Panel AKTIF.
echo 2. Periksa apakah kredensial user/password sudah sesuai.
echo 3. Periksa apakah file SQL tidak korup.
echo ==============================================================================

:end
echo.
if "%AUTO_YES%"=="1" goto :eof
if not "%~1"=="" goto :eof
pause
