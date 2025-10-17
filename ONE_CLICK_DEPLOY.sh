#!/bin/bash

# 🚀 LiSCase - ONE CLICK KUBERNETES DEPLOYMENT
# Просто запустите: ./ONE_CLICK_DEPLOY.sh

clear
cat << "EOF"
╔══════════════════════════════════════════════════════╗
║                                                      ║
║   ██╗     ██╗███████╗ ██████╗ █████╗ ███████╗███████╗║
║   ██║     ██║██╔════╝██╔════╝██╔══██╗██╔════╝██╔════╝║
║   ██║     ██║███████╗██║     ███████║███████╗█████╗  ║
║   ██║     ██║╚════██║██║     ██╔══██║╚════██║██╔══╝  ║
║   ███████╗██║███████║╚██████╗██║  ██║███████║███████╗║
║   ╚══════╝╚═╝╚══════╝ ╚═════╝╚═╝  ╚═╝╚══════╝╚══════╝║
║                                                      ║
║        Kubernetes One-Click Deployment              ║
║                                                      ║
╚══════════════════════════════════════════════════════╝
EOF

echo ""
echo "🎯 Начинаем автоматическое развертывание..."
echo ""

# Проверка зависимостей
check_dependency() {
    if ! command -v $1 &> /dev/null; then
        echo "❌ $1 не найден!"
        return 1
    fi
    echo "✅ $1 найден"
    return 0
}

echo "Проверка зависимостей..."
check_dependency kubectl || exit 1
check_dependency helm || exit 1
check_dependency docker || exit 1

echo ""
echo "📝 Настройка конфигурации..."

# Автоопределение registry
REGISTRY="${DOCKER_REGISTRY:-ghcr.io/$(git config user.name 2>/dev/null || echo 'your-org')}"
NAMESPACE="liscase"
RELEASE="liscase"

echo "  Registry: $REGISTRY"
echo "  Namespace: $NAMESPACE"
echo ""

# Запрос подтверждения
read -p "🚀 Начать развертывание? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Отменено."
    exit 0
fi

echo ""
echo "🔨 Сборка Docker образов..."
docker build -t ${REGISTRY}/liscase-app:latest . || {
    echo "❌ Ошибка сборки образа приложения"
    exit 1
}

echo ""
echo "📦 Развертывание через Helm..."
helm repo add bitnami https://charts.bitnami.com/bitnami 2>/dev/null || true
helm repo update

cd helm/liscase
helm dependency update
cd ../..

helm upgrade --install $RELEASE helm/liscase \
  --namespace $NAMESPACE \
  --create-namespace \
  --set image.repository=$REGISTRY/liscase-app \
  --set image.tag=latest \
  --wait \
  --timeout 10m || {
    echo "❌ Ошибка развертывания"
    exit 1
}

echo ""
echo "⏳ Ожидание готовности pod'ов..."
kubectl wait --for=condition=ready pod -l app.kubernetes.io/name=liscase -n $NAMESPACE --timeout=300s || true

echo ""
cat << "EOF"
╔══════════════════════════════════════════════════════╗
║                                                      ║
║              ✅ РАЗВЕРТЫВАНИЕ ЗАВЕРШЕНО!             ║
║                                                      ║
╚══════════════════════════════════════════════════════╝
EOF

echo ""
echo "🌐 Доступ к приложению:"
echo ""
INGRESS_IP=$(kubectl get ingress -n $NAMESPACE -o jsonpath='{.items[0].status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "pending")
echo "  Frontend: https://prostoj.store"
echo "  Backend:  https://backend.prostoj.store"
echo "  API:      https://api.prostoj.store"
echo "  Ingress IP: $INGRESS_IP"
echo ""

echo "📊 Статус:"
kubectl get pods -n $NAMESPACE

echo ""
echo "📝 Полезные команды:"
echo "  kubectl get all -n $NAMESPACE          # Все ресурсы"
echo "  kubectl logs -f -l app=liscase-app -n $NAMESPACE  # Логи"
echo "  helm status $RELEASE -n $NAMESPACE     # Статус Helm"
echo "  helm uninstall $RELEASE -n $NAMESPACE  # Удалить"
echo ""
echo "🎉 Готово! Приложение развернуто в Kubernetes!"

