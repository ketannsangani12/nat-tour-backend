# FROM 194836855709.dkr.ecr.us-east-1.amazonaws.com/php8.0-fpm:latest
# FROM 194836855709.dkr.ecr.us-east-1.amazonaws.com/php8.1-fpm:latest
# FROM 194836855709.dkr.ecr.us-east-1.amazonaws.com/php8.2-fpm:latest
# FROM 194836855709.dkr.ecr.us-east-1.amazonaws.com/php8.3-fpm:latest
# Pinned to 8.3.36+ for PHP2Shell (CVE-2026-109618, CVE-2026-109619)
FROM public.ecr.aws/docker/library/php:8.3.36-fpm-trixie
#FROM php:8.3-fpm
#FROM public.ecr.aws/docker/library/php:8.3-fpm

LABEL maintainer="Devat Karetha <devat@glorywebs.com>"
LABEL Description="This image is used to start a serverless Laravel app." Vendor="dekts" Version="1.0"

# Set working directory
WORKDIR /var/www

# Install additional dependencies
RUN apt-get update && apt-get install -y \
    libfreetype-dev \
    libjpeg62-turbo-dev \
    libzip-dev \
    libjpeg-dev \
    libmagickwand-dev \
    libmagickcore-dev \
    libpng-dev \
    libfreetype6-dev \
    libonig-dev \
    pkg-config \
    locales \
    zip \
    unzip \
    git \
    ca-certificates \
    curl \
    gnupg \
    nginx \
    supervisor \
    libmemcached-dev \
    libssl-dev \
    libicu-dev \
    zlib1g-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-install pdo pdo_mysql zip opcache mbstring exif pcntl intl bcmath xml \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-enable opcache

# Set the locale
RUN echo "en_US.UTF-8 UTF-8" > /etc/locale.gen && locale-gen

# Install Imagick
RUN curl -L -o imagick.tar.gz https://pecl.php.net/get/imagick-3.7.0.tgz && \
    tar -xzf imagick.tar.gz && \
    cd imagick-3.7.0 && \
    phpize && \
    ./configure && \
    make && make install && \
    docker-php-ext-enable imagick

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Create bootstrap/cache and storage directories
RUN mkdir -p /var/www/bootstrap/cache /var/www/storage

# Add user for laravel application
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copy application code to /var/www
COPY --chown=www:www-data . /var/www

# Set permissions
RUN chown -R www-data:www-data /var/www/storage 
RUN chown -R www-data:www-data /var/www/bootstrap/cache
RUN chmod -R 777 /var/www/storage
RUN chmod -R 777 /var/www/bootstrap
#RUN chmod -R ug+w /var/www/bootstrap

# Copy Nginx/PHP/Supervisor configs
COPY .docker/supervisor.conf /etc/supervisord.conf
COPY .docker/supervisor-queue.conf /etc/supervisor/conf.d/supervisor-queue.conf
COPY .docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY .docker/fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY .docker/nginx.conf /etc/nginx/sites-enabled/default

# Create PHP error log files
RUN mkdir /var/log/php && touch /var/log/php/errors.log && chmod 777 /var/log/php/errors.log

# Ensure Git does not block due to ownership mismatches
RUN git config --global --add safe.directory /var/www
RUN ls -la

RUN composer install --optimize-autoloader --no-dev

# Install NPM
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
RUN apt-get install -y nodejs
RUN npm install

# Provide permission to shell script
RUN chmod +x /var/www/.docker/run.sh

EXPOSE 80

ENTRYPOINT ["/var/www/.docker/run.sh"]