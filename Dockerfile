FROM docker-php-his-customer:latest

WORKDIR /app

# Copy toàn bộ project
COPY . .

# Install composer dependencies (nếu chưa install)
RUN if [ ! -d "vendor" ]; then composer install --no-dev --optimize-autoloader; fi

# Clear cache
RUN #php artisan config:cache && php artisan route:cache

# Set permissions
RUN chown -R www-data:www-data /app && chmod -R 755 /app

EXPOSE 9000

CMD ["php-fpm"]