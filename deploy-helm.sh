#!/bin/bash

# LiSCase - One-Click Helm Deployment
# Простое развертывание через Helm Chart

set -e

GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}================================================${NC}"
echo -e "${GREEN}  LiSCase - One-Click Kubernetes Deployment${NC}"
echo -e "${BLUE}================================================${NC}"

# Конфигурация
NAMESPACE="${NAMESPACE:-liscase}"
RELEASE_NAME="${RELEASE_NAME:-liscase}"
REGISTRY="${DOCKER_REGISTRY:-your-registry.com}"
TAG="${IMAGE_TAG:-latest}"

echo -e "${BLUE}Configuration:${NC}"
echo "  Namespace: $NAMESPACE"
echo "  Release:   $RELEASE_NAME"
echo "  Registry:  $REGISTRY"
echo "  Tag:       $TAG"
echo ""

# Проверка Helm
if ! command -v helm &> /dev/null; then
    echo "❌ Helm not found. Installing..."
    curl https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash
fi

# Создание namespace
echo "📦 Creating namespace..."
kubectl create namespace $NAMESPACE --dry-run=client -o yaml | kubectl apply -f -

# Добавление Bitnami репозитория
echo "📚 Adding Helm repositories..."
helm repo add bitnami https://charts.bitnami.com/bitnami
helm repo update

# Обновление зависимостей
echo "🔄 Updating Helm dependencies..."
cd helm/liscase
helm dependency update
cd ../..

# Установка/Обновление через Helm
echo "🚀 Deploying LiSCase..."
helm upgrade --install $RELEASE_NAME helm/liscase \
  --namespace $NAMESPACE \
  --create-namespace \
  --set image.repository=$REGISTRY/liscase-app \
  --set image.tag=$TAG \
  --set nodeImage.repository=$REGISTRY/liscase-node \
  --set nodeImage.tag=$TAG \
  --wait \
  --timeout 10m

echo ""
echo -e "${GREEN}✅ Deployment completed!${NC}"
echo ""

# Вывод статуса
echo "📊 Deployment status:"
kubectl get all -n $NAMESPACE

echo ""
echo -e "${BLUE}🌐 Access URLs:${NC}"
echo "  Frontend: https://prostoj.store"
echo "  Backend:  https://backend.prostoj.store"
echo "  API:      https://api.prostoj.store"

echo ""
echo -e "${BLUE}📝 Useful commands:${NC}"
echo "  helm status $RELEASE_NAME -n $NAMESPACE"
echo "  kubectl logs -f -l app=liscase-app -n $NAMESPACE"
echo "  kubectl get pods -n $NAMESPACE"




