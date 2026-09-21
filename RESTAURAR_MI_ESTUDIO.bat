@echo off
chcp 65001 >nul
title CRM CONTABLE - Restaurador de Base de Datos
cls

echo ========================================================================
echo        📦 CRM CONTABLE — RESTAURADOR DE MI ESTUDIO
echo ========================================================================
echo.
echo  Este script te permite restaurar tu base de datos de trabajo
echo  a partir de las copias de seguridad guardadas en storage\backups\
echo.
echo ========================================================================
echo.

set PHP_BIN=php
where php >nul 2>&1
if %errorlevel% neq 0 (
    for /d %%D in (C:\laragon\bin\php\php-8*) do (
        if exist "%%D\php.exe" set PHP_BIN=%%D\php.exe
    )
)

"%PHP_BIN%" artisan crm:restore-backup

echo.
pause
