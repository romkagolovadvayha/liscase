#!/bin/bash

# Скрипт для загрузки переменных из .env файла в Kubernetes ConfigMap

set -e

ENV_FILE="${1:-k8s.env}"
NAMESPACE="${NAMESPACE:-liscase}"
CONFIGMAP_NAME="liscase-config"

if [ ! -f "$ENV_FILE" ]; then
    echo "❌ Файл $ENV_FILE не найден!"
    echo "Скопируйте k8s.env.example в k8s.env и заполните своими значениями:"
    echo "  cp k8s.env.example k8s.env"
    exit 1
fi

echo "📝 Загрузка переменных из $ENV_FILE в ConfigMap..."

# Создаем namespace если не существует
kubectl create namespace $NAMESPACE --dry-run=client -o yaml | kubectl apply -f -

# Удаляем старый ConfigMap если существует
kubectl delete configmap $CONFIGMAP_NAME -n $NAMESPACE --ignore-not-found=true

# Создаем новый ConfigMap из env файла
kubectl create configmap $CONFIGMAP_NAME \
  --from-env-file=$ENV_FILE \
  -n $NAMESPACE

echo "✅ ConfigMap создан успешно!"

# Показываем содержимое
echo ""
echo "📋 Текущие переменные в ConfigMap:"
kubectl get configmap $CONFIGMAP_NAME -n $NAMESPACE -o yaml

echo ""
echo "✅ Готово! Переменные загружены в Kubernetes."
echo ""
echo "💡 Для применения изменений перезапустите pod'ы:"
echo "  kubectl rollout restart deployment/liscase-app -n $NAMESPACE"



