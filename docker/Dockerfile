FROM httpd:2.4-bullseye

# Additional utilities
RUN apt-get -y update
RUN apt-get -y install vim curl git unzip
# Install PHP
RUN apt-get -y install php php-cli php-mbstring php-sqlite3 php-gd php-json php-xml
# Install OCR
RUN apt-get -y install tesseract-ocr

# Copy sources from local repository.
COPY src /usr/local/apache2/htdocs/src
COPY composer /usr/local/apache2/htdocs/composer

# Install composer from official docker image.
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
# Install dependencies with composer.
WORKDIR /usr/local/apache2/htdocs/composer
RUN composer install

# Set apache group permission (else cause problem in the src/writable directory)
RUN chgrp -R www-data /usr/local/apache2/htdocs

# Enable PHP: Use mpm_prefork instead of mpm_even. Load PHP7 module and config.
WORKDIR /usr/local/apache2
RUN sed -i \
        -e 's/^\(LoadModule mpm_event_module .*\)/#\1/' \
        -e 's/^#\(LoadModule mpm_prefork_module .*\)/\1\nLoadModule php7_module \/usr\/lib\/apache2\/modules\/libphp7.4.so/' \
        -e 's/^#\(Include conf\/extra\/httpd-mpm.*\)/\1\nInclude \/etc\/apache2\/mods-enabled\/php7.4.conf/' \
        conf/httpd.conf
#TODO manque de activer les .htaccess... ou créer un virtual host?

# Change DocumentRoot to "src" folder, and load index.php by default.
RUN sed -i \
        -e 's/htdocs/htdocs\/src/g' \ 
        -e 's/index\.html/index\.php/' \
        conf/httpd.conf

# Uncomment for Heroku, to change the listening port based on the environment variable.
#RUN sed -i \
#        -e 's/Listen 80/Listen ${PORT}/' \
#        conf/httpd.conf
