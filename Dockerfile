FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
COPY .prettierrc.json ./
RUN npm ci
COPY assets/scss ./assets/scss
RUN npm run styles:build

FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev tini \
    && docker-php-ext-install curl mbstring \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod headers \
    && printf 'ServerTokens Prod\nServerSignature Off\n<Directory /var/www/html>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/elections.conf \
    && a2enconf elections
WORKDIR /var/www/html
COPY . .
COPY --from=assets /build/assets/style.css ./assets/style.css
RUN chmod +x docker/entrypoint.sh && chown -R www-data:www-data storage
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s CMD php -r 'exit(@file_get_contents("http://127.0.0.1/api.php") === false ? 1 : 0);'
ENTRYPOINT ["/usr/bin/tini", "--", "/bin/sh", "/var/www/html/docker/entrypoint.sh"]
