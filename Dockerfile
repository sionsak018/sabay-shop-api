FROM php:8.4-cli-alpine
# Trigger new deployment with PHP 8.4

# Install system dependencies
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    oniguruma-dev \
    curl \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# `php artisan serve` is single-threaded by default, so one slow request blocks
# every other user. Fork a small worker pool instead.
#
# --no-reload is mandatory here: without it Laravel refuses to spawn the pool
# and logs "Unable to respect the PHP_CLI_SERVER_WORKERS environment variable
# without the --no-reload flag. Only creating a single server."
ENV PHP_CLI_SERVER_WORKERS=4

# Set working directory
WORKDIR /app
COPY . .

# Install Laravel dependencies
RUN composer install --no-dev --optimize-autoloader

# Set PHP configuration for larger uploads
RUN echo "upload_max_filesize=20M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size=25M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Enable opcache. `artisan serve` runs on the CLI SAPI, where opcache is compiled
# in but switched off unless enable_cli is set. Without this every request
# recompiles the framework from source.
RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=1"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.validate_timestamps=1"; \
        echo "opcache.revalidate_freq=2"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Expose port (Render overrides or uses PORT env var)
EXPOSE 10000

# Caches are warmed at STARTUP, not build time. Render injects environment
# variables when the container starts, so running config:cache during the build
# would bake in a missing APP_KEY and DB_HOST.
#
# Each step is non-fatal: if a cache fails the service still boots, just slower.
# A failed `config:cache` here is fatal on its own - the app has no .env file and
# relies entirely on platform environment variables.
CMD php artisan config:cache || { echo "FATAL: config:cache failed - APP_KEY or config values may be missing"; exit 1; }; \
    php artisan route:cache || echo "WARN: route:cache failed, continuing without route cache"; \
    php artisan migrate --force -n || echo "WARN: migrate failed, continuing with existing schema"; \
    exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000} --no-reload
