#!/bin/bash
set -e

ssh -p 65002 u359351136@147.93.88.127 "
  cd ~/domains/zeminal.tech/public_html &&
  git pull origin main &&
  COMPOSER_ALLOW_SUPERUSER=1 composer2 install --no-dev --optimize-autoloader &&
  php bin/console doctrine:migrations:migrate --no-interaction --env=prod &&
  php bin/console cache:clear --env=prod &&
  php bin/console asset-map:compile
"

echo "Déploiement terminé."
