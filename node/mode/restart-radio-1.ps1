# Перезапуск Radio Station #1
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "Restarting Radio Station #1 on port 8081..." -ForegroundColor Cyan
Write-Host "============================================================`n" -ForegroundColor Cyan

# Убиваем процессы Node.js на порту 8081
Write-Host "Killing existing Node.js process on port 8081..." -ForegroundColor Yellow
$process = Get-NetTCPConnection -LocalPort 8081 -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess
if ($process) {
    Write-Host "Found process PID: $process" -ForegroundColor Green
    Stop-Process -Id $process -Force
    Write-Host "Process killed!" -ForegroundColor Green
} else {
    Write-Host "No process found on port 8081" -ForegroundColor Yellow
}

Write-Host "`nWaiting 2 seconds...`n" -ForegroundColor Yellow
Start-Sleep -Seconds 2

Write-Host "Starting Radio Station #1...`n" -ForegroundColor Green
node app.js 8081 "../../frontend/web/uploads/radio/1"

