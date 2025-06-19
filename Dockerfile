FROM php:8.1-apache

# 安裝必要套件
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libzip-dev \
    libonig-dev \
    zip \
    libpng-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql zip mbstring

# 啟用 Apache rewrite module
RUN a2enmod rewrite

# 將本地檔案複製到容器內
COPY . /var/www/html

# 設定正確權限（assets 與 runtime）
RUN mkdir -p /var/www/html/web/assets /var/www/html/runtime \
 && chmod -R 777 /var/www/html/web/assets /var/www/html/runtime

# 設定工作目錄
WORKDIR /var/www/html

# composer 安裝
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer
RUN composer install --no-interaction --prefer-dist

# 設定 Apache 預設首頁到 Yii2 的入口
ENV APACHE_DOCUMENT_ROOT /var/www/html/web
RUN sed -ri -e 's!/var/www/html!/var/www/html/web!g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80
CMD ["apache2-foreground"]
