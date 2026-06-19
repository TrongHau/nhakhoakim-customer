FROM testjt1/docker-php-his-customer:latest

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs || true

RUN chown -R www-data:www-data /app

EXPOSE 9000

CMD ["php-fpm"]