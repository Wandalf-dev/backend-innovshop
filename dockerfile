# On utilise l'image PHP officielle
FROM php:8.2-apache

# 1. Installation des dépendances système + Pilotes (MySQL & Postgres)
RUN apt-get update \
    && apt-get install -y git acl openssl openssh-client wget zip vim libpng-dev zlib1g-dev libzip-dev libxml2-dev libicu-dev \
    && docker-php-ext-install intl pdo pdo_mysql pdo_pgsql zip gd soap bcmath sockets \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2. Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configuration Apache
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf && a2enmod rewrite

WORKDIR /var/www/html

# 3. Copie intelligente
COPY composer.json composer.lock ./

# 4. Installation des dépendances
RUN composer install --no-scripts --no-autoloader --no-dev

# 5. Copie du reste du code
COPY . .

# 6. Finalisation Composer
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ==========================================
# LA CORRECTION EST ICI (Lignes 33-36)
# ==========================================

# 7. On crée un fichier .env vide pour tromper Symfony
RUN touch .env

# 8. On lance l'installation des assets en forçant l'environnement PROD
# (Cela évite qu'il essaie de charger des trucs de debug)
RUN APP_ENV=prod DATABASE_URL="mysql://build:build@build:3306/build" APP_SECRET="build" php bin/console assets:install public --no-interaction

# Permissions et Port
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80