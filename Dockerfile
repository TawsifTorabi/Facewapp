FROM php:8.2-apache

# =========================
# SYSTEM DEPENDENCIES
# =========================
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# =========================
# ENABLE APACHE REWRITE
# =========================
RUN a2enmod rewrite

# =========================
# DOCUMENT ROOT
# =========================
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# =========================
# WORKDIR
# =========================
WORKDIR /var/www/html

# =========================
# COPY APP FIRST
# =========================
COPY . /var/www/html

# =========================
# CREATE STORAGE + FIX PERMS (AFTER COPY)
# =========================
RUN mkdir -p \
    /var/www/html/storage/tmp_uploads \
    /var/www/html/storage/results \
    /var/www/html/storage/uploads \
    /var/www/html/storage/zips \
 && chown -R www-data:www-data /var/www/html/storage \
 && chmod -R 775 /var/www/html/storage

# =========================
# OPTIONAL: RUN AS WWW-DATA
# =========================
# USER www-data

# =========================
# EXPOSE
# =========================
EXPOSE 80