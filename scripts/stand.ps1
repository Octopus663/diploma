# Керування стендом з PowerShell (Windows), якщо немає make.
# Приклади:
#   .\scripts\stand.ps1 up fpm
#   .\scripts\stand.ps1 down
#   .\scripts\stand.ps1 fresh
#   .\scripts\stand.ps1 ping
param(
    [Parameter(Mandatory = $true)][ValidateSet("up", "down", "migrate", "fresh", "test", "ping", "logs")]
    [string]$Command,
    [ValidateSet("fpm", "roadrunner", "swoole", "frankenphp")]
    [string]$Mode = "fpm"
)

switch ($Command) {
    "up"      { docker compose --profile $Mode up -d --build }
    "down"    { docker compose --profile "*" down --remove-orphans }
    "migrate" { docker compose run --rm app php artisan migrate --force }
    "fresh"   { docker compose run --rm app php artisan migrate:fresh --seed --force }
    "test"    { docker compose run --rm app php artisan test }
    "logs"    { docker compose --profile "*" logs -f --tail=100 }
    "ping" {
        foreach ($port in 8081, 8082, 8083, 8084) {
            try {
                $r = Invoke-WebRequest -UseBasicParsing -TimeoutSec 3 "http://localhost:$port/api/ping"
                Write-Host "${port}: $($r.Content)"
            } catch {
                Write-Host "${port}: недоступний"
            }
        }
    }
}
