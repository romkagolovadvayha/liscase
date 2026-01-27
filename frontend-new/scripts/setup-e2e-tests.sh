#!/bin/bash

# Скрипт для установки зависимостей для E2E тестирования

echo "📦 Установка Playwright..."
npm install -D @playwright/test

echo "🔧 Установка браузеров для Playwright..."
npx playwright install

echo "✅ Установка завершена!"
echo ""
echo "Для запуска тестов используйте:"
echo "  npm run test:e2e           # Запуск всех тестов"
echo "  npm run test:e2e:ui        # Запуск в UI режиме"
echo "  npm run test:e2e:debug     # Запуск в режиме отладки"




