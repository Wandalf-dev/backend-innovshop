# On utilise une image PHP officielle avec Apache
FROM php:8.2-apache

# On installe les extensions nécessaires pour Symfony (Zip, Intl, PDO, etc.)
RUN apt-get update \
    && apt-get install -y git acl openssl openssh-client wget zip vim libpng-dev zlib1g-dev libzip-dev libxml2-dev libicu-dev \
    && docker-php-ext-install intl pdo pdo_mysql zip gd soap bcmath sockets

# On installe Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# On configure Apache pour pointer vers le dossier /public de Symfony
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf && a2enmod rewrite

# On copie les fichiers du projet dans le conteneur
WORKDIR /var/www/html
COPY . .

# On installe les dépendances (sans les outils de dev pour gagner de la place)
RUN composer install --no-scripts --no-autoloader
RUN composer dump-autoload --optimize

# On donne les bonnes permissions
RUN chown -R www-data:www-data /var/www/html

# On expose le port 80
EXPOSE 80