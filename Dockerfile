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

# Copy Apache configuration files for dynamic port binding
COPY apache-ports.conf /etc/apache2/ports.conf
COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

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

# Copy and set up entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Start via entrypoint which writes .env from Railway env vars then starts Apache
CMD ["/entrypoint.sh"]
