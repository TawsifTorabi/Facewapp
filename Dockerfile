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
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# =========================
# ENABLE APACHE REWRITE
# =========================
RUN a2enmod rewrite

# =========================
# SET DOCUMENT ROOT TO /public (IMPORTANT FIX)
# =========================
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# =========================
# WORKDIR
# =========================
WORKDIR /var/www/html

# =========================
# COPY APPLICATION
# =========================
COPY . /var/www/html

# =========================
# PERMISSIONS
# =========================
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage || true

# =========================
# EXPOSE APACHE
# =========================
EXPOSE 80