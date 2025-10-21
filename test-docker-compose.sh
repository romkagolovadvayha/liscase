#!/bin/bash

# Скрипт для тестирования docker-compose.yml
# Проверяет совместимость с разными версиями docker-compose

echo "=========================================="
echo "  Docker Compose Compatibility Test"
echo "=========================================="
echo ""

# 1. Версия docker-compose
echo "1. Docker Compose Version:"
docker-compose --version || docker compose version
echo ""

# 2. Версия Docker
echo "2. Docker Version:"
docker --version
echo ""

# 3. Валидация конфигурации
echo "3. Validating docker-compose.yml..."
if docker-compose config --quiet 2>&1; then
    echo "   ✅ Syntax is valid"
else
    echo "   ❌ Syntax error detected:"
    docker-compose config 2>&1 | head -20
    exit 1
fi
echo ""

# 4. Проверка services
echo "4. Services defined:"
docker-compose config --services
echo ""

# 5. Проверка портов
echo "5. Ports configuration:"
docker-compose config | grep -A 5 "ports:" || echo "   No explicit ports (using expose)"
echo ""

# 6. Проверка переменных окружения
echo "6. Environment variables:"
if [ -f ".env" ]; then
    echo "   .env file found:"
    cat .env | grep -v "^#" | grep -v "^$"
else
    echo "   .env file NOT found (using defaults)"
fi
echo ""

# 7. Dry-run test
echo "7. Dry-run test (pull images):"
docker-compose pull 2>&1 | head -10
echo ""

echo "=========================================="
echo "  Test completed!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "  - If all tests pass, run: docker-compose up -d"
echo "  - If syntax error, check docker-compose.yml"
echo "  - If old docker-compose version, upgrade or simplify config"
echo ""

