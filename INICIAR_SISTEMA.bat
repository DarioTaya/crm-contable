@echo off
chcp 65001 >nul
title INICIAR SISTEMA - CRM CONTABLE
color 0A

echo ========================================================================
echo               INICIANDO SISTEMA INTEGRAL - CRM CONTABLE
echo ========================================================================
echo.

set "LARAGON_DIR=%~dp0"
if "%LARAGON_DIR:~-1%"=="\" set "LARAGON_DIR=%LARAGON_DIR:~0,-1%"

if exist "%LARAGON_DIR%\www\crm-contable" (
    set "CRM_DIR=%LARAGON_DIR%\www\crm-contable"
) else (
    set "CRM_DIR=%LARAGON_DIR%"
    set "LARAGON_DIR=%LARAGON_DIR%\..\.."
)

echo [1/4] Verificando y limpiando bloqueos temporales...
del /f /q "%CRM_DIR%\.wwebjs_auth\session\LOCK" 2>nul
del /f /q "%CRM_DIR%\.wwebjs_auth\session\lockfile" 2>nul
del /f /q "%CRM_DIR%\.wwebjs_auth\session\Default\LOCK" 2>nul
del /f /q "%CRM_DIR%\.wwebjs_auth\session\Default\lockfile" 2>nul
del /f /q "%CRM_DIR%\.wwebjs_auth\session\SingletonLock" 2>nul

echo [2/4] Iniciando Laragon (Servidores Apache y MySQL)...
tasklist /FI "IMAGENAME eq laragon.exe" 2>nul | find /i "laragon.exe" >nul
if errorlevel 1 (
    if exist "%LARAGON_DIR%\laragon.exe" (
        start "" "%LARAGON_DIR%\laragon.exe"
    )
)

ping 127.0.0.1 -n 4 >nul

echo [3/4] Iniciando Microservicio de WhatsApp en segundo plano...
set "NODE_EXE="
for /d %%D in ("%LARAGON_DIR%\bin\nodejs\*") do (
    if exist "%%D\node.exe" set "NODE_EXE=%%D\node.exe"
)
if "%NODE_EXE%"=="" (
    if exist "C:\Program Files\nodejs\node.exe" set "NODE_EXE=C:\Program Files\nodejs\node.exe"
)
if "%NODE_EXE%"=="" (
    if exist "C:\Program Files (x86)\nodejs\node.exe" set "NODE_EXE=C:\Program Files (x86)\nodejs\node.exe"
)
if "%NODE_EXE%"=="" set "NODE_EXE=node"

if exist "%CRM_DIR%\scripts\run_hidden.vbs" (
    wscript.exe //B "%CRM_DIR%\scripts\run_hidden.vbs" "%CRM_DIR%" "--log-file=%CRM_DIR%\storage\logs\whatsapp_server.log" "%NODE_EXE%" "%CRM_DIR%\scripts\whatsapp_server.cjs"
)

echo [4/4] Abriendo CRM Contable en el navegador...
start "" "http://crm-contable.test" 2>nul
if errorlevel 1 (
    start "" "http://localhost/crm-contable/public"
)

echo.
echo ========================================================================
echo   ¡TODO LISTO!
echo   - Laragon y Base de Datos: ACTIVOS
echo   - WhatsApp Microservicio: ACTIVO EN SEGUNDO PLANO
echo   - CRM Contable: ABIERTO EN EL NAVEGADOR
echo ========================================================================
echo Esta ventana se cerrará automáticamente en 4 segundos...
ping 127.0.0.1 -n 5 >nul
exit /b 0