# ---- vendor: install dependency PHP (tanpa dev) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-scripts --prefer-dist \
    --optimize-autoloader --no-interaction --no-progress \
    --ignore-platform-req=ext-gd --ignore-platform-req=ext-zip \
    --ignore-platform-req=ext-intl --ignore-platform-req=ext-bcmath

# ---- runtime: PHP-FPM + Nginx + worker ----
# Catatan: harus PHP 8.4 agar cocok dengan dependency yang di-lock (butuh php >= 8.4.1).
FROM php:8.4-fpm-bookworm AS runtime

# Ekstensi yang dibutuhkan Laravel + phpword (zip/gd) + smalot/pdfparser (mbstring).
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor \
    libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring bcmath zip intl gd opcache pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Kode aplikasi + vendor hasil stage sebelumnya.
COPY --from=vendor /app /var/www/html

# Konfigurasi container.
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN rm -f /etc/nginx/sites-enabled/default \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8100
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
