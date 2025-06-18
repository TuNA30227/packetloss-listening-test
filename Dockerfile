FROM php:8.1-apache

# 安裝 PHP 擴充與系統套件
RUN apt-get update && apt-get install -y \
    unzip git curl libzip-dev zip libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# 啟用 Apache rewrite 模組
RUN a2enmod rewrite

# 複製專案原始碼
COPY . /var/www/html/

# ✅ 修正權限問題
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# 切換到專案資料夾
WORKDIR /var/www/html

# ✅ 安裝 Composer
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

# ✅ 安裝 composer 套件（最重要這一行不能漏）
RUN composer install --no-interaction --no-progress --no-suggest

# 設定 Apache 的 web root 指向 Yii2 的 web 資料夾
RUN sed -i 's|/var/www/html|/var/www/html/web|g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80
