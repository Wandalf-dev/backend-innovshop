# On utilise l'image PHP officielle
FROM php:8.2-apache

# 1. Installation et Nettoyage (Optimisé pour la RAM)
RUN apt-get update \
    && apt-get install -y git acl openssl openssh-client wget zip vim libpng-dev zlib1g-dev libzip-dev libxml2-dev libicu-dev \
    && docker-php-ext-install intl pdo pdo_mysql zip gd soap bcmath sockets \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2. Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configuration Apache
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf && a2enmod rewrite

WORKDIR /var/www/html

# Permissions initiales pour que www-data puisse écrire
RUN chown www-data:www-data /var/www/html

# On passe en utilisateur non-root pour la suite (Sécurité + Performance build)
USER www-data

# 3. Copie intelligente (avec bon propriétaire)
COPY --chown=www-data:www-data composer.json composer.lock ./

# 4. Installation des dépendances
RUN composer install --no-scripts --no-autoloader --no-dev

# 5. Copie du reste du code (avec bon propriétaire)
COPY --chown=www-data:www-data . .

# 6. Finalisation Composer
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Définition des variables d'environnement pour le build
ENV APP_ENV=prod
ENV APP_SECRET=build_placeholder_secret

# Création d'un fichier .env vide
RUN touch .env

# 7. Installation des Assets
RUN php bin/console assets:install public --no-interaction

# On repasse en root pour qu'Apache puisse binder le port 80
USER root
EXPOSE 80