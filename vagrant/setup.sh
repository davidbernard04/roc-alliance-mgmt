#!/bin/bash

PROJECT_PATH="$1"
if [ -z "$PROJECT_PATH" ]; then
   PROJECT_PATH="/var/www/my_web_app/"
   echo "Path not provided in arguments, falling back to default $PROJECT_PATH"
fi

echo "Installing dependencies with apt-get..."
apt-get update 
# Tesseract
apt-get -y install tesseract-ocr
# My web app
apt-get -y install vim git unzip curl apache2 php php-sqlite3 php-gd
# Not sure if needed anymore
apt-get -y install php-cli php-mbstring 

# Install composer
echo "Installing composer..."
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer; rm /tmp/composer-setup.php

# TODO
# php.ini max upload size (2 MB by default)
# php.ini enable libsqlite3
# php.ini max_execution_time > 30 sec (eu un timeout)

######################################
# Configure apache
######################################
echo "Configuring apache..."

# Enable required apache modules
a2enmod rewrite
a2enmod vhost_alias

CONFIG_NAME=`basename $PROJECT_PATH`

# Write virtual host
cat << END >/etc/apache2/sites-available/$CONFIG_NAME
<VirtualHost *:80>
    DocumentRoot "$PROJECT_PATH/src"

    <Directory "$PROJECT_PATH">
        # Allow .htaccess to control web access (allow/deny).
        AllowOverride All
    </Directory>
</VirtualHost>

END

# Replace default site
a2dissite 000-default.conf 
a2ensite $CONFIG_NAME

# Apply config
systemctl restart apache2.service
