# Iniciar Maktub con Docker
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "   INICIANDO MAKTUB CON DOCKER" -ForegroundColor Cyan
Write-Host "========================================================`n" -ForegroundColor Cyan

# 1. Comprobar Docker
try {
    $null = docker info 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Docker daemon not running"
    }
    Write-Host "[1/5] Docker detectado y en ejecución." -ForegroundColor Green
} catch {
    Write-Host "[AVISO] Docker Desktop no se está ejecutando o no está instalado en el PATH." -ForegroundColor Yellow
    Write-Host "Asegúrate de haber instalado Docker Desktop y de tenerlo ABIERTO en segundo plano." -ForegroundColor Yellow
    Read-Host "Presiona Enter para salir..."
    exit 1
}

# 2. Archivo .env
if (-not (Test-Path ".env")) {
    Write-Host "[2/5] Creando archivo .env..." -ForegroundColor Yellow
    Copy-Item ".env.example" ".env"
} else {
    Write-Host "[2/5] Archivo .env listo." -ForegroundColor Green
}

# 3. Base de datos SQLite
if (-not (Test-Path "database\database.sqlite")) {
    Write-Host "[3/5] Creando database.sqlite..." -ForegroundColor Yellow
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
} else {
    Write-Host "[3/5] Base de datos SQLite lista." -ForegroundColor Green
}

# 4. Vendor / Composer
if (-not (Test-Path "vendor\autoload.php")) {
    Write-Host "[4/5] Instalando dependencias de PHP vía contenedor Composer..." -ForegroundColor Yellow
    docker run --rm -v "${PWD}:/app" -w /app composer:2 composer install --ignore-platform-reqs
} else {
    Write-Host "[4/5] Dependencias PHP ya instaladas." -ForegroundColor Green
}

# 5. Docker Compose
Write-Host "[5/5] Levantando contenedores Docker..." -ForegroundColor Green
docker compose up -d

Write-Host "`nEjecutando migraciones y seeders de la base de datos..." -ForegroundColor Cyan
docker compose exec laravel.test php artisan migrate --seed --force

Write-Host "`nCreando enlace de almacenamiento para imágenes..." -ForegroundColor Cyan
docker compose exec laravel.test php artisan storage:link

Write-Host "`n========================================================" -ForegroundColor Green
Write-Host "   ¡TODO LISTO! 🚀" -ForegroundColor Green
Write-Host "   La tienda y el cliente API están disponibles en: http://localhost:8080" -ForegroundColor Green
Write-Host "   Cliente de la API común: http://localhost:8080/cliente/index.html" -ForegroundColor Green
Write-Host "========================================================`n" -ForegroundColor Green

Start-Process "http://localhost:8080"
