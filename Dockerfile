FROM php:8.1-apache

ENV TZ=UTC
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Instalar todas las dependencias del sistema en una sola capa
# Nota: Algunos paquetes de seguridad pueden no estar disponibles en repos antiguos
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    g++ \
    git \
    libbz2-dev \
    libfreetype6-dev \
    libicu-dev \
    libjpeg-dev \
    libpng-dev \
    libreadline-dev \
    libxslt-dev \
    libzip-dev \
    zip \
    sudo \
    unzip \
    python3 \
    mariadb-client \
    vim \
 && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Instalar Xdebug compatible con PHP 8.1
RUN pecl install xdebug-3.2.0 \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=coverage,develop" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Configurar e instalar extensiones PHP
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd xsl zip pdo pdo_mysql

RUN echo "ServerName laravel-app.local" >> /etc/apache2/apache2.conf

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN a2enmod rewrite headers alias

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ARG uid

EXPOSE 80