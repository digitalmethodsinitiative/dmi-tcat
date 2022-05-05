#!/bin/bash
# docker-entrypoint.sh runs when the TCAT Docker container starts
# It should address first time installation issues

# Environment variables that can be passed when running Docker container
# SERVERNAME defaults to localhost on build
# LETSENCRYPT defaults to n on build
#----------------------------------------------------------------
# Load Install Parameters
# TCAT_DIR, MYSQL_CNF_PREFIX, MYSQL_CNF_SUFFIX, SHELLUSER, SHELLGROUP, TCAT_GIT_REPOSITORY
. ../docker/config_parameters.txt

#----------------------------------------------------------------
# Get TCAT users from arguments
TCATADMINUSER=$1
TCATADMINPASS=$2
TCATUSER=$3
TCATPASS=$4

echo ""
echo "Creating Apache Users ..."
echo ""

# Save users and passwords
#----------------------------------------------------------------
FILE="${TCAT_APACHE_LOGINS}"
touch "$FILE"
chown $SHELLUSER:$SHELLGROUP "$FILE"
chmod 600 "$FILE" # secure file before writing password to it
cat > "$FILE" <<EOF
# TCAT Web-UI administrator user
user=$TCATADMINUSER
password="${TCATADMINPASS}"
# TCAT Web-UI standard user
user=$TCATUSER
password="${TCATPASS}"
EOF

echo "TCAT administrator login (for capture setup and analysis):"
echo "  Username: $TCATADMINUSER"
echo "  Password: $TCATADMINPASS"
echo "TCAT standard login (for analysis only):"
echo "  Username: $TCATUSER"
echo "  Password: $TCATPASS"
echo "TCAT logins have been saved to: ${FILE}"

# Update Apache TCAT Config file
# Finds '# basic authentication' and replaces it with all the necessary information
perl -0777 -pi -e "s|# basic authentication|# basic authentication\n            AuthType Basic\n            AuthName \"Log in to DMI-TCAT\"\n            AuthBasicProvider file\n            AuthUserFile $APACHE_PASSWORDS_FILE\n            Require user $TCATADMINUSER $TCATUSER|" "$APACHE_TCAT_CONFIG_FILE"

# Update TCAT Config file with Admin user
CFG="$TCAT_DIR/config.php"

VAR=ADMIN_USER
VALUE="'$TCATADMINUSER'"
sed -i "s|^define('$VAR'.*;$|define('$VAR', serialize(array($VALUE)));|" "$CFG"

# Create TCAT login credentials file for Apache Basic Authentication

htpasswd -b -c "$APACHE_PASSWORDS_FILE" $TCATUSER $TCATPASS
htpasswd -b "$APACHE_PASSWORDS_FILE" $TCATADMINUSER $TCATADMINPASS
chown $SHELLUSER:$WEBGROUP "$APACHE_PASSWORDS_FILE"

# Restart apache with changes
apachectl restart