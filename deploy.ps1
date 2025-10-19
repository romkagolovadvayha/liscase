# LiSCase - One-Click Kubernetes Deployment (PowerShell)
# Простое развертывание одной командой

param(
    [string]$Namespace = "liscase",
    [string]$Registry = "your-registry.com",
    [string]$Tag = "latest"
)

$ErrorActionPreference = "Stop"

Write-Host "================================================" -ForegroundColor Blue
Write-Host "  LiSCase - Kubernetes Deployment" -ForegroundColor Green
Write-Host "================================================" -ForegroundColor Blue
Write-Host ""

Write-Host "Configuration:" -ForegroundColor Blue
Write-Host "  Namespace: $Namespace"
Write-Host "  Registry:  $Registry"
Write-Host "  Tag:       $Tag"
Write-Host ""

# Проверка kubectl
Write-Host "Checking kubectl..." -ForegroundColor Yellow
if (!(Get-Command kubectl -ErrorAction SilentlyContinue)) {
    Write-Host "kubectl not found! Please install kubectl." -ForegroundColor Red
    exit 1
}

# Проверка Helm
Write-Host "Checking Helm..." -ForegroundColor Yellow
if (!(Get-Command helm -ErrorAction SilentlyContinue)) {
    Write-Host "Helm not found! Installing Helm..." -ForegroundColor Yellow
    choco install kubernetes-helm -y
}

# Проверка Docker
Write-Host "Checking Docker..." -ForegroundColor Yellow
if (!(Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Host "Docker not found! Please install Docker." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Building Docker images..." -ForegroundColor Yellow

# Сборка образов
docker build -t "${Registry}/liscase-app:${Tag}" .
docker build -t "${Registry}/liscase-node:${Tag}" -f docker/node/Dockerfile .

Write-Host "Pushing images to registry..." -ForegroundColor Yellow
docker push "${Registry}/liscase-app:${Tag}"
docker push "${Registry}/liscase-node:${Tag}"

Write-Host ""
Write-Host "Deploying to Kubernetes..." -ForegroundColor Yellow

# Создание namespace
kubectl create namespace $Namespace --dry-run=client -o yaml | kubectl apply -f -

# Применение манифестов
kubectl apply -f kubernetes/secrets.yaml
kubectl apply -f kubernetes/configmap.yaml
kubectl apply -f kubernetes/mysql-deployment.yaml

Write-Host "Waiting for MySQL..." -ForegroundColor Yellow
kubectl wait --for=condition=ready pod -l app=mysql -n $Namespace --timeout=300s

kubectl apply -f kubernetes/app-deployment.yaml
kubectl apply -f kubernetes/websocket-deployment.yaml
kubectl apply -f kubernetes/node-deployment.yaml
kubectl apply -f kubernetes/ingress.yaml

Write-Host "Waiting for application..." -ForegroundColor Yellow
kubectl wait --for=condition=ready pod -l app=liscase-app -n $Namespace --timeout=300s

Write-Host ""
Write-Host "================================================" -ForegroundColor Blue
Write-Host "  Deployment completed successfully!" -ForegroundColor Green
Write-Host "================================================" -ForegroundColor Blue
Write-Host ""

Write-Host "Status:" -ForegroundColor Yellow
kubectl get pods -n $Namespace
kubectl get services -n $Namespace

Write-Host ""
Write-Host "Access URLs:" -ForegroundColor Blue
Write-Host "  Frontend: https://prostoj.store"
Write-Host "  Backend:  https://backend.prostoj.store"
Write-Host "  API:      https://api.prostoj.store"
Write-Host ""
Write-Host "Done!" -ForegroundColor Green



