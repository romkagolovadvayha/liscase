#!/bin/bash

# Скрипт для проверки статуса деплоя в Kubernetes
# Использование: ./check-deploy-status.sh [namespace]

NAMESPACE="${1:-liscase}"

# Цвета
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  LiSCase Deployment Status Check${NC}"
echo -e "${BLUE}  Namespace: ${NAMESPACE}${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# 1. Проверка pod'ов
echo -e "${YELLOW}📦 Pod Status:${NC}"
kubectl get pods -n ${NAMESPACE} --no-headers 2>/dev/null || echo -e "${RED}❌ Cannot get pods${NC}"
echo ""

# 2. Проверка deployments
echo -e "${YELLOW}🚀 Deployment Status:${NC}"
kubectl get deployments -n ${NAMESPACE} --no-headers 2>/dev/null || echo -e "${RED}❌ Cannot get deployments${NC}"
echo ""

# 3. Проверка services
echo -e "${YELLOW}🌐 Services:${NC}"
kubectl get services -n ${NAMESPACE} --no-headers 2>/dev/null || echo -e "${RED}❌ Cannot get services${NC}"
echo ""

# 4. Проверка ingress
echo -e "${YELLOW}🔗 Ingress:${NC}"
kubectl get ingress -n ${NAMESPACE} --no-headers 2>/dev/null || echo -e "${RED}❌ Cannot get ingress${NC}"
echo ""

# 5. Последние события
echo -e "${YELLOW}📋 Recent Events (last 10):${NC}"
kubectl get events -n ${NAMESPACE} --sort-by='.lastTimestamp' | tail -10 2>/dev/null || echo -e "${RED}❌ Cannot get events${NC}"
echo ""

# 6. Проверка initContainers
echo -e "${YELLOW}🔧 InitContainers Status:${NC}"
POD_NAME=$(kubectl get pod -l app=liscase-app -n ${NAMESPACE} -o jsonpath='{.items[0].metadata.name}' 2>/dev/null)

if [ -n "$POD_NAME" ]; then
    echo "Pod: $POD_NAME"
    echo ""
    
    # Логи каждого initContainer
    for container in wait-for-mysql run-migrations compile-scss update-settings create-admin; do
        echo -e "${BLUE}  ├─ ${container}:${NC}"
        STATUS=$(kubectl get pod $POD_NAME -n ${NAMESPACE} -o jsonpath="{.status.initContainerStatuses[?(@.name=='${container}')].state}" 2>/dev/null)
        
        if echo "$STATUS" | grep -q "terminated"; then
            echo -e "${GREEN}     ✅ Completed${NC}"
            # Последние 5 строк логов
            kubectl logs $POD_NAME -c ${container} -n ${NAMESPACE} --tail=5 2>/dev/null | sed 's/^/     │ /'
        elif echo "$STATUS" | grep -q "running"; then
            echo -e "${YELLOW}     ⏳ Running${NC}"
        elif echo "$STATUS" | grep -q "waiting"; then
            echo -e "${YELLOW}     ⏳ Waiting${NC}"
        else
            echo -e "${RED}     ❌ Error or not found${NC}"
            kubectl logs $POD_NAME -c ${container} -n ${NAMESPACE} --tail=10 2>/dev/null | sed 's/^/     │ /' || echo "     No logs"
        fi
        echo ""
    done
else
    echo -e "${RED}❌ No pod found with label app=liscase-app${NC}"
fi

# 7. Проверка основного контейнера
echo -e "${YELLOW}📱 Main Container Logs (last 20 lines):${NC}"
if [ -n "$POD_NAME" ]; then
    kubectl logs $POD_NAME -n ${NAMESPACE} --tail=20 2>/dev/null || echo -e "${RED}❌ Cannot get logs${NC}"
else
    echo -e "${RED}❌ No pod available${NC}"
fi
echo ""

# 8. Health check
echo -e "${YELLOW}🏥 Health Check:${NC}"
if [ -n "$POD_NAME" ]; then
    kubectl exec -n ${NAMESPACE} $POD_NAME -- php -v 2>/dev/null && echo -e "${GREEN}✅ PHP is working${NC}" || echo -e "${RED}❌ PHP check failed${NC}"
    kubectl exec -n ${NAMESPACE} $POD_NAME -- php yii --version 2>/dev/null && echo -e "${GREEN}✅ Yii is working${NC}" || echo -e "${RED}❌ Yii check failed${NC}"
fi
echo ""

# 9. Проверка доступности сервисов
echo -e "${YELLOW}🌍 External Access Check:${NC}"
INGRESS_IP=$(kubectl get ingress -n ${NAMESPACE} -o jsonpath='{.items[0].status.loadBalancer.ingress[0].ip}' 2>/dev/null)
if [ -n "$INGRESS_IP" ]; then
    echo "Ingress IP: $INGRESS_IP"
    curl -s -o /dev/null -w "  Frontend: %{http_code}\n" http://$INGRESS_IP/ 2>/dev/null || echo "  Frontend: Connection failed"
else
    echo -e "${YELLOW}⏳ Waiting for external IP${NC}"
fi
echo ""

echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}Status check completed!${NC}"
echo -e "${BLUE}========================================${NC}"

