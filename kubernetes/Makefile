.PHONY: help deploy deploy-k8s build push clean logs status shell

# Конфигурация
NAMESPACE ?= liscase
REGISTRY ?= your-registry.com
TAG ?= latest
APP_IMAGE = $(REGISTRY)/liscase-app:$(TAG)
NODE_IMAGE = $(REGISTRY)/liscase-node:$(TAG)
KUBERNETES_DIR := $(dir $(abspath $(lastword $(MAKEFILE_LIST))))
REPO_ROOT := $(abspath $(KUBERNETES_DIR)/..)

help: ## Показать помощь
	@echo "LiSCase - Kubernetes Deployment Commands"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

deploy: deploy-k8s ## Развернуть приложение через kubectl

deploy-k8s: ## Развернуть через kubectl
	@echo "Deploying via kubectl..."
	$(KUBERNETES_DIR)/deploy.sh

build: ## Собрать Docker образы
	@echo "Building images..."
	docker build -t $(APP_IMAGE) $(REPO_ROOT)
	docker build -t $(NODE_IMAGE) -f $(REPO_ROOT)/docker/node/Dockerfile $(REPO_ROOT)
	@echo "✅ Images built!"

push: build ## Собрать и отправить образы в registry
	@echo "Pushing images..."
	docker push $(APP_IMAGE)
	docker push $(NODE_IMAGE)
	@echo "✅ Images pushed!"

test-local: ## Тестировать локально с Docker Compose
	docker compose -f $(KUBERNETES_DIR)/docker-compose.test.yml up

clean: ## Удалить развертывание
	@echo "Cleaning up..."
	kubectl delete namespace $(NAMESPACE) || true
	@echo "✅ Cleaned!"

logs: ## Показать логи приложения
	kubectl logs -f -l app=liscase-app -n $(NAMESPACE)

status: ## Показать статус развертывания
	@echo "Pods:"
	@kubectl get pods -n $(NAMESPACE)
	@echo ""
	@echo "Services:"
	@kubectl get services -n $(NAMESPACE)
	@echo ""
	@echo "Ingress:"
	@kubectl get ingress -n $(NAMESPACE)

shell: ## Открыть shell в pod
	@POD=$$(kubectl get pod -n $(NAMESPACE) -l app=liscase-app -o jsonpath='{.items[0].metadata.name}'); \
	kubectl exec -it $$POD -n $(NAMESPACE) -- bash

migrate: ## Запустить миграции
	@POD=$$(kubectl get pod -n $(NAMESPACE) -l app=liscase-app -o jsonpath='{.items[0].metadata.name}'); \
	kubectl exec $$POD -n $(NAMESPACE) -- php yii migrate --interactive=0

restart: ## Перезапустить приложение
	kubectl rollout restart deployment/liscase-app -n $(NAMESPACE)

scale: ## Масштабировать (использование: make scale REPLICAS=5)
	kubectl scale deployment liscase-app --replicas=$(REPLICAS) -n $(NAMESPACE)

update: push ## Обновить развертывание с новым образом
	kubectl rollout restart deployment/liscase-app -n $(NAMESPACE)
	kubectl rollout status deployment/liscase-app -n $(NAMESPACE)

health: ## Проверить здоровье сервисов
	@echo "Health check..."
	@curl -f https://prostoj.store/health || echo "Frontend: ❌"
	@curl -f https://backend.prostoj.store/health || echo "Backend: ❌"
	@curl -f https://api.prostoj.store/health || echo "API: ❌"

backup-db: ## Создать backup базы данных
	@POD=$$(kubectl get pod -n $(NAMESPACE) -l app=mysql -o jsonpath='{.items[0].metadata.name}'); \
	kubectl exec $$POD -n $(NAMESPACE) -- mysqldump -u root -p$$MYSQL_ROOT_PASSWORD prostoj4 > backup-$$(date +%Y%m%d-%H%M%S).sql

watch: ## Наблюдать за pod'ами в реальном времени
	watch kubectl get pods -n $(NAMESPACE)


