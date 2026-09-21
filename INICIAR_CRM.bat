@echo off
chcp 65001 >nul
title CRM CONTABLE - Iniciador del Sistema
cls

echo ========================================================================
echo        🏢 BIENVENIDO A SU NUEVO CRM CONTABLE ("0 KILÓMETROS")
echo ========================================================================
echo.
echo  Iniciando los servicios del sistema contable...
echo.

set PHP_BIN=php
where php >nul 2>&1
if %errorlevel% neq 0 (
    for /d %%D in (C:\laragon\bin\php\php-8*) do (
        if exist "%%D\php.exe" set PHP_BIN=%%D\php.exe
    )
)

echo [OK] Verificando dependencias y cachés...
"%PHP_BIN%" artisan config:clear >nul 2>&1
"%PHP_BIN%" artisan route:clear >nul 2>&1

echo.
echo ========================================================================
echo  🔑 DATOS DE ACCESO DE FÁBRICA:
echo.
echo  URL del Sistema : http://localhost:8000 (o http://localhost/crm-contable)
echo  Usuario Admin   : admin@estudio.pe
echo  Contraseña      : admin123
echo ========================================================================
echo.
echo  Abriendo el navegador...
start http://localhost:8000/login

echo  Iniciando servidor de aplicaciones (presione Ctrl+C para detener)...
"%PHP_BIN%" artisan serve --port=8000
pause
