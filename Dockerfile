FROM php:8.2-apache

# Install system packages and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libpng-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql gd zip

# Enable Apache rewrite module for .htaccess
RUN a2enmod rewrite

# Disable conflicting MPMs for Railway
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2enmod mpm_prefork || true

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Set working directory
WORKDIR /var/www/html/

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Configure Apache port to use the dynamic $PORT environment variable
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# Fallback port
ENV PORT=80

# Start command with runtime MPM configuration to prevent crash on Railway
CMD ["sh", "-c", "a2dismod mpm_event || true; a2dismod mpm_worker || true; a2enmod mpm_prefork || true; apache2-foreground"]
