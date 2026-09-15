FROM php:8.2-apache

# Install PHP extensions and required tools
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    unzip \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli pdo pdo_mysql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer.json first (this stays during build)
COPY composer.json .

# Install PHP dependencies (vendor will be built into image)
RUN composer install --no-dev --optimize-autoloader

# Copy project files (after composer install)
COPY src/ .

# Ensure proper permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
