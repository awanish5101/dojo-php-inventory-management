FROM php:8.2-apache

# Install PDO MySQL driver and dependencies
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy project files into web root
COPY . /var/www/html/

# Set working directory and file permissions
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Expose standard HTTP port
EXPOSE 80

CMD ["apache2-foreground"]
