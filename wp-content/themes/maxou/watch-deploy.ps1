$path = "C:\Users\maxan\Desktop\Site Wlad\maxou-theme"
$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $path
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $true

$action = {
    Start-Sleep -Seconds 1
    Set-Location "C:\Users\maxan\Desktop\Site Wlad\maxou-theme"
    git add .
    $status = git status --porcelain
    if ($status) {
        git commit -m "auto-deploy $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
        git push production main
        Write-Host "Deploye a $(Get-Date -Format 'HH:mm:ss')" -ForegroundColor Green
    }
}

Register-ObjectEvent $watcher "Changed" -Action $action | Out-Null
Register-ObjectEvent $watcher "Created" -Action $action | Out-Null
Register-ObjectEvent $watcher "Deleted" -Action $action | Out-Null
Register-ObjectEvent $watcher "Renamed" -Action $action | Out-Null

Write-Host "Watcher actif sur le dossier - sauvegarde un fichier pour tester" -ForegroundColor Cyan
while ($true) { Start-Sleep -Seconds 1 }