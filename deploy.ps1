# Ajout de Git au PATH PowerShell si nécessaire
if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    $env:PATH = "C:\Program Files\Git\cmd;$env:PATH"
}

# 1. Build du CSS Tailwind en local
Write-Host "Compilation Tailwind..." -ForegroundColor Cyan
php bin/console tailwind:build --minify

# 2. Commit du CSS compilé si modifié
$changed = git status --porcelain var/tailwind/app.built.css
if ($changed) {
    git add var/tailwind/app.built.css
    git commit -m "build: compile Tailwind CSS"
}

# 3. Push sur main
Write-Host "Push sur main..." -ForegroundColor Cyan
git push origin main

# 4. Déploiement sur le serveur
Write-Host "Déploiement sur le serveur..." -ForegroundColor Cyan
ssh -p 65002 u359351136@147.93.88.127 "cd ~/domains/zeminal.tech/public_html && git pull origin main && chmod +x bin/console && COMPOSER_ALLOW_SUPERUSER=1 composer2 install --no-dev --optimize-autoloader && php bin/console doctrine:migrations:migrate --no-interaction --env=prod && php bin/console cache:clear --env=prod && php bin/console asset-map:compile"

Write-Host "Déploiement terminé." -ForegroundColor Green
