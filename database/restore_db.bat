@echo off
title Restore Database DTC
echo =========================================
echo       RESTORE DATABASE DTC (MYSQL)
echo =========================================
echo.

set MYSQL_PATH=c:\xampp\mysql\bin\mysql.exe
set DB_USER=root
set DB_NAME=dtc
set BACKUP_FILE=backup.sql

if not exist "%MYSQL_PATH%" (
    echo Error: mysql.exe tidak ditemukan di %MYSQL_PATH%
    echo Pastikan XAMPP terinstall di C:\xampp
    pause
    exit /b
)

if not exist "%BACKUP_FILE%" (
    echo [ERROR] File backup "%BACKUP_FILE%" tidak ditemukan!
    echo Pastikan file backup berada di folder yang sama dengan script ini.
    echo.
    pause
    exit /b
)

echo Peringatan: Proses ini akan MENIMPA seluruh database '%DB_NAME%' saat ini!
echo Tekan tombol apa saja untuk melanjutkan, atau tutup jendela ini untuk membatalkan...
pause >nul

echo.
echo Me-restore database "%DB_NAME%" dari "%BACKUP_FILE%"...
"%MYSQL_PATH%" -u %DB_USER% %DB_NAME% < "%BACKUP_FILE%"

if %ERRORLEVEL% equ 0 (
    echo.
    echo [OK] Restore Berhasil!
) else (
    echo.
    echo [ERROR] Restore Gagal!
)

echo.
pause
