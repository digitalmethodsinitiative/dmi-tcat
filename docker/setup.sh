#!/bin/bash
#
# Installer for DMI-TCAT via Docker Build
#
#----------------------------------------------------------------
# Load Install Parameters
# TCAT_DIR, MYSQL_CNF_PREFIX, MYSQL_CNF_SUFFIX, SHELLUSER, SHELLGROUP, WEBUSER, WEBGROUP
. docker/config_parameters.txt

#----------------------------------------------------------------
# Error checking

PROG=$(basename "$0")

# Trap to abort script when a command fails.

trap "echo $PROG: command failed: install aborted; exit 3" ERR
# Can't figure out which command failed? Run using "bash -x" or uncomment:
#set -x # write each command to stderr before it is exceuted

#set -e # fail if a command fails (this works in sh, since trap ERR does not)

set -u # fail on attempts to expand undefined variables

#----------------------------------------------------------------
apt-get update

# Generate random passwords
apt-get -qq install -y openssl

# Needed when installing MySQL for root user
DBPASS=$(openssl rand -base64 32 | tr -c -d 0-9A-Za-z | tr -d O01iIl)

#----------------------------------------------------------------
echo
echo "Installing GIT ..."
echo ""

apt-get -qq install -y git

#----------------------------------------------------------------
echo
echo "Installing MySQL server and Apache webserver ..."
echo ""

# Set MySQL root password to avoid prompt during "apt-get install" MySQL server
echo "mysql-server mysql-server/root_password password $DBPASS" |
debconf-set-selections
echo "mysql-server mysql-server/root_password_again password $DBPASS" |
debconf-set-selections

echo "$PROG: installing MySQL for Ubuntu"
apt-get -y install mariadb-server mariadb-client

echo "$PROG: installing Apache for Ubuntu"
apt-get -y install apache2 apache2-utils

# This will install PHP 7
# Ubuntu versions starting from 17.04 have the PHP GEOS module in the repository
PHP_PACKAGES="libapache2-mod-php php-mysql php-curl php-cli php-patchwork-utf8 php-mbstring php-geos"

echo "$PROG: installing PHP packages:"
echo "  $PHP_PACKAGES"
apt-get -y install $PHP_PACKAGES

# Installation and autoconfiguration of MySQL will not work with
# Apparmor profile enabled
if [ -L /etc/init.d/apparmor ]; then
    if [ -L /etc/apparmor.d/disable/usr.sbin.mysqld ]; then
        rm /etc/apparmor.d/disable/usr.sbin.mysqld # remove so "ln" will work
    fi
    ln -s /etc/apparmor.d/usr.sbin.mysqld /etc/apparmor.d/disable/
    /etc/init.d/apparmor restart
fi

#----------------------------------------------------------------
echo ""
echo "Preliminary DMI-TCAT configuration ..."
echo ""

# Create unix user and group, if needed

if ! id "$SHELLUSER" >/dev/null 2>&1; then
    # User does not exist: create it
    adduser --quiet --disabled-login --gecos 'DMI-TCAT' "$SHELLUSER"
fi

if ! grep "^$SHELLGROUP:" /etc/group >/dev/null; then
    # Group does not exist: create it
    addgroup --quiet "$SHELLGROUP"
fi

chown -R $SHELLUSER:$SHELLGROUP "$TCAT_DIR"
cd "$TCAT_DIR"
mkdir analysis/cache logs proc config
chown $WEBUSER:$WEBGROUP analysis/cache config helpers/create_apache_users.sh
chmod 755 analysis/cache
chown $SHELLUSER:$SHELLGROUP logs proc
# Changing logs from 755 to 777 so that both webuser and shelluser can write to them
chmod 777 logs
chmod 755 proc

#----------------------------------------------------------------
# Save passwords in MySQL defaults-file format
# Note: done after the "mysql" unix user created so it can own the file

# Save MySQL admin's password

MYSQL_USER_ADMIN_CNF="${MYSQL_CNF_PREFIX}root${MYSQL_CNF_SUFFIX}"

