@echo off
REM Script de backup para PostgreSQL en Windows
SETLOCAL

REM Configuración
set PG_PATH=C:\Program Files\PostgreSQL\9.4\bin
set BACKUP_DIR=\\cel310\COMPARTIDA COMPUTACION\BACKUPS CAPCEL
set DB_NAME=CAPCEL
set DB_USER=postgres
set DB_HOST=localhost
set FECHA=%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%
set FECHA=%FECHA: =0%

REM Crear directorio si no existe
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM Configurar password (alternativa: usar pgpass.conf)
set PGPASSWORD=123

REM Generar backup
echo Generando backup de %DB_NAME% - %date% %time%
"%PG_PATH%\pg_dump.exe" -h %DB_HOST% -U %DB_USER% -F c -b -v -f "%BACKUP_DIR%\%DB_NAME%_%FECHA%.backup" %DB_NAME%

if %errorlevel% equ 0 (
    echo Backup completado exitosamente: %DB_NAME%_%FECHA%.backup
) else (
    echo ERROR: Fallo en el backup
    exit /b 1
)

REM Limpiar backups antiguos (mas de 30 dias)
forfiles /p "%BACKUP_DIR%" /m "*.backup" /d -30 /c "cmd /c del @path"

echo Proceso de backup finalizado
ENDLOCAL