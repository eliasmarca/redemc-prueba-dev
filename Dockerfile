# Apache + PHP con PDO MySQL. Railway manda PORT; el entrypoint hace que Apache escuche ahí.
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && { a2dismod -f autoindex || true; }

COPY docker/php.ini /usr/local/etc/php/conf.d/redemc.ini
COPY docker/apache.conf /etc/apache2/conf-available/redemc.conf
# Solo mpm_prefork. event o worker junto con prefork tiran AH00534 y Apache no arranca.
# El entrypoint vuelve a asegurarlo en cada arranque: en Railway esos módulos a veces reaparecen.
RUN a2enconf redemc \
    && a2dismod -f mpm_event mpm_worker \
    && a2enmod mpm_prefork \
    && rm -f /etc/apache2/mods-enabled/mpm_event.load \
             /etc/apache2/mods-enabled/mpm_event.conf \
             /etc/apache2/mods-enabled/mpm_worker.load \
             /etc/apache2/mods-enabled/mpm_worker.conf

COPY docker/entrypoint.sh /usr/local/bin/redemc-entrypoint.sh
RUN chmod +x /usr/local/bin/redemc-entrypoint.sh

WORKDIR /var/www/html
COPY . /var/www/html

ENTRYPOINT ["redemc-entrypoint.sh"]
