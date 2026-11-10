@echo off
chcp 65001 >nul
echo ========================================================
echo   INICIANDO MAKTUB CON DOCKER
echo ========================================================
echo.

:: 1. Comprobar si Docker está instalado y activo
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo [AVISO] Docker no se está ejecutando o no está disponible en la terminal.
    echo Asegúrate de:
    echo 1. Haber completado la instalación de Docker Desktop.
    echo 2. Abrir 'Docker Desktop' desde el menú Inicio.
    echo 3. Esperar unos segundos a que el motor arranque (icono en verde).
    echo.
    pause
    exit /b 1
)

echo [1/5] Docker detectado y funcionando correctamente.

:: 2. Asegurar que existe .env
if not exist ".env" (
    echo [2/5] Creando archivo .env...
    copy .env.example .env >nul
) else (
    echo [2/5] Archivo .env verificado.
)

:: 3. Asegurar que existe la base de datos SQLite
if not exist "database\database.sqlite" (
    echo [3/5] Creando base de datos SQLite...
    type nul > "database\database.sqlite"
) else (
    echo [3/5] Base de datos SQLite lista.
)

:: 4. Comprobar dependencias de Composer
if not exist "vendor\autoload.php" (
    echo [4/5] Instalando dependencias de PHP vía contenedor Composer...
    docker run --rm -v "%cd%:/app" -w /app composer:2 composer install --ignore-platform-reqs
) else (
    echo [4/5] Dependencias PHP ya instaladas.
)

:: 5. Levantar Docker Compose
echo [5/5] Levantando contenedores de Docker...
docker compose up -d

echo.
echo Configurando base de datos (migraciones + productos de prueba)...
docker compose exec laravel.test php artisan migrate --seed --force

echo.
echo Creando enlace para las imágenes de productos...
docker compose exec laravel.test php artisan storage:link

echo.
echo ========================================================
echo   ¡TODO LISTO! 🚀
echo   Puedes acceder a la tienda en: http://localhost:8080
echo   Cliente de la API común: http://localhost:8080/cliente/index.html
echo ========================================================
echo.
start http://localhost:8080
pause
