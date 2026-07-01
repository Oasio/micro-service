# Service Commande (Symfony + API Platform) — conteneurisation (cf. S8)
FROM php:8.3-cli

# Extensions PHP requises : intl (API Platform) + pdo_mysql (MariaDB) + zip (Composer)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev libzip-dev unzip git \
    && docker-php-ext-install intl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Composer (copié depuis l'image officielle)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 1) Dépendances d'abord (couche cachée tant que composer.json/.lock ne changent pas)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

# 2) Code applicatif
COPY . .
RUN composer dump-autoload --optimize \
    && chmod +x docker/entrypoint.sh

EXPOSE 8000

# L'entrypoint attend MariaDB, applique les migrations, puis lance le serveur.
ENTRYPOINT ["sh", "docker/entrypoint.sh"]
