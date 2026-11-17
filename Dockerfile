# Apache + PHP con PDO MySQL. Railway manda PORT; el entrypoint hace que Apache escuche ahí.
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && { a2dismod -f autoindex || true; }

COPY docker/php.ini /usr/local/etc/php/conf.d/redemc.ini
COPY docker/apache.conf /etc/apache2/conf-available/redemc.conf
RUN a2enconf redemc

COPY docker/entrypoint.sh /usr/local/bin/redemc-entrypoint.sh
RUN chmod +x /usr/local/bin/redemc-entrypoint.sh

WORKDIR /var/www/html
COPY . /var/www/html

ENTRYPOINT ["redemc-entrypoint.sh"]
