#!/bin/bash
# Script to add localhost subdomains to hosts file
# Run with sudo!

HOSTS_PATH="/etc/hosts"
ENTRIES=(
    "127.0.0.1 localhost"
    "127.0.0.1 backend.localhost"
    "127.0.0.1 api.localhost"
)

echo "🔧 Настройка hosts файла..."
echo "   Файл: $HOSTS_PATH"
echo ""

# Проверяем права root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Требуются права root!"
    echo "   Запустите: sudo ./setup-hosts.sh"
    echo ""
    echo "Или добавьте вручную в $HOSTS_PATH :"
    echo ""
    for entry in "${ENTRIES[@]}"; do
        echo "   $entry"
    done
    echo ""
    exit 1
fi

ADDED=0
SKIPPED=0

for entry in "${ENTRIES[@]}"; do
    if grep -Fxq "$entry" "$HOSTS_PATH"; then
        echo "✓ Уже существует: $entry"
        ((SKIPPED++))
    else
        echo "$entry" >> "$HOSTS_PATH"
        echo "✅ Добавлено: $entry"
        ((ADDED++))
    fi
done

echo ""
echo "📊 Результат:"
echo "   Добавлено: $ADDED"
echo "   Пропущено: $SKIPPED"
echo ""

if [ $ADDED -gt 0 ]; then
    echo "✅ Hosts файл обновлен!"
    echo ""
    echo "🌐 Доступные URL:"
    echo "   Frontend: http://localhost:3025/"
    echo "   Backend:  http://backend.localhost:3025/"
    echo "   API:      http://api.localhost:3025/"
else
    echo "ℹ️  Все записи уже существуют"
fi

echo ""

