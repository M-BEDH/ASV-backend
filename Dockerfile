FROM php:8.2-fpm

# Dépendances système
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

    # APCu -> stocke des données PHP directement en mémoire du serveur pour éviter de les recalculer ou de les récupérer à chaque requête.
RUN pecl install apcu && docker-php-ext-enable apcu

# Composer (copié depuis l'image officielle, sans changer la version PHP)
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
