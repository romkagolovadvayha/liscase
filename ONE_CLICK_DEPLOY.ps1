# 🚀 LiSCase - ONE CLICK KUBERNETES DEPLOYMENT (Windows)
# Просто запустите: .\ONE_CLICK_DEPLOY.ps1

$ErrorActionPreference = "Stop"

Clear-Host

Write-Host @"
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
"@ -ForegroundColor Cyan

Write-Host ""
Write-Host "🎯 Начинаем автоматическое развертывание..." -ForegroundColor Green
Write-Host ""

# Конфигурация
$NAMESPACE = "liscase"
$RELEASE = "liscase"
$REGISTRY = if ($env:DOCKER_REGISTRY) { $env:DOCKER_REGISTRY } else { "your-registry.com" }
$TAG = if ($env:IMAGE_TAG) { $env:IMAGE_TAG } else { "latest" }

Write-Host "📝 Конфигурация:" -ForegroundColor Yellow
Write-Host "  Registry:  $REGISTRY"
Write-Host "  Namespace: $NAMESPACE"
Write-Host "  Tag:       $TAG"
Write-Host ""

# Проверка зависимостей
Write-Host "Проверка зависимостей..." -ForegroundColor Yellow

function Check-Command {
    param($CommandName)
    
    if (Get-Command $CommandName -ErrorAction SilentlyContinue) {
        Write-Host "✅ $CommandName найден" -ForegroundColor Green
        return $true
    } else {
        Write-Host "❌ $CommandName не найден!" -ForegroundColor Red
        return $false
    }
}

$allDepsOk = $true
$allDepsOk = (Check-Command kubectl) -and $allDepsOk
$allDepsOk = (Check-Command helm) -and $allDepsOk
$allDepsOk = (Check-Command docker) -and $allDepsOk

if (-not $allDepsOk) {
    Write-Host ""
    Write-Host "❌ Не все зависимости установлены!" -ForegroundColor Red
    Write-Host "Установите через Chocolatey:" -ForegroundColor Yellow
    Write-Host "  choco install kubernetes-cli kubernetes-helm docker-desktop -y"
    exit 1
}

Write-Host ""
Write-Host "Проверка подключения к Kubernetes..." -ForegroundColor Yellow
try {
    kubectl cluster-info | Out-Null
    Write-Host "✅ Подключение к Kubernetes успешно" -ForegroundColor Green
} catch {
    Write-Host "❌ Не удалось подключиться к Kubernetes!" -ForegroundColor Red
    exit 1
}

Write-Host ""
$confirm = Read-Host "🚀 Начать развертывание? (y/n)"
if ($confirm -ne 'y') {
    Write-Host "Отменено."
    exit 0
}

Write-Host ""
Write-Host "🔨 Сборка Docker образов..." -ForegroundColor Yellow

try {
    docker build -t "${REGISTRY}/liscase-app:${TAG}" .
    Write-Host "✅ Образ приложения собран" -ForegroundColor Green
} catch {
    Write-Host "❌ Ошибка сборки образа" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "📦 Развертывание через Helm..." -ForegroundColor Yellow

# Добавление репозиториев
helm repo add bitnami https://charts.bitnami.com/bitnami 2>$null
helm repo update

# Обновление зависимостей
Set-Location helm\liscase
helm dependency update
Set-Location ..\..

# Развертывание
helm upgrade --install $RELEASE helm/liscase `
  --namespace $NAMESPACE `
  --create-namespace `
  --set image.repository=$REGISTRY/liscase-app `
  --set image.tag=$TAG `
  --wait `
  --timeout 10m

if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Ошибка развертывания" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host @"
╔══════════════════════════════════════════════════════╗
║                                                      ║
║              ✅ РАЗВЕРТЫВАНИЕ ЗАВЕРШЕНО!             ║
║                                                      ║
╚══════════════════════════════════════════════════════╝
"@ -ForegroundColor Green

Write-Host ""
Write-Host "🌐 Доступ к приложению:" -ForegroundColor Cyan
Write-Host "  Frontend: https://prostoj.store"
Write-Host "  Backend:  https://backend.prostoj.store"
Write-Host "  API:      https://api.prostoj.store"
Write-Host ""

Write-Host "📊 Статус развертывания:" -ForegroundColor Yellow
kubectl get pods -n $NAMESPACE

Write-Host ""
Write-Host "📝 Полезные команды:" -ForegroundColor Yellow
Write-Host "  kubectl get all -n $NAMESPACE"
Write-Host "  kubectl logs -f -l app=liscase-app -n $NAMESPACE"
Write-Host "  helm status $RELEASE -n $NAMESPACE"
Write-Host ""
Write-Host "🎉 Готово!" -ForegroundColor Green




