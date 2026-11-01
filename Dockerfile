FROM php:8.4-apache

# headers/remoteip: security headers and the real client IP behind a proxy.
RUN a2enmod headers remoteip
COPY docker/apache-dps.conf /etc/apache2/conf-enabled/dps.conf
RUN echo 'expose_php=Off' > /usr/local/etc/php/conf.d/dps.ini

COPY . /var/www/html
RUN chmod +x /var/www/html/docker/entrypoint.sh

# Data lives outside the web root, in a volume.
ENV DPS_DATA=/data DPS_DOCKER=1
VOLUME /data

# The HEALTHCHECK is set per service in docker-compose.yml.
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["web"]
