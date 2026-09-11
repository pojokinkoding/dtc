@echo off
setlocal enabledelayedexpansion
title Backup Database DTC (Windows)

echo ==============================================================================
echo                 BACKUP DATABASE SYSTEM (WINDOWS / XAMPP)
echo ==============================================================================
echo.

:: 1. Tentukan direktori script
set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%"

:: 2. Cari executable mysqldump
set "DUMP_CMD="
where mysqldump.exe >nul 2>nul
if %ERRORLEVEL% equ 0 (
    set "DUMP_CMD=mysqldump.exe"
) else if exist "C:\xampp\mysql\bin\mysqldump.exe" (
    set "DUMP_CMD=C:\xampp\mysql\bin\mysqldump.exe"
) else if exist "D:\xampp\mysql\bin\mysqldump.exe" (
    set "DUMP_CMD=D:\xampp\mysql\bin\mysqldump.exe"
) else if exist "E:\xampp\mysql\bin\mysqldump.exe" (
    set "DUMP_CMD=E:\xampp\mysql\bin\mysqldump.exe"
)

if "%DUMP_CMD%"=="" (
    echo [ERROR] mysqldump.exe tidak ditemukan di sistem maupun folder XAMPP!
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

:: Override lewat argumen baris perintah jika ada: backup_db.bat [db_name] [user] [pass] [host] [port]
if not "%~1"=="" set "DB_NAME=%~1"
if not "%~2"=="" set "DB_USER=%~2"
if not "%~3"=="" set "DB_PASS=%~3"
if not "%~4"=="" set "DB_HOST=%~4"
if not "%~5"=="" set "DB_PORT=%~5"

:: 4. Format timestamp YYYYMMDD_HHMMSS
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value 2^>nul') do set "DATETIME=%%I"
if not "%DATETIME%"=="" (
    set "YYYY=%DATETIME:~0,4%"
    set "MM=%DATETIME:~4,2%"
    set "DD=%DATETIME:~6,2%"
    set "HH=%DATETIME:~8,2%"
    set "MIN=%DATETIME:~10,2%"
    set "SS=%DATETIME:~12,2%"
    set "TIMESTAMP=!YYYY!!MM!!DD!_!HH!!MIN!!SS!"
) else (
    set "TIMESTAMP=%date:~10,4%%date:~4,2%%date:~7,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
    set "TIMESTAMP=!TIMESTAMP: =0!"
)

set "BACKUP_FILE=backup_%DB_NAME%_%TIMESTAMP%.sql"
set "LATEST_FILE=backup_latest.sql"

echo [*] Target Host     : %DB_HOST%:%DB_PORT%
echo [*] Database Target : %DB_NAME%
echo [*] User Database   : %DB_USER%
echo [*] Output File     : %BACKUP_FILE%
echo [*] Executable      : %DUMP_CMD%
echo ------------------------------------------------------------------------------
echo [*] Sedang membuat snapshot backup database...

:: Parameter password jika diisi
set "PASS_PARAM="
if not "%DB_PASS%"=="" set "PASS_PARAM=-p%DB_PASS%"

:: Eksekusi mysqldump
"%DUMP_CMD%" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% %PASS_PARAM% --default-character-set=utf8mb4 --single-transaction --quick --routines --triggers --hex-blob --databases %DB_NAME% > "%BACKUP_FILE%" 2>backup_err.tmp
set "DUMP_EXIT=%ERRORLEVEL%"

if not %DUMP_EXIT% equ 0 goto :failed
if not exist "%BACKUP_FILE%" goto :failed

:: Cek apakah ukuran file 0 byte
for %%F in ("%BACKUP_FILE%") do set "FILE_SIZE=%%~zF"
if "%FILE_SIZE%"=="0" goto :failed

:: Salin ke backup_latest.sql dan backup.sql
copy /y "%BACKUP_FILE%" "%LATEST_FILE%" >nul 2>&1
copy /y "%BACKUP_FILE%" "backup.sql" >nul 2>&1
if exist "backup_err.tmp" del "backup_err.tmp" >nul 2>&1

echo.
echo ==============================================================================
echo [OK] BACKUP DATABASE BERHASIL!
echo ==============================================================================
echo File Snapshot : %SCRIPT_DIR%%BACKUP_FILE%
echo Link Latest   : %SCRIPT_DIR%%LATEST_FILE%
echo Ukuran File   : %FILE_SIZE% bytes
echo ==============================================================================
goto :end

:failed
echo.
echo ==============================================================================
echo [ERROR] BACKUP DATABASE GAGAL! (Exit Code: %DUMP_EXIT%)
echo ==============================================================================
if exist "backup_err.tmp" (
    type "backup_err.tmp"
    del "backup_err.tmp" >nul 2>&1
)
if exist "%BACKUP_FILE%" del "%BACKUP_FILE%" >nul 2>&1
echo.
echo TIPS TROUBLESHOOTING:
echo 1. Pastikan service MySQL di XAMPP Control Panel sudah AKTIF.
echo 2. Jika user MySQL menggunakan password, jalankan:
echo    backup_db.bat %DB_NAME% %DB_USER% password_anda
echo ==============================================================================

:end
echo.
if not "%~1"=="" goto :eof
pause