FILE="$MYSQL_USER_ADMIN_CNF"
touch "$FILE"
chown mysql:mysql "$FILE"
chmod 600 "$FILE" # secure file before writing password to it
cat > "$FILE" <<EOF
# MySQL/MariaDB config
[client]
user=root
password="${DBPASS}"
EOF
echo "$PROG: root mysql account details saved: $FILE"

service mysql restart
# Install MySQL server timezone data

mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql --defaults-file="$MYSQL_USER_ADMIN_CNF" mysql

#----------------------------------------------------------------
echo ""
echo "Activating TCAT controller in cron ..."
echo ""

mkdir -p /etc/cron.d/
CRONLINE="* *     * * *   $SHELLUSER   (cd \"$TCAT_DIR/capture/stream\"; php controller.php)"
echo "" >> /etc/cron.d/tcat
echo "# Run TCAT controller every minute" >> /etc/cron.d/tcat
echo "$CRONLINE" >> /etc/cron.d/tcat

#----------------------------------------------------------------
echo ""
echo "Setting up logfile rotation ..."
echo ""

cat > /etc/logrotate.d/dmi-tcat <<EOF
$TCAT_DIR/logs/controller.log $TCAT_DIR/logs/track.error.log $TCAT_DIR/logs/follow.error.log $TCAT_DIR/logs/onepercent.error.log
{
   weekly
   rotate 8
   compress
   delaycompress
   missingok
   ifempty
   create 644 $SHELLUSER $SHELLGROUP
}
EOF

#----------------------------------------------------------------
# Additional MySQL Setup

# Check if the current MySQL configuration is the system default one
apt-get -y install debsums

# TCAT will not function fully on modern versions of MySQL without some modified settings
echo "[mysqld]" > /etc/mysql/conf.d/tcat-autoconfigured.cnf

echo ""
echo "Configuring MySQL server (compatibility) ..."
echo ""
echo "sql-mode=\"NO_AUTO_VALUE_ON_ZERO,ALLOW_INVALID_DATES\"" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf

echo ""
echo "Configuring MySQL server (memory profile) ..."
echo ""
MAXMEM=`free -m | head -n 2 | tail -n 1 | tr -s ' ' | cut -d ' ' -f 2`
# Make this an integer, and non-empty for bash
MAXMEM=${MAXMEM//[^[:digit:]]/0}
MAXMEM=${MAXMEM//^$/0}
echo "Maximum machine memory detected: $MAXMEM Mb"
if [ "$MAXMEM" -lt "1024" ]; then
    echo "This machine has a limited ammount of memory; leaving system defaults in place."
else
    # Set the key buffer to 1/3 of system memory
    SIZE=$(($MAXMEM/3))
    echo "key_buffer_size         = $SIZE""M" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf
    if [ "$MAXMEM" -gt "1024" ]; then
        # Set the query cache limit to 128 Mb
        echo "query_cache_limit       = 128M" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf
    else
        # For machines with 1G memory or less, set the query cache limit to 64 Mb
        echo "query_cache_limit       = 64M" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf
    fi
    # Set the total query cache size to 1/8 of systemn emory
    SIZE=$(($MAXMEM/8))
    echo "query_cache_size        = $SIZE""M" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf
    # Increase sizes of temporary tables (memory sort tables for doing a GROUP BY) for machines with sufficient memory
    if [ "$MAXMEM" -gt "7168" ]; then
        echo "tmp_table_size          = 1G" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf
        echo "max_heap_table_size     = 1G" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf
    elif [ "$MAXMEM" -gt "3072" ]; then
        echo "tmp_table_size          = 256M" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf
        echo "max_heap_table_size     = 256M" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf
    fi
    # Unless we have a machine capable of performing many heavy queries simultaneously,
    # it is sensible to restrict the maximum number of client connections. This will reduce the overcommitment of memory.
    # The default max_connections for MySQL 5.6 is 150. In any case, even 80 is still a high figure.
    if [ "$MAXMEM" -lt "31744" ]; then
        echo "max_connections         = 80" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf
    fi
    echo "Memory profile adjusted"

    # Finally, reload MySQL server configuration
    echo "Restarting service ..."
    service mysql restart
fi

echo ""
echo "Done: TCAT installed"
echo ""

exit 0
