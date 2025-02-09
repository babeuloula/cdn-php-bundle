FROM php:8.2-cli

ARG DOCKER_PHP_EXT=2.6.0
ADD https://github.com/mlocati/docker-php-extension-installer/releases/download/${DOCKER_PHP_EXT}/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

RUN \
    apt-get update && \
    apt-get install -y --fix-missing \
        apt-utils \
        jpegoptim \
        gnupg \
        nano \
        unzip \
        git \
        gcc \
        g++ \
        make \
        bash-completion \
        wget \
        webp \
    && \
    apt-get autoremove -y && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    truncate -s 0 /var/log/*log

# Install composer
RUN \
    curl -sl https://getcomposer.org/composer-2.phar -o /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

# Create user
ARG UID
RUN \
    useradd -ms /bin/bash --uid ${UID} docker && \
    usermod -a -G www-data docker && \
    wget -O /home/docker/.bashrc https://gist.githubusercontent.com/babeuloula/3911e3192bdade918c26a43885f31a0a/raw/d37620f6e28606a0db0642687b57afc3acb45f48/.bashrc && \
    chown -R docker:docker /home/docker && \
    chown -R docker /srv
USER docker

WORKDIR /srv
