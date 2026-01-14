# Скрипт для установки зависимостей для E2E тестирования (PowerShell)

Write-Host "📦 Установка Playwright..." -ForegroundColor Cyan
npm install -D @playwright/test

Write-Host "🔧 Установка браузеров для Playwright..." -ForegroundColor Cyan
npx playwright install

Write-Host "✅ Установка завершена!" -ForegroundColor Green
Write-Host ""
Write-Host "Для запуска тестов используйте:" -ForegroundColor Yellow
Write-Host "  npm run test:e2e           # Запуск всех тестов"
Write-Host "  npm run test:e2e:ui        # Запуск в UI режиме"
Write-Host "  npm run test:e2e:debug     # Запуск в режиме отладки"




