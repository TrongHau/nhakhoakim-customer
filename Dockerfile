FROM testjt1/customer-22:latest

WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader
RUN chown -R www-data:www-data /app
EXPOSE 9000
CMD ["php-fpm"]