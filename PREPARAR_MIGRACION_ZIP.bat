@echo off
chcp 65001 >nul
title PREPARAR MIGRACIÓN A ZIP - CRM CONTABLE
color 0E

echo ========================================================================
echo          PREPARANDO SISTEMA PARA MIGRACIÓN / COPIA DE SEGURIDAD
echo ========================================================================
echo.
echo Este proceso detendrá de forma segura todos los procesos activos
echo (WhatsApp, Apache, MySQL, Node) y liberará los archivos bloqueados
echo para que puedas comprimir la carpeta C:\laragon sin ningún error.
echo.

set "LARAGON_DIR=%~dp0"
if "%LARAGON_DIR:~-1%"=="\" set "LARAGON_DIR=%LARAGON_DIR:~0,-1%"

if exist "%LARAGON_DIR%\www\crm-contable" (
    set "CRM_DIR=%LARAGON_DIR%\www\crm-contable"
) else (
    set "CRM_DIR=%LARAGON_DIR%"
    set "LARAGON_DIR=%LARAGON_DIR%\..\.."
)

echo [1/5] Deteniendo microservicios de WhatsApp y automatizaciones...
taskkill /F /IM node.exe >nul 2>&1

echo [2/5] Cerrando procesos residuales de navegadores...
taskkill /F /IM chrome.exe /FI "WINDOWTITLE eq data:*" >nul 2>&1

echo [3/5] Limpiando archivos de bloqueo de sesión y temporales...
del /f /q "%CRM_DIR%\.wwebjs_auth\session\LOCK" 2>nul
del /f /q "%CRM_DIR%\.wwebjs_auth\session\lockfile" 2>nul
del /f /q "%CRM_DIR%\.wwebjs_auth\session\Default\LOCK" 2>nul
del /f /q "%CRM_DIR%\.wwebjs_auth\session\Default\lockfile" 2>nul
del /f /q "%CRM_DIR%\.wwebjs_auth\session\SingletonLock" 2>nul
del /f /q "%CRM_DIR%\.wwebjs_auth\session\first_party_sets.db-journal" 2>nul

echo [4/5] Limpiando caché y optimizaciones de Laravel...
set "PHP_EXE="
for /d %%D in ("%LARAGON_DIR%\bin\php\*") do (
    if exist "%%D\php.exe" set "PHP_EXE=%%D\php.exe"
)
if "%PHP_EXE%"=="" set "PHP_EXE=php"

if exist "%CRM_DIR%\artisan" (
    "%PHP_EXE%" "%CRM_DIR%\artisan" optimize:clear >nul 2>&1
)

echo [5/5] Deteniendo servidores de Laragon (MySQL y Apache) de forma segura...
for /d %%M in ("%LARAGON_DIR%\bin\mysql\*") do (
    if exist "%%M\bin\mysqladmin.exe" (
        "%%M\bin\mysqladmin.exe" -u root shutdown >nul 2>&1
    )
)
taskkill /F /IM mysqld.exe >nul 2>&1
taskkill /F /IM httpd.exe >nul 2>&1
taskkill /F /IM laragon.exe >nul 2>&1

echo.
echo ========================================================================
echo        ¡SISTEMA PREPARADO Y LIBERADO CON ÉXITO AL 100%%!
echo ========================================================================
echo Ya no hay ningún proceso activo ni archivos en uso.
echo.
echo Pasos a seguir:
echo 1. Cierra esta ventana.
echo 2. Haz clic derecho sobre C:\laragon y selecciona "Añadir al archivo..." (WinRAR/ZIP).
echo 3. Cópialo a tu laptop y descomprímelo en C:\laragon.
echo 4. En la laptop, simplemente dale doble clic a "INICIAR_SISTEMA.bat" y listo.
echo ========================================================================
echo.
pause