FROM php:8.2-apache

# 1. Instalar dependencias del sistema y PHP
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-install pdo_mysql gd zip

# 2. Habilitar reescritura de URLs para Laravel
RUN a2enmod rewrite

# 3. Configurar la carpeta "public" de Laravel como la principal
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Copiar todo el código de tu CRM al contenedor
COPY . /var/www/html

# 6. Dar permisos a las carpetas que Laravel necesita para funcionar
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache