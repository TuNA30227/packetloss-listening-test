# 使用官方 PHP 映像 + Apache
FROM php:8.1-apache

# 安裝必要套件
RUN apt-get update && apt-get install -y \
    unzip git curl libzip-dev zip libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# 啟用 Apache rewrite 模組
RUN a2enmod rewrite

# 複製網站檔案
COPY . /var/www/html/

# 設定 Apache 根目錄為 Yii2 的 web 目錄
WORKDIR /var/www/html
RUN sed -i 's|/var/www/html|/var/www/html/web|g' /etc/apache2/sites-available/000-default.conf

# 安裝 composer 並安裝依賴
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer
RUN composer install --no-interaction

EXPOSE 80
