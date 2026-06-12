#!/bin/sh
set -e

# Hôte de la base : extrait de DB_HOST ou par défaut le nom du service compose "avis-db".
DB_HOST="${DB_HOST:-avis-db}"
DB_PORT="${DB_PORT:-3306}"

echo "[entrypoint] Attente de MariaDB sur ${DB_HOST}:${DB_PORT}..."
i=0
until php -r "exit(@fsockopen('${DB_HOST}', ${DB_PORT}) ? 0 : 1);" 2>/dev/null; do
    i=$((i+1))
    if [ "$i" -gt 30 ]; then
        echo "[entrypoint] MariaDB injoignable après 60s, abandon."
        exit 1
    fi
    sleep 2
done
echo "[entrypoint] MariaDB est prête."

# Vide le cache puis applique les migrations (la migration livrée est en SQL MariaDB).
php bin/console cache:clear --no-interaction || true
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[entrypoint] Démarrage du serveur sur 0.0.0.0:8000"
# router.php sert les assets statiques (CSS/JS Swagger) et délègue le reste à Symfony.
exec php -S 0.0.0.0:8000 -t public public/router.php
