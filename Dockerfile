FROM php:8.3-apache

# 1. Instalar dependencias del sistema, drivers y CURL (necesario para instalar Node)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    zip \
    git \
    curl \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_pgsql zip intl

# 2. Instalar Node.js (Versión 20) y NPM
# Esto es CRUCIAL para que Vite funcione
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Activar mod_rewrite
RUN a2enmod rewrite

# Configurar Apache
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar el código del proyecto
WORKDIR /var/www/html
COPY . .

# 3. Instalar dependencias de Laravel (PHP)
RUN composer install --no-dev --optimize-autoloader

# 4. Instalar dependencias de Frontend (Node) y COMPILAR ASSETS
# Este paso crea el archivo manifest.json que te falta
RUN npm install
RUN npm run build

# Permisos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# --- Configurar el script de arranque ---
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Ejecutar el script al iniciar
CMD ["/usr/local/bin/start.sh"]