# Full Docker environment rebuild script (PowerShell)
# This will destroy all data and rebuild from scratch

Write-Host "🔥 Full rebuild: stopping containers..." -ForegroundColor Yellow
docker-compose down -v

Write-Host "🗑️ Removing MySQL volume..." -ForegroundColor Yellow
docker volume rm liscase_mysql-data 2>$null

Write-Host "🚀 Starting fresh environment..." -ForegroundColor Green
docker-compose up -d

Write-Host "📋 Following logs (Ctrl+C to exit)..." -ForegroundColor Cyan
docker-compose logs -f


