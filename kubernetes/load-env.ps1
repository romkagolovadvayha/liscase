# Скрипт для загрузки переменных из .env файла в Kubernetes ConfigMap (PowerShell)

param(
    [string]$EnvFile = "",
    [string]$Namespace = "liscase",
    [string]$ConfigMapName = "liscase-config"
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($EnvFile)) {
    $EnvFile = Join-Path $PSScriptRoot "k8s.env"
}

if (-not (Test-Path $EnvFile)) {
    Write-Host "❌ Файл $EnvFile не найден!" -ForegroundColor Red
    Write-Host "Скопируйте $PSScriptRoot\k8s.env.example в $PSScriptRoot\k8s.env и заполните своими значениями:" -ForegroundColor Yellow
    Write-Host "  Copy-Item $PSScriptRoot\k8s.env.example $PSScriptRoot\k8s.env"
    exit 1
}

Write-Host "📝 Загрузка переменных из $EnvFile в ConfigMap..." -ForegroundColor Yellow

# Создаем namespace если не существует
kubectl create namespace $Namespace --dry-run=client -o yaml | kubectl apply -f -

# Удаляем старый ConfigMap
kubectl delete configmap $ConfigMapName -n $Namespace --ignore-not-found=true

# Создаем новый ConfigMap из env файла
kubectl create configmap $ConfigMapName `
  --from-env-file=$EnvFile `
  -n $Namespace

Write-Host "✅ ConfigMap создан успешно!" -ForegroundColor Green

# Показываем содержимое
Write-Host ""
Write-Host "📋 Текущие переменные в ConfigMap:" -ForegroundColor Cyan
kubectl get configmap $ConfigMapName -n $Namespace -o yaml

Write-Host ""
Write-Host "✅ Готово! Переменные загружены в Kubernetes." -ForegroundColor Green
Write-Host ""
Write-Host "💡 Для применения изменений перезапустите pod'ы:" -ForegroundColor Yellow
Write-Host "  kubectl rollout restart deployment/liscase-app -n $Namespace"


