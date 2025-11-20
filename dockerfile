# On utilise l'image PHP officielle
FROM php:8.2-apache

# 1. Installation des dépendances système + NETTOYAGE IMMÉDIAT (Pour gagner de la place)
RUN apt-get update \
    && apt-get install -y git acl openssl openssh-client wget zip vim libpng-dev zlib1g-dev libzip-dev libxml2-dev libicu-dev \
    && docker-php-ext-install intl pdo pdo_mysql zip gd soap bcmath sockets \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2. Installation de Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configuration Apache
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf && a2enmod rewrite

WORKDIR /var/www/html

# 3. "Smart Copy" : On copie d'abord juste les fichiers de définition Composer
# Cela permet au Docker de mettre en cache les vendors si le fichier composer.json n'a pas changé
COPY composer.json composer.lock ./

# 4. Installation des dépendances (Sans scripts pour l'instant pour éviter les erreurs de cache)
RUN composer install --no-scripts --no-autoloader --no-dev

# 5. Copie du reste du code source
COPY . .

# 6. Finalisation de Composer
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Permissions et Port
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80