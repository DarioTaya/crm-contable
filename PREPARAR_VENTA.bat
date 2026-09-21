@echo off
chcp 65001 >nul
title CRM CONTABLE - Preparador de Empaquetado ("0 Kilómetros")
cls

echo ========================================================================
echo        🏢 CRM CONTABLE — PREPARADOR DE EMPAQUETADO Y VENTA
echo                    (MODO "0 KILÓMETROS")
echo ========================================================================
echo.
echo  ⚠️  ATENCIÓN:
echo  Este proceso preparará el CRM para empaquetado y entrega a otro estudio.
echo.
echo  1. Creará un RESPALDO AUTOMÁTICO de tu base de datos actual en:
echo     storage\backups\
echo  2. Eliminará todos los clientes privados, claves SOL y comprobantes.
echo  3. Dejará el sistema limpio con usuario Administrador de fábrica:
echo     Usuario: admin@estudio.pe   /   Clave: admin123
echo  4. Limpiará trazas de logs, sesiones y cachés de WhatsApp.
echo.
echo ========================================================================
echo.

set /p RESPUESTA="¿Deseas iniciar la preparación del sistema '0 Kilómetros'? (S/N): "
if /i "%RESPUESTA%" neq "S" (
    echo.
    echo [INFO] Operación cancelada. Tus datos no han sido modificados.
    echo.
    pause
    exit /b 0
)

echo.
echo [1/3] Detectando entorno PHP...

set PHP_BIN=php
where php >nul 2>&1
if %errorlevel% neq 0 (
    for /d %%D in (C:\laragon\bin\php\php-8*) do (
        if exist "%%D\php.exe" set PHP_BIN=%%D\php.exe
    )
)

echo [OK] Usando PHP: %PHP_BIN%
echo.
echo [2/3] Ejecutando rutina de Factory Reset y empaquetado...
echo.

"%PHP_BIN%" artisan crm:factory-reset --force

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Hubo un problema durante el proceso. Revisa los mensajes arriba.
    echo.
    pause
    exit /b 1
)

echo.
echo [3/3] ¡Proceso completado exitosamente!
echo.
echo ========================================================================
echo  📦 LISTO PARA EMPAQUETAR Y DISTRIBUIR:
echo.
echo  1. Ya puedes comprimir esta carpeta completa en un archivo .ZIP
echo  2. Cuando desees volver a tus datos reales de trabajo, ejecuta:
echo     RESTAURAR_MI_ESTUDIO.bat
echo ========================================================================
echo.
pause
