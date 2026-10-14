FROM au9500/php:8.5

# bcmath backs ez-php/bignum, used by the 'bigint' / 'decimal[:scale]' casts
# (the base image has neither bcmath nor gmp)
RUN docker-php-ext-install bcmath

WORKDIR /var/www/html

CMD ["/usr/local/bin/container-start.sh"]
