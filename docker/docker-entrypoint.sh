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
  # Create TCAT users
  TCATADMINUSER=admin
  TCATADMINPASS=$(openssl rand -base64 32 | tr -c -d 0-9A-Za-z | tr -d O01iIl)
  TCATUSER=tcat
  TCATPASS=$(openssl rand -base64 32 | tr -c -d 0-9A-Za-z | tr -d O01iIl)
  TCATMYSQLUSER=tcatdbuser
  TCATMYSQLPASS=$(openssl rand -base64 32 | tr -c -d 0-9A-Za-z | tr -d O01iIl)

  #----------------------------------------------------------------
  # Grab public IP if desired
  if [ "$SERVERNAME" = 'public' ]; then
    echo ""
    echo "Collecting Public IP ..."
    echo ""
    apt-get install -y curl
    SERVERNAME=$(curl -s https://api.ipify.org)
    echo "SERVERNAME updated with public IP: $SERVERNAME"
  fi
  #----------------------------------------------------------------
  echo ""
  echo "Configuring Apache 2 ..."
  echo ""
  # Save TCAT admin's password
  FILE="${TCAT_CNF_PREFIX}${TCATADMINUSER}${TCAT_CNF_SUFFIX}"
  touch "$FILE"
  chown $SHELLUSER:$SHELLGROUP "$FILE"
  chmod 600 "$FILE" # secure file before writing password to it
  cat > "$FILE" <<EOF
# TCAT Web-UI administrator user
user=$TCATADMINUSER
password="${TCATADMINPASS}"
EOF
  echo "$PROG: TCAT admin login details saved: $FILE"
  # Save TCAT standard user's password
  FILE="${TCAT_CNF_PREFIX}${TCATUSER}${TCAT_CNF_SUFFIX}"
  touch "$FILE"
  chown $SHELLUSER:$SHELLGROUP "$FILE"
  chmod 600 "$FILE" # secure file before writing password to it
  cat > "$FILE" <<EOF
# TCAT Web-UI standard user
user=$TCATUSER
password="${TCATPASS}"
EOF
  echo "$PROG: TCAT tcat login details saved: $FILE"

  #----------------------------------------------------------------
  # Create Apache TCAT config file
  cat > /etc/apache2/sites-available/tcat.conf <<EOF
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
            AuthType Basic
            AuthName "Log in to DMI-TCAT"
            AuthBasicProvider file
            AuthUserFile "$APACHE_PASSWORDS_FILE"

            Require user $TCATADMINUSER $TCATUSER

            DirectoryIndex index.html index.php
            # some directories and files should not be accessible via the web, make sure to enable mod_rewrite
            RewriteEngine on
            RewriteRule ^(cli|helpers|import|logs|proc|config.php|capture/common|capture/klout|capture/pos|capture/search|capture/stream|/capture/user) - [F,L,NC]
        </Directory>
</VirtualHost>
EOF

  a2dissite 000-default
  a2ensite tcat.conf

  # Create TCAT config file

  CFG="$TCAT_DIR/config.php"

  cp "$TCAT_DIR/config.php.example" "$CFG"

  VAR=ADMIN_USER
  VALUE="'$TCATADMINUSER'"
  sed -i "s|^define('$VAR'.*;$|define('$VAR', serialize(array($VALUE)));|" "$CFG"

  VAR=REPOSITORY_URL
  VALUE="'$TCAT_GIT_REPOSITORY'"
  sed -i "s|^define('$VAR',[^)]*);|define('$VAR', $VALUE);|" "$CFG"

  # Create TCAT login credentials file for Apache Basic Authentication

  htpasswd -b -c "$APACHE_PASSWORDS_FILE" $TCATUSER $TCATPASS
  htpasswd -b "$APACHE_PASSWORDS_FILE" $TCATADMINUSER $TCATADMINPASS
  chown $SHELLUSER:$WEBGROUP "$APACHE_PASSWORDS_FILE"

  a2enmod rewrite

  # Install Let's Encrypt via certbot
  if [ "$LETSENCRYPT" = 'y' ]; then
      apt-get install -y certbot python-certbot-apache
      certbot --apache --non-interactive --agree-tos -m "$LETSENCRYPT_EMAIL" -d $SERVERNAME
  fi

  if [ "$LETSENCRYPT" = 'y' ]; then
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
  sed -i "s/example.com\/dmi-tcat\//$SERVERNAME\//g" "$CFG"

  #----------------------------------------------------------------
  # Create file for startup check
  touch "$CHECKFILE"

  #----------------------------------------------------------------
  # Finally!
  echo "TCAT administrator login (for capture setup and analysis):"
  echo "  Username: $TCATADMINUSER"
  echo "  Password: $TCATADMINPASS"
  echo "TCAT standard login (for analysis only):"
  echo "  Username: $TCATUSER"
  echo "  Password: $TCATPASS"
  echo "TCAT logins have been saved to ${TCAT_CNF_PREFIX}*${TCAT_CNF_SUFFIX}."
  echo "MySQL accounts have been saved to ${MYSQL_CNF_PREFIX}*${MYSQL_CNF_SUFFIX}."
  # Start TCAT
  start_tcat
fi