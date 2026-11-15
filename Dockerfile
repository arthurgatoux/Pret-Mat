FROM php:8.5-apache

# Installation des paquets système nécessaires pour la compilation des extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libssl-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Installation des extensions pdo_mysql et ext-mongodb via PECL
RUN docker-php-ext-install pdo_mysql \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# Activation du module rewrite d'Apache
RUN a2enmod rewrite

# Configuration du DocumentRoot vers le dossier /var/www/html/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Autoriser les directives du fichier .htaccess
RUN echo '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>' > /etc/apache2/conf-available/override.conf \
    && a2enconf override

# Installation de Composer depuis l'image officielle
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 80
