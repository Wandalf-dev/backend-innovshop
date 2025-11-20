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

# 3. Copie intelligente (On copie d'abord juste les fichiers composer pour le cache)
COPY composer.json composer.lock ./

# 4. Installation des dépendances (Sans scripts pour éviter les erreurs, sans dev pour le poids)
RUN composer install --no-scripts --no-autoloader --no-dev

# 5. Copie du reste du code
COPY . .

# 6. Finalisation Composer
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Définition des variables d'environnement pour le build (Sécurité)
ENV APP_ENV=prod
ENV APP_SECRET=build_placeholder_secret

# Création d'un fichier .env vide pour éviter l'erreur de Dotenv
RUN touch .env

# 7. AJOUT CRUCIAL : Installation des Assets (CSS/JS pour EasyAdmin/API Platform)
# On le fait ici pour que les fichiers soient créés DANS l'image finale
RUN php bin/console assets:install public --no-interaction

# Permissions et Port
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80