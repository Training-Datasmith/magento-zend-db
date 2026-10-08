FROM php:7.0.33-cli@sha256:950fa73e35e650ee1d5fff5b053feadcd1dc8c6e83bba07d6c7fd17c44868e22

# pdo_sqlite is bundled in the official image; avoid Stretch apt mirrors (EOL).
WORKDIR /app

# Composer 2.2.x LTS (verified at image build time in CI script)
ARG COMPOSER_VERSION=2.2.30
RUN curl -fsSLO https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar \
    && curl -fsSLO https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar.sha256sum \
    && sha256sum -c composer.phar.sha256sum \
    && mv composer.phar /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer \
    && rm composer.phar.sha256sum

ENV COMPOSER_ALLOW_SUPERUSER=1
