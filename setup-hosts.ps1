# PowerShell script to add localhost subdomains to hosts file
# Run as Administrator!

$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"
$entries = @(
    "127.0.0.1 localhost",
    "127.0.0.1 backend.localhost",
    "127.0.0.1 api.localhost"
)

Write-Host "🔧 Настройка hosts файла..." -ForegroundColor Cyan
Write-Host "   Файл: $hostsPath`n" -ForegroundColor Gray

# Проверяем права администратора
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "❌ Требуются права администратора!" -ForegroundColor Red
    Write-Host "   Запустите PowerShell от имени администратора" -ForegroundColor Yellow
    Write-Host "`nИли добавьте вручную в $hostsPath :`n" -ForegroundColor Yellow
    foreach ($entry in $entries) {
        Write-Host "   $entry" -ForegroundColor White
    }
    pause
    exit 1
}

# Читаем текущий hosts
$hostsContent = Get-Content $hostsPath -Raw

$added = 0
$skipped = 0

foreach ($entry in $entries) {
    if ($hostsContent -match [regex]::Escape($entry)) {
        Write-Host "✓ Уже существует: $entry" -ForegroundColor Gray
        $skipped++
    } else {
        Add-Content -Path $hostsPath -Value $entry -Encoding ASCII
        Write-Host "✅ Добавлено: $entry" -ForegroundColor Green
        $added++
    }
}

Write-Host "`n📊 Результат:" -ForegroundColor Cyan
Write-Host "   Добавлено: $added" -ForegroundColor Green
Write-Host "   Пропущено: $skipped" -ForegroundColor Gray

if ($added -gt 0) {
    Write-Host "`n✅ Hosts файл обновлен!" -ForegroundColor Green
    Write-Host "`n🌐 Доступные URL:" -ForegroundColor Cyan
    Write-Host "   Frontend: http://localhost:3025/" -ForegroundColor White
    Write-Host "   Backend:  http://backend.localhost:3025/" -ForegroundColor White
    Write-Host "   API:      http://api.localhost:3025/" -ForegroundColor White
} else {
    Write-Host "`nℹ️  Все записи уже существуют" -ForegroundColor Yellow
}

Write-Host ""
pause

