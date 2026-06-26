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

# Pass environment variables to Apache and PHP
RUN echo "PassEnv PORT DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD MYSQLHOST MYSQLPORT MYSQLDATABASE MYSQLUSER MYSQLPASSWORD APP_ENV APP_URL CORS_ORIGIN" >> /etc/apache2/apache2.conf

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

# Fallback port
ENV PORT=80

# Start command with runtime port replacement and MPM configuration to prevent crash and 502 on Railway
CMD ["sh", "-c", "sed -i \"s/Listen .*/Listen $PORT/g\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:.*/<VirtualHost *:$PORT>/g\" /etc/apache2/sites-available/000-default.conf && a2dismod mpm_event || true && a2dismod mpm_worker || true && a2enmod mpm_prefork || true && apache2-foreground"]
