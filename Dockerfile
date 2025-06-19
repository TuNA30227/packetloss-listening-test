FROM php:8.1-apache

# 安裝系統套件與 PHP 擴充
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libzip-dev \
    libonig-dev \
    zip \
    libpng-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql zip mbstring

# 啟用 Apache rewrite
RUN a2enmod rewrite

# 建立 Yii2 所需目錄與權限
RUN mkdir -p /var/www/html/runtime /var/www/html/web/assets /var/www/html/web/audio /var/www/html/web/results \
 && chmod -R 777 /var/www/html/runtime /var/www/html/web/assets /var/www/html/web/audio /var/www/html/web/results

# 複製應用程式到容器中
COPY . /var/www/html

# 設定工作目錄
WORKDIR /var/www/html

# 安裝 Composer 相依套件
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer
RUN composer install --no-interaction --prefer-dist

# 修改 Apache 預設根目錄為 /web
ENV APACHE_DOCUMENT_ROOT /var/www/html/web
RUN sed -ri -e 's!/var/www/html!/var/www/html/web!g' /etc/apache2/sites-available/000-default.conf

# 開放 port
EXPOSE 80

CMD ["apache2-foreground"]
