#!/bin/sh

# Write Railway's environment variables into a .env file so PHP can read them
ENV_FILE="/var/www/html/.env"

echo "# Auto-generated from Railway environment variables" > $ENV_FILE

# Database credentials - support all Railway MySQL variable naming patterns
DB_HOST_VAL="${MYSQLHOST:-${MYSQL_HOST:-localhost}}"
DB_PORT_VAL="${MYSQLPORT:-${MYSQL_PORT:-3306}}"
DB_NAME_VAL="${MYSQLDATABASE:-${MYSQL_DATABASE:-lunar_store}}"
DB_USER_VAL="${MYSQLUSER:-${MYSQL_USER:-root}}"
DB_PASS_VAL="${MYSQLPASSWORD:-${MYSQL_PASSWORD:-}}"

echo "DB_HOST=${DB_HOST_VAL}" >> $ENV_FILE
echo "DB_PORT=${DB_PORT_VAL}" >> $ENV_FILE
echo "DB_NAME=${DB_NAME_VAL}" >> $ENV_FILE
echo "DB_USER=${DB_USER_VAL}" >> $ENV_FILE
echo "DB_PASSWORD=${DB_PASS_VAL}" >> $ENV_FILE

# App settings
echo "APP_ENV=${APP_ENV:-production}" >> $ENV_FILE
echo "APP_URL=${APP_URL:-}" >> $ENV_FILE
echo "CORS_ORIGIN=${CORS_ORIGIN:-*}" >> $ENV_FILE
echo "JWT_SECRET=${JWT_SECRET:-}" >> $ENV_FILE
echo "PAYSTACK_PUBLIC_KEY=${PAYSTACK_PUBLIC_KEY:-}" >> $ENV_FILE
echo "PAYSTACK_SECRET_KEY=${PAYSTACK_SECRET_KEY:-}" >> $ENV_FILE

echo "[entrypoint] .env written:"
cat $ENV_FILE

# Fix permissions
chown www-data:www-data $ENV_FILE
chmod 640 $ENV_FILE

# Disable conflicting MPMs
a2dismod mpm_event || true
a2dismod mpm_worker || true
a2enmod mpm_prefork || true

# Start Apache
exec apache2-foreground
