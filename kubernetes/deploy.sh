#!/bin/bash

# LiSCase - Kubernetes One-Click Deployment Script
# Этот скрипт автоматически разворачивает весь проект в Kubernetes

set -e  # Остановка при ошибке

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd)"
cd "${REPO_ROOT}"

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Функция для вывода
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Конфигурация
NAMESPACE="liscase"
DOCKER_REGISTRY="${DOCKER_REGISTRY:-your-registry.com}"
IMAGE_TAG="${IMAGE_TAG:-latest}"

print_info "Starting LiSCase deployment to Kubernetes..."

# Проверка зависимостей
print_info "Checking dependencies..."

if ! command -v kubectl &> /dev/null; then
    print_error "kubectl not found. Please install kubectl first."
    exit 1
fi

if ! command -v docker &> /dev/null; then
    print_error "docker not found. Please install Docker first."
    exit 1
fi

print_success "All dependencies found!"

# Проверка подключения к Kubernetes
print_info "Checking Kubernetes connection..."
if ! kubectl cluster-info &> /dev/null; then
    print_error "Cannot connect to Kubernetes cluster. Please check your kubeconfig."
    exit 1
fi

print_success "Connected to Kubernetes cluster!"

# Сборка Docker образов
print_info "Building Docker images..."

# Сборка основного приложения
print_info "Building main application image..."
docker build -t ${DOCKER_REGISTRY}/liscase-app:${IMAGE_TAG} .
print_success "Main application image built!"

# Сборка Node.js сервисов
print_info "Building Node.js services image..."
docker build -t ${DOCKER_REGISTRY}/liscase-node:${IMAGE_TAG} -f docker/node/Dockerfile .
print_success "Node.js services image built!"

# Пуш образов в registry
print_info "Pushing images to registry..."
docker push ${DOCKER_REGISTRY}/liscase-app:${IMAGE_TAG}
docker push ${DOCKER_REGISTRY}/liscase-node:${IMAGE_TAG}
print_success "Images pushed to registry!"

# Создание namespace
print_info "Creating namespace..."
kubectl create namespace ${NAMESPACE} --dry-run=client -o yaml | kubectl apply -f -
print_success "Namespace created/updated!"

# Применение secrets
print_info "Applying secrets..."
if [ ! -f "kubernetes/secrets-production.yaml" ]; then
    print_warning "Production secrets not found. Using template secrets."
    print_warning "Please update kubernetes/secrets.yaml with real credentials!"
fi
kubectl apply -f kubernetes/secrets.yaml
print_success "Secrets applied!"

# Применение ConfigMap
print_info "Applying ConfigMap..."
kubectl apply -f kubernetes/configmap.yaml
print_success "ConfigMap applied!"

# Разворачивание MySQL
print_info "Deploying MySQL..."
kubectl apply -f kubernetes/mysql-deployment.yaml
print_success "MySQL deployed!"

# Ожидание готовности MySQL
print_info "Waiting for MySQL to be ready..."
kubectl wait --for=condition=ready pod -l app=mysql -n ${NAMESPACE} --timeout=300s
print_success "MySQL is ready!"

# Разворачивание основного приложения
print_info "Deploying main application..."
kubectl apply -f kubernetes/app-deployment.yaml
print_success "Main application deployed!"

# Разворачивание WebSocket сервера
print_info "Deploying WebSocket server..."
kubectl apply -f kubernetes/websocket-deployment.yaml
print_success "WebSocket server deployed!"

# Разворачивание Node.js сервисов
print_info "Deploying Node.js services..."
kubectl apply -f kubernetes/node-deployment.yaml
print_success "Node.js services deployed!"

# Применение Ingress
print_info "Applying Ingress..."
kubectl apply -f kubernetes/ingress.yaml
print_success "Ingress applied!"

# Ожидание готовности всех pod'ов
print_info "Waiting for all pods to be ready..."
kubectl wait --for=condition=ready pod -l app=liscase-app -n ${NAMESPACE} --timeout=300s
print_success "All pods are ready!"

# Вывод статуса
print_info "Deployment status:"
kubectl get pods -n ${NAMESPACE}

print_info "Services:"
kubectl get services -n ${NAMESPACE}

print_info "Ingress:"
kubectl get ingress -n ${NAMESPACE}

# Вывод URL для доступа
print_success "=========================================="
print_success "Deployment completed successfully!"
print_success "=========================================="
print_info "Frontend: https://prostoj.store"
print_info "Backend:  https://backend.prostoj.store"
print_info "API:      https://api.prostoj.store"
print_info "WebSocket: wss://ws.prostoj.store"
print_success "=========================================="

# Полезные команды
print_info "Useful commands:"
echo "  kubectl logs -f -l app=liscase-app -n ${NAMESPACE}  # View logs"
echo "  kubectl exec -it <pod-name> -n ${NAMESPACE} -- bash  # Access pod"
echo "  kubectl get all -n ${NAMESPACE}  # View all resources"
echo "  kubectl delete namespace ${NAMESPACE}  # Uninstall"

