#!/bin/bash
# docker-entrypoint.sh runs when the TCAT Docker container starts
# It should address first time installation issues

# Environment variables that can be passed when running Docker container
# SERVERNAME defaults to localhost on build
# LETSENCRYPT defaults to n on build
#----------------------------------------------------------------
# Load Install Parameters
# TCAT_DIR, MYSQL_CNF_PREFIX, MYSQL_CNF_SUFFIX, SHELLUSER, SHELLGROUP, TCAT_GIT_REPOSITORY
. docker/config_parameters.txt

PROG=$(basename "$0")
#----------------------------------------------------------------
set -e

exit_backend() {
  # Runs when Docker container exited
  echo "Exiting TCAT"
  exit 0
}

trap exit_backend INT TERM

start_tcat() {
  echo "Starting TCAT"
  sudo service cron start && service mysql start && apachectl start

  # Hang out until SIGTERM received
  while true; do
      sleep 1
  done
}
#----------------------------------------------------------------
# Check if first run
CHECKFILE=/var/www/dmi-tcat/docker/first_run.txt
if [ -f "$CHECKFILE" ]; then
  # Start TCAT
  start_tcat
else
  # First time setup
  #----------------------------------------------------------------
  # Grab public IP if desired
  if [ "$SERVERNAME" = 'public' ]; then
    echo ""
    echo "Collecting Public IP ..."
    echo ""
    apt-get update && apt-get install --fix-missing -y curl
    SERVERNAME=$(curl -s https://api.ipify.org)
    echo "SERVERNAME updated with public IP: $SERVERNAME"
  fi
  #----------------------------------------------------------------
  echo ""
  echo "Configuring Apache 2 ..."
  echo ""

  # Create Apache TCAT config file
  cat > "$APACHE_TCAT_CONFIG_FILE" <<EOF
# Apache config for DMI-TCAT

<VirtualHost *:80>
        ServerName $SERVERNAME

        DocumentRoot "$TCAT_DIR"

        RewriteEngine On
        RewriteRule ^/$ /analysis/ [R,L]

        <Directory />
                Options FollowSymLinks
                AllowOverride None
        </Directory>

        <Directory "$TCAT_DIR">
            # make sure directory lists are not possible
            Options -Indexes
            # basic authentication

            DirectoryIndex index.html index.php
            # some directories and files should not be accessible via the web, make sure to enable mod_rewrite
            RewriteEngine on
            RewriteRule ^(cli|helpers|import|logs|proc|config.php|capture/common|capture/klout|capture/pos|capture/search|capture/stream|/capture/user) - [F,L,NC]
        </Directory>
</VirtualHost>
EOF

  a2dissite 000-default
  a2ensite tcat.conf
  a2enmod rewrite

  # This will allow create_apache_users.sh to be run by www-data (via the frontend in config_tcat.php) with the sudo command
  # It should be the only file able to be run by www-data
  echo 'www-data ALL = NOPASSWD: /var/www/dmi-tcat/helpers/create_apache_users.sh' | sudo EDITOR='tee -a' visudo

  # Create TCAT config file

  CFG="$TCAT_DIR/config.php"

  cp "$TCAT_DIR/docker/config.php.example" "$CFG"
  sed -i "s/example.com\/dmi-tcat\//$SERVERNAME\//g" "$CFG"

  VAR=REPOSITORY_URL
  VALUE="'$TCAT_GIT_REPOSITORY'"
  sed -i "s|^define('$VAR',[^)]*);|define('$VAR', $VALUE);|" "$CFG"

  # Install Let's Encrypt via certbot
  if [ "$LETSENCRYPT" = 'y' ]; then
      apt-get install -y certbot python-certbot-apache
      certbot --apache --non-interactive --agree-tos -m "$LETSENCRYPT_EMAIL" -d $SERVERNAME
      sed -i "s/http:\/\//https:\/\//g" "$CFG"
  fi

  # Restart apache with changes
  /etc/init.d/apache2 restart

  #----------------------------------------------------------------
  echo ""
  echo "Configuring MySQL server for TCAT (authentication) ..."
  echo ""
  # Save MySQL TCAT database user's password
  MYSQL_USER_ADMIN_CNF="${MYSQL_CNF_PREFIX}root${MYSQL_CNF_SUFFIX}"

  FILE="${MYSQL_CNF_PREFIX}${TCATMYSQLUSER}${MYSQL_CNF_SUFFIX}"
  echo "${MYSQL_CNF_PREFIX}${TCATMYSQLUSER}${MYSQL_CNF_SUFFIX}"
  touch "$FILE"
  chown mysql:mysql "$FILE"
  chmod 600 "$FILE" # secure file before writing password to it
  cat > "$FILE" <<EOF
# MySQL/MariaDB config
[client]
user=$TCATMYSQLUSER
password="${TCATMYSQLPASS}"
EOF
  echo "$PROG: mysql account details saved: $FILE"

  service mysql restart

  #----------------------------------------------------------------
  # Create twittercapture database
  echo "CREATE DATABASE IF NOT EXISTS twittercapture DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci;" | mysql --defaults-file="$MYSQL_USER_ADMIN_CNF"
  echo "GRANT CREATE, DROP, LOCK TABLES, ALTER, DELETE, INDEX, INSERT, SELECT, UPDATE, CREATE TEMPORARY TABLES ON twittercapture.* TO '$TCATMYSQLUSER'@'localhost' IDENTIFIED BY '$TCATMYSQLPASS';" | mysql --defaults-file="$MYSQL_USER_ADMIN_CNF"
  echo "FLUSH PRIVILEGES;" | mysql --defaults-file="$MYSQL_USER_ADMIN_CNF"
  sed -i "s/dbuser = \"\"/dbuser = \"$TCATMYSQLUSER\"/g" "$CFG"
  sed -i "s/dbpass = \"\"/dbpass = \"$TCATMYSQLPASS\"/g" "$CFG"

  #----------------------------------------------------------------
  # Create file for startup check
  touch "$CHECKFILE"

  #----------------------------------------------------------------
  # Finally!
  echo "MySQL accounts have been saved to ${MYSQL_CNF_PREFIX}*${MYSQL_CNF_SUFFIX}."
  # Start TCAT
  start_tcat
fi
