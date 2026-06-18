FROM reg-gitlab.aipacific.vn/devops2022/docker/nginx-php7.2:v7
# Add php test file
COPY . /src/public/
WORKDIR /src/public
RUN chown -R www-data:www-data /src/public
RUN composer install --ignore-platform-reqs
# Start Supervisord
COPY supervisord.conf /etc/supervisord.conf
COPY ./start.sh /start.sh
RUN chmod 755 /start.sh

EXPOSE 80 443

CMD ["/bin/bash", "/start.sh"]
