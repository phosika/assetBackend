# Dockerfile
FROM php:8.2-apache

# ຕິດຕັ້ງ PHP extensions ທີ່ຈຳເປັນ
RUN docker-php-ext-install pdo pdo_mysql mysqli

# ຕິດຕັ້ງເຄື່ອງມືທີ່ຈຳເປັນ (optional)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ເປີດໃຊ້ mod_rewrite ສຳລັບ Apache (ຈຳເປັນສຳລັບ API routing)
RUN a2enmod rewrite headers

# ຕັ້ງຄ່າ working directory
WORKDIR /var/www/html

# ສ້າງໂຟນເດີທີ່ຈຳເປັນກ່ອນຄັດລອກໂຄ້ດ
RUN mkdir -p /var/www/html/uploads/assets \
    && mkdir -p /var/www/html/uploads/documents \
    && mkdir -p /var/www/html/uploads/temp \
    && mkdir -p /var/www/html/src/utils \
    && mkdir -p /var/www/html/src/controllers \
    && mkdir -p /var/www/html/src/models \
    && mkdir -p /var/www/html/src/config \
    && mkdir -p /var/www/html/src/middleware

# ຄັດລອກ source code ເຂົ້າໄປໃນ container
COPY ./src /var/www/html/

# ຕັ້ງຄ່າ Apache ໃຫ້ຊີ້ໄປທີ່ public folder (ຖ້າມີ)
# ຖ້າບໍ່ມີ public folder, ໃຫ້ປິດສ່ວນນີ້
# ENV APACHE_DOCUMENT_ROOT /var/www/html/public
# RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
# RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# ຕັ້ງຄ່າ permission ໃຫ້ຖືກຕ້ອງ
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/uploads/assets \
    && chmod -R 777 /var/www/html/uploads/documents \
    && chmod -R 777 /var/www/html/uploads/temp

# ສ້າງ .htaccess ເພື່ອປ້ອງກັນການເຂົ້າເຖິງໂຟນເດີ uploads ໂດຍກົງ
RUN echo "Options -Indexes" > /var/www/html/uploads/.htaccess

# ສ້າງໄຟລ໌ test ເພື່ອກວດສອບວ່າ uploads folder ຂຽນໄດ້
RUN touch /var/www/html/uploads/assets/test.txt && \
    echo "Test file" > /var/www/html/uploads/assets/test.txt && \
    chmod 666 /var/www/html/uploads/assets/test.txt

# ເພີ່ມ PHP configuration ສຳລັບ file uploads
RUN echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 20M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# ເພີ່ມ Apache configuration ສຳລັບ CORS (ຖ້າຈຳເປັນ)
RUN echo "<IfModule mod_headers.c>\n\
    Header set Access-Control-Allow-Origin \"*\"\n\
    Header set Access-Control-Allow-Methods \"GET, POST, PUT, DELETE, OPTIONS\"\n\
    Header set Access-Control-Allow-Headers \"Content-Type, Authorization\"\n\
</IfModule>" > /etc/apache2/conf-available/cors.conf && \
    a2enconf cors

# ສະແດງ error ໃນລະຫວ່າງການພັດທະນາ
RUN echo "display_errors = On" > /usr/local/etc/php/conf.d/display-errors.ini && \
    echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/display-errors.ini

# ເປີດເຜີຍ port 80
EXPOSE 80

# ເລີ່ມຕົ້ນ Apache
CMD ["apache2-foreground"]