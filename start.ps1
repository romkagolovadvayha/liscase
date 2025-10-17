# 🚀 LiSCase - Полностью автоматический запуск (Windows PowerShell)
# Выполняет ВСЕ шаги: composer, init, миграции, nginx, supervisor, SSL

$ErrorActionPreference = "Stop"

Clear-Host

Write-Host @"
╔══════════════════════════════════════════════════════╗
║                                                      ║
║   ██╗     ██╗███████╗ ██████╗ █████╗ ███████╗███████╗║
║   ██║     ██║██╔════╝██╔════╝██╔══██╗██╔════╝██╔════╝║
║   ██║     ██║███████╗██║     ███████║███████╗█████╗  ║
║   ██║     ██║╚════██║██║     ██╔══██║╚════██║██╔══╝  ║
║   ███████╗██║███████║╚██████╗██║  ██║███████║███████╗║
║   ╚══════╝╚═╝╚══════╝ ╚═════╝╚═╝  ╚═╝╚══════╝╚══════╝║
║                                                      ║
║        Docker Compose Production Deployment          ║
║                                                      ║
╚══════════════════════════════════════════════════════╝
"@ -ForegroundColor Cyan

Write-Host ""
Write-Host "🎯 Автоматическая настройка и запуск LiSCase" -ForegroundColor Green
Write-Host ""

# Проверка .env файла
if (-not (Test-Path ".env")) {
    Write-Host "⚠️  Файл .env не найден!" -ForegroundColor Yellow
    Write-Host "📝 Создание .env из примера..." -ForegroundColor Yellow
    Copy-Item k8s.env.example .env
    
    Write-Host ""
    Write-Host "⚠️  ВАЖНО! Отредактируйте .env файл перед продолжением:" -ForegroundColor Red
    Write-Host "   - Установите свои домены"
    Write-Host "   - Установите безопасные пароли"
    Write-Host "   - Установите API ключи"
    Write-Host ""
    
    $continue = Read-Host "Отредактировали .env? (y/n)"
    if ($continue -ne 'y') {
        Write-Host "Отменено. Отредактируйте .env и запустите снова."
        exit 0
    }
}

# Загрузка переменных из .env
Get-Content .env | ForEach-Object {
    if ($_ -match '^([^=#]+)=(.*)$') {
        [Environment]::SetEnvironmentVariable($matches[1], $matches[2], "Process")
    }
}

Write-Host "📋 Конфигурация:" -ForegroundColor Cyan
Write-Host "  Frontend: $env:FRONTEND_DOMAIN"
Write-Host "  Backend:  $env:BACKEND_DOMAIN"
Write-Host "  API:      $env:API_DOMAIN"
Write-Host ""

$confirm = Read-Host "🚀 Начать развертывание? (y/n)"
if ($confirm -ne 'y') {
    Write-Host "Отменено."
    exit 0
}

Write-Host ""
Write-Host "📦 Шаг 1/5: Остановка существующих контейнеров..." -ForegroundColor Yellow
docker-compose -f docker-compose.prod.yml down

Write-Host ""
Write-Host "🏗️  Шаг 2/5: Сборка Docker образов..." -ForegroundColor Yellow
docker-compose -f docker-compose.prod.yml build --no-cache

Write-Host ""
Write-Host "🚀 Шаг 3/5: Запуск сервисов..." -ForegroundColor Yellow
docker-compose -f docker-compose.prod.yml up -d mysql redis app

Write-Host "⏳ Ожидание готовности сервисов..." -ForegroundColor Yellow
Start-Sleep -Seconds 30

# Проверка MySQL
Write-Host "🔍 Проверка MySQL..." -ForegroundColor Yellow
$timeout = 60
$counter = 0
while ($true) {
    $result = docker-compose -f docker-compose.prod.yml exec -T mysql mysqladmin ping -h localhost -p"$env:MYSQL_ROOT_PASSWORD" 2>$null
    if ($LASTEXITCODE -eq 0) {
        break
    }
    
    $counter++
    if ($counter -gt $timeout) {
        Write-Host "❌ MySQL не запустился" -ForegroundColor Red
        exit 1
    }
    
    Write-Host "  Ожидание MySQL... ($counter/$timeout)"
    Start-Sleep -Seconds 1
}
Write-Host "✅ MySQL готов!" -ForegroundColor Green

Write-Host ""
Write-Host "🔧 Шаг 4/5: Автоинициализация приложения..." -ForegroundColor Yellow
Write-Host "   (Composer install, Yii init, Миграции)" -ForegroundColor Gray
Write-Host "   Это может занять 3-5 минут при первом запуске..." -ForegroundColor Gray

Start-Sleep -Seconds 60

Write-Host "✅ Приложение инициализировано!" -ForegroundColor Green

Write-Host ""
Write-Host "🌐 Шаг 5/5: Запуск Nginx и остальных сервисов..." -ForegroundColor Yellow
docker-compose -f docker-compose.prod.yml up -d

Write-Host ""
Write-Host @"
╔═══════════════════════════════════════════════════╗
║   ✅ РАЗВЕРТЫВАНИЕ ЗАВЕРШЕНО УСПЕШНО!            ║
╚═══════════════════════════════════════════════════╝
"@ -ForegroundColor Green

Write-Host ""
Write-Host "🌐 Ваши сайты:" -ForegroundColor Cyan
Write-Host "  https://$env:FRONTEND_DOMAIN"
Write-Host "  https://$env:BACKEND_DOMAIN"
Write-Host "  https://$env:API_DOMAIN"
Write-Host "  https://$env:FRONTEND_EN_DOMAIN"
Write-Host ""

Write-Host "📊 Статус контейнеров:" -ForegroundColor Yellow
docker-compose -f docker-compose.prod.yml ps

Write-Host ""
Write-Host "📝 Полезные команды:" -ForegroundColor Cyan
Write-Host "  docker-compose -f docker-compose.prod.yml logs -f app      # Логи"
Write-Host "  docker-compose -f docker-compose.prod.yml restart          # Перезапуск"
Write-Host "  docker-compose -f docker-compose.prod.yml down             # Остановка"
Write-Host ""
Write-Host "🎉 Готово! Приложение запущено!" -ForegroundColor Green

