#!/bin/bash

# Fixed parameters

WEBUSER=www-data
WEBGROUP=www-data

# Make sure only root can run our script

if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

# Get user parameters

read -p "Server name for TCAT: " SERVERNAME
read -p "Shell username for tcat ownership (usually a SSH user, but don't use root): " SHELLUSER
read -p "Shell group name for tcat ownership (usually the same as the username): " SHELLGROUP
read -p "Username for MySQL database: " DBUSER
read -p "Password for MySQL database (repeat this password during MySQL package install!): " DBPASS

# Install

apt-get update
apt-get -y upgrade
apt-get install wget
wget http://dev.mysql.com/get/mysql-apt-config_0.3.5-1debian8_all.deb
dpkg -i mysql-apt-config_0.3.5-1debian8_all.deb
apt-get -y install mariadb-server apache2-mpm-prefork apache2-utils libapache2-mod-php5 php5-mysql php5-curl php5-cli php5-geos php-patchwork-utf8 git curl
php5enmod geos
git clone https://github.com/digitalmethodsinitiative/dmi-tcat.git /var/www/dmi-tcat
chown -R $WEBUSER:$WEBGROUP /var/www/dmi-tcat/
cd /var/www/dmi-tcat
mkdir analysis/cache logs proc
chown $WEBUSER:$WEBGROUP analysis/cache
chmod 755 analysis/cache
chown $SHELLUSER:$SHELLGROUP logs proc
chmod 755 logs
chmod 755 proc
read -d '' APACHECONF1 <<"EOF"
<VirtualHost *:80>
EOF
read -d '' APACHECONF2 <<"EOF"
        DocumentRoot /var/www/dmi-tcat/

        RewriteEngine On
        RewriteRule ^/$ /analysis/ [R,L]

        <Directory />
                Options FollowSymLinks
                AllowOverride None
        </Directory>

        <Directory /var/www/dmi-tcat/>
            # make sure directory lists are not possible
            Options -Indexes
            # basic authentication
            AuthType Basic
            AuthName "Log in to DMI-TCAT"
            AuthBasicProvider file
            AuthUserFile /etc/apache2/passwords
            Require user admin dmi
            DirectoryIndex index.html index.php
            # some directories and files should not be accessible via the web, make sure to enable mod_rewrite
            RewriteEngine on
            RewriteRule ^(cli|helpers|import|logs|proc|config.php|capture/common|capture/klout|capture/pos|capture/search|capture/stream|/capture/user) - [F,L,NC]
        </Directory>
</VirtualHost>
EOF
echo "$APACHECONF1"  > /etc/apache2/sites-available/tcat.conf
echo "        ServerName $SERVERNAME"  >> /etc/apache2/sites-available/tcat.conf
echo "        $APACHECONF2"  >> /etc/apache2/sites-available/tcat.conf
a2dissite 000-default
a2ensite tcat.conf
cp /var/www/dmi-tcat/config.php.example /var/www/dmi-tcat/config.php
htpasswd -b -c /etc/apache2/passwords dmi dmi
htpasswd -b /etc/apache2/passwords admin tcatadmin
chown $SHELLUSER:$WEBGROUP /etc/apache2/passwords
a2enmod rewrite
systemctl restart apache2
echo "CREATE DATABASE IF NOT EXISTS twittercapture DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci;" | mysql -u$DBUSER  -p$DBPASS
sed -i "s/dbuser = \"\"/dbuser = \"$DBUSER\"/g" /var/www/dmi-tcat/config.php
sed -i "s/dbpass = \"\"/dbpass = \"$DBPASS\"/g" /var/www/dmi-tcat/config.php
sed -i "s/example.com\/dmi-tcat\//$SERVERNAME\//g" /var/www/dmi-tcat/config.php
CRONLINE="* *     * * *   $SHELLUSER   (cd /var/www/dmi-tcat/capture/stream/; php controller.php)"
echo "" >> /etc/crontab
echo "# Run TCAT controller every minute" >> /etc/crontab
echo "$CRONLINE" >> /etc/crontab

echo ""
echo "You are ready for the final configurations steps of TCAT. Please select the primary capture role of this TCAT instance."
echo ""
echo "1) Track phrases and keywords"
echo "2) Follow Twitter users"
echo "3) Capture a one percent sample of all Twitter traffic"
echo ""
echo "Enter your choice:"
read INPUT
if [ $INPUT -eq 1 ]; then
	echo "Using role: track"
elif [ $INPUT -eq 2 ]; then
	echo "Using role: follow"
	sed -i "s/array(\"track\")/array(\"follow\")/g" /var/www/dmi-tcat/config.php
elif [ $INPUT -eq 3 ]; then
	echo "Using role: onepercent"
	sed -i "s/array(\"track\")/array(\"onepercent\")/g" /var/www/dmi-tcat/config.php
else
	echo "Unrecognized input. Skipping this step and sticking to default role: track."
fi

echo ""
echo "Please visit your new TCAT installation at: http://$SERVERNAME/capture/"
echo "Default login credentials for regular users are dmi:dmi"
echo "Default login credentials for admin users are admin:tcatadmin"
echo "Use the htpasswd utility and password file /etc/apache2/passwords to change passwords or add users"
echo ""
echo "The initial setup of TCAT has been completed. The following steps are still REQUIRED for capture to work"
echo ""
echo " * Edit the file /var/www/dmi-tcat/config.php and fill in your Twitter API keys"
echo ""
echo "The following steps are recommended, but not required"
echo ""
echo " * Set-up your systems e-mail (sendmail)"
echo ""
