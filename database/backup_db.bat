@echo off
title Backup Database DTC
echo =========================================
echo       BACKUP DATABASE DTC (MYSQL)
echo =========================================
echo.

set MYSQLDUMP_PATH=c:\xampp\mysql\bin\mysqldump.exe
set DB_USER=root
set DB_NAME=dtc
set BACKUP_FILE=backup.sql

if not exist "%MYSQLDUMP_PATH%" (
    echo Error: mysqldump.exe tidak ditemukan di %MYSQLDUMP_PATH%
    echo Pastikan XAMPP terinstall di C:\xampp
    pause
    exit /b
)

echo Mem-backup database "%DB_NAME%" ke "%BACKUP_FILE%"...
"%MYSQLDUMP_PATH%" -u %DB_USER% --hex-blob --routines --triggers --databases %DB_NAME% > "%BACKUP_FILE%"

if %ERRORLEVEL% equ 0 (
    echo.
    echo [OK] Backup Berhasil!
) else (
    echo.
    echo [ERROR] Backup Gagal!
)

echo.
pause
