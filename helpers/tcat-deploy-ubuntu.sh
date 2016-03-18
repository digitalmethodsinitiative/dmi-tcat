#!/bin/bash
#
# Installer for DMI-TCAT on Ubuntu.
#
# This script prompts the user interactively for parameters, or it can
# be run in batch mode (with the -b option).
#
# The paramaters can also be loaded from a configuration file (with
# the -c option).  The format of the config file is a Bash shell file
# that sets values for some/all of the parameter environment variables
# from the top section of this file (since it is simply sourced by
# this script).
#
# To be able to run in batch mode, a config file *must* at least provide
# values for CONSUMERKEY, CONSUMERSECRET, USERTOKEN and USERSECRET.
#
# Run with -h for help.
#
# WARNING: reinstalls are experimental. Running this script more than
# once is not guaranteed to work.
# ----------------------------------------------------------------

#----------------------------------------------------------------
# Changelog:
#
# - set MySQL server's password so user is not prompted for it by apt-get
# - batch mode added for unattended installation (with a config file)
# - in interactive mode, all questions are asked at the beginning
# - default values provided for parameters (novice users can just accept them)
# - default SERVERNAME is derived from IP address (useful for testing on VMs)
# - option to generate random passwords (more secure than user made up ones)
# - shell user and groups are automatically created if they don't already exist
# - user cannot change the name of the MySQL admin account (it must be "root")
# - experimental support for reinstalling (i.e. running the script again)
# - tested on Ubuntu 14.04.3 and 15.04.
#
# TODO:
#
# - Handle errors from commands properly. Abort if a subcommand fails.
# - Reorganise so installation of MySQL, Apache and TCAT are logically separate
#----------------------------------------------------------------

# TCAT Installer parameters

# Twitter API credentials and capture options

CONSUMERKEY=
CONSUMERSECRET=
USERTOKEN=
USERSECRET=

CAPTURE_MODE=1 # 1=track phrases/keywords, 2=follow users, 3=onepercent

URLEXPANDYES=y # install URL Expander or not

# Apache

SERVERNAME= # should default to this machine's IP address (-s overrides)

# Unix user and group to own the TCAT files

SHELLUSER=tcat
SHELLGROUP=tcat

# MySQL (blank password means randomly generate it)

DBPASS= # password for the MySQL "root" administrator account

TCATMYSQLUSER=tcatdbuser
TCATMYSQLPASS=

DB_CONFIG_MEMORY_PROFILE=y

# TCAT Web user interface logins (blank password means randomly generate it)

TCATADMINUSER=admin
TCATADMINPASS=

TCATUSER=tcat
TCATPASS=

#----------------------------------------------------------------
# Error checking

PROG=`basename "$0"`

# Trap to abort script when a command fails.

# This script does not yet handle errors properly. When it does, uncomment:
# trap "echo $PROG: error: aborted; exit 3" ERR
# set -e # fail if a command fails (this works in sh, since trap ERR does not)

set -u # fail on attempts to expand undefined variables

#----------------------------------------------------------------
# Other constants (these should not need changing)

# Where the MySQL defaults files are written

MYSQL_CNF_PREFIX='/var/lib/mysql/user-'
MYSQL_CNF_SUFFIX='.cnf'

# Where the TCAT logins are written

TCAT_CNF_PREFIX='/var/lib/mysql/tcat-login-'
TCAT_CNF_SUFFIX='.txt'

# Where to install TCAT files

TCAT_DIR=/var/www/dmi-tcat

# MySQL server package name for apt-get

MYSQL_SERVER_PKG=mysql-server-5.6

# Apache user and group

WEBUSER=www-data
WEBGROUP=www-data

#----------------------------------------------------------------
# Functions

# Prompts for a non-blank string
#
# Usage: promptStr promptMessage defaultValue
#
# Outputs: the string value

promptStr() {
    P_PROMPT=$1
    if [ $# -gt 1 ]; then
	P_DEFAULT=$2
    else
	P_DEFAULT=
    fi

    if [ -n "$P_DEFAULT" ]; then
	P_PROMPT="$P_PROMPT [$P_DEFAULT]"
    fi

    P_INPUT=
    while [ -z "$P_INPUT" ]; do
	read -p "$P_PROMPT: " P_INPUT
	if [ -z "$P_INPUT" ] ; then
	    P_INPUT="$P_DEFAULT"
	fi
    done
    echo $P_INPUT

    unset P_PROMPT
    unset P_DEFAULT
}

# Prompts for "y" or "n".
#
# Usage: promptYN promptMessage [defaultValue]
#
# Outputs: nothing
#
# Returns: 0 if "y", 1 if "n", or exits script if "q"

promptYN() {
    if [ $# -gt 1 ]; then
	P_DEFAULT="$2"
    else
	P_DEFAULT=
    fi

    if [ "$P_DEFAULT" = 'y' ]; then
	P_PROMPT="$1 [Y/n]? "
    elif [ "$P_DEFAULT" = 'n' ]; then
	P_PROMPT="$1 [y/N]? "
    else
	P_PROMPT="$1 [y/n]? "
    fi

    P_INPUT=
    while [ "$P_INPUT" != 'y' -a "$P_INPUT" != 'n' ]; do
	read -p "$P_PROMPT" P_INPUT
	if [ "$P_INPUT" = 'q' -o "$P_INPUT" = 'quit' -o \
	    "$P_INPUT" = 'Q' -o "$P_INPUT" = 'QUIT' ]; then
	    echo "$PROG: aborted by user" >&2
	    exit 1
	elif [ "$P_INPUT" = 'yes' -o \
	    "$P_INPUT" = 'Y' -o "$P_INPUT" = "YES" ]; then
	    P_INPUT='y'
	elif [ "$P_INPUT" = 'no' -o \
	    "$P_INPUT" = 'N' -o "$P_INPUT" = "NO" ]; then
	    P_INPUT='n'
	elif [ -z "$P_INPUT" ]; then
	    P_INPUT="$P_DEFAULT"
	fi
    done

    unset P_PROMPT
    unset P_DEFAULT

    if [ "$P_INPUT" = 'y' ]; then
	unset P_INPUT
	return 0
    else
	unset P_INPUT
	return 1
    fi
}

# Prompts for a password
#
# Usage: promptPassword promptMessage
#
# Usually call it like this:
#   VALUE=$(promptPassword "message"); echo
#
# Outputs: nothing
#
# Returns: the password, or the empty string.

promptPassword() {
    P_PROMPT="$1 [default: randomly generated]: "
    P_INPUT=""
    while [ -z "$P_INPUT" ]; do
	read -s -p "$P_PROMPT" P_INPUT
	if [ -z "$P_INPUT" ]; then
	    # Blank password means to randomly generate it
	    break
	else
	    # Confirm password
	    echo >&2
	    read -s -p "Please reenter password: " P2
	    if [ "$P_INPUT" != "$P2" ]; then
		echo >&2
		echo "Error: passwords did not match, please try again." >&2
		P_INPUT="" # clear to re-prompting
	    fi
	fi
    done

    echo "$P_INPUT" # the password or the empty string

    unset P_PROMPT
    unset P_INPUT
}

#----------------------------------------------------------------
# Process command line

SHORT_OPTS="bc:fGs:h"
if ! getopt $SHORT_OPTS "$@" >/dev/null; then
    echo "$PROG: usage error (use -h for help)" >&2
    exit 2
fi
ARGS=`getopt $SHORT_OPTS "$@"`
eval set -- $ARGS

## Process parsed options

BATCH_MODE=
CONFIG_FILE=
GEO_SEARCH=y
FORCE_REINSTALL=
CMD_SERVERNAME=
HELP=

while [ $# -gt 0 ]; do
    case "$1" in
        -b) BATCH_MODE=y;;
	-c) CONFIG_FILE="$2"; shift;;
	-G) GEO_SEARCH=n;;
	-f) FORCE_REINSTALL=y;;
        -s) CMD_SERVERNAME="$2"; shift;;
        -h) HELP='y';;
	--) break;;
    esac
    shift
done

if [ -n "$HELP" ]; then
    cat <<EOF
Usage: $PROG [options]
Options:
  -b             run in batch mode
  -c configFile  load parameters from file
  -s server      the name or IP address of this machine
  -G             install without geographical search (for Ubuntu < 15.x)
  -f             force re-install
  -h             show this help message
EOF
    exit 0
fi

if [ $# -gt 1 ]; then
    echo "$PROG: too many arguments (-h for help)" >&2
    exit 2
fi

#----------------------------------------------------------------
# Checks

# Script run with root privileges (either as root or with sudo)

if [ $(id -u) -ne 0 ]; then
   tput setaf 1
   echo "$PROG: error: this script was run without root privileges" 1>&2
   tput sgr0
   exit 1
fi

# Already installed?

if [ "$FORCE_REINSTALL" != 'y' ]; then
    # Reinstall not explicity allowed, check if not already installed

    if dpkg --status $MYSQL_SERVER_PKG >/dev/null 2>&1; then
	echo "$PROG: $MYSQL_SERVER_PKG already installed (use -f to force reinstall)" >&2
	exit 1
    fi

    if [ -e "$TCAT_DIR" ]; then
	echo "$PROG: TCAT already installed (use -f to force reinstall)" >&2
	exit 1
    fi

    if [ -L /etc/apparmor.d/disable/usr.sbin.mysqld ]; then
	echo "$PROG: apparmor configured (use -f to forece reinstall)" >&2
	exit 1
    fi
fi

# Expected OS version

if [ ! -f '/etc/issue' ]; then
    echo "$PROG: error: system not running Ubuntu: /etc/issue missing" >&2
    exit 1
fi

# Example: "Ubuntu 14.04.3 LTS \n \l"
# -> UBUNTU_VERSION=14.04.3, UBUNTU_VERSION_MAJOR=14
UBUNTU_VERSION=`awk '{print $2}' /etc/issue`
UBUNTU_VERSION_MAJOR=$(echo $UBUNTU_VERSION |
			      awk -F . '{if (match($1, /^[0-9]+$/)) print $1}')

if [ -z "$UBUNTU_VERSION_MAJOR" ]; then
    echo "$PROG: error: system not running Ubuntu: $UBUNTU_VERSION" >&2
    exit 1;
fi

if [ "$UBUNTU_VERSION_MAJOR" -lt 15 ]; then
    echo "Warning: geographical search not available on Ubuntu $UBUNTU_VERSION < 15.x"

    if [ "$GEO_SEARCH" = 'y' ]; then
	if [ "$BATCH_MODE" = 'y' ]; then
	    echo "$PROG: aborted (use -G to install without geographical search)" >&2
	    exit 1
	else
	    if ! promptYN "Continue install without geographical search"; then
		echo "$PROG: aborted by user"
		exit 1
	    fi
	fi
    fi
fi

#----------------------------------------------------------------
# Load config file (if any)

if [ -n "$CONFIG_FILE" ]; then
    if [ ! -f "$CONFIG_FILE" ]; then
	echo "$PROG: config file not found: $CONFIG_FILE" >&2
	exit 1
    fi
    . "$CONFIG_FILE" # Source the config file
fi

if [ -n "$CMD_SERVERNAME" ]; then
    SERVERNAME="$CMD_SERVERNAME" # command line value overrides config file
fi

#----------------------------------------------------------------
# Fix default server name

if [ -z "$SERVERNAME" ]; then
    # Try to find a usable default value for the server name.
    #
    # This is not reliable, but is better than confused novice users not
    # knowing what value to use.

    # Extract all IPv4 IP address provided by the "ip" command
    ADDRS=
    for STR in $(ip -o -4 addr | awk '{print $4}'); do
	# "ip -o -4 addr" produces lines like "1: lo inet 127.0.0.1/8 ..."
	ADDRS="$ADDRS $(echo $STR | awk -F '/' '{print $1}')"
    done
    unset STR

    # Remove loopback addresses 127.x.x.x: these are no better than "localhost"
    NEW_ADDRS=
    for ADR in $ADDRS; do
	if ! echo $ADR | grep ^127\\. >/dev/null ; then
	    NEW_ADDRS="$NEW_ADDRS $ADR"
	fi
    done
    ADDRS=$NEW_ADDRS

    # Remove all private IP addresses (192.168., 10.x., 172.16. to 172.31.)
    NEW_ADDRS=
    for ADR in $ADDRS; do
	if ! echo $ADR | grep ^192.168\\. >/dev/null -a; then
	    if ! echo $ADR | grep ^10\\. >/dev/null -a; then
		if ! echo $ADR | grep ^172.1[6789]\\. >/dev/null -a; then
		    if ! echo $ADR | grep ^172.2[0-9]\\. >/dev/null -a; then
			if ! echo $ADR | grep ^172.3[01]\\. >/dev/null -a; then
			    NEW_ADRS="$NEW_ADDRS $ADR"
			fi
		    fi
		fi
	    fi
	fi
    done
    if [ -n "$NEW_ADDRS" ]; then
	# There are some non-private IP addresses, use them.
	# Otherwise, stay with the list that (might) have private IP address
	ADDRS=$NEW_ADDRS
    fi

    if [ -n "$ADDRS" ]; then
	# Pick first address in remaining list
	SERVERNAME=$(echo $ADDRS | cut -d ' ' -f 1)
    else
	# Last resort default value. Not useful, but it is a valid value!
	SERVERNAME=localhost
    fi

    unset ADR
    unset ADDRS
    unset NEW_ADDRS
fi

#----------------------------------------------------------------
# Get parameters

if [ "$BATCH_MODE" != "y" ]; then
    echo
    echo "Installer for DMI-TCAT"
    echo "----------------------"
fi

FIRST_PASS=y
while [ "$BATCH_MODE" != "y" ]; do

    if [ -n "$CONSUMERKEY" -a -n "$CONSUMERSECRET" -a \
	 -n "$USERTOKEN" -a -n "$USERSECRET" ]; then
	# Confirm values
	#
	# Parameters are ready, so ask user to confirm they are acceptable.
	# Usually they won't be ready the first time through, so this will be
	# skipped and go straight to prompting for the parametes. But if they
	# were all set (by a config file) the user will be asked to confirm
	# them first.

	echo
	echo "Install DMI TCAT with these parameters:"

	echo "  Twitter consumer key: $CONSUMERKEY"
	echo "  Twitter consumer secret: $CONSUMERSECRET"
	echo "  Twitter user token: $USERTOKEN"
	echo "  Twitter user secret: $USERSECRET"

	case "$CAPTURE_MODE" in
	    1) echo "  Tweet capture mode: track phrases and keywords";;
	    2) echo "  Tweet capture mode: follow Twitter users";;
	    3) echo "  Tweet capture mode: one percent sample of all Twitter traffic";;
	    *) echo "  Tweet capture mode: $CAPTURE_MODE (invalid value)"; exit 1;;
	esac

	echo "  Expands URLs in tweets: $URLEXPANDYES"
	echo "  Server: $SERVERNAME (TCAT will be at http://$SERVERNAME/)"
	echo "  Advanced parameters:"
	echo "    Shell user: $SHELLUSER"
	echo "    Shell group: $SHELLGROUP"
	echo "    MySQL TCAT database account: $TCATMYSQLUSER"
	echo "    MySQL memory profile auto-configure: $DB_CONFIG_MEMORY_PROFILE"
	echo "    TCAT admin login name: $TCATADMINUSER"
	echo "    TCAT standard login name: $TCATUSER"
	echo
	if promptYN "Use these values (or \"q\" to quit)"; then
	    BATCH_MODE=n # actually this is redundant: the break will exit the loop
	    break; # exit while-loop to start installing
	fi
	echo
    fi

    # Twitter credentials

    if [ "$FIRST_PASS" = 'y' ]; then
	# First time through: provide extra help information
	echo
	echo "Twitter credentials for the Twitter API are needed to capture tweets."
	echo "These can be obtained from <https://apps.twitter.com>."
	echo "You will need an application's Consumer Key and its Consumer Secret,"
	echo "and an Access Token and its Access Token Secret."
	echo
    fi

    CONSUMERKEY=$(promptStr "Twitter consumer key" $CONSUMERKEY)
    CONSUMERSECRET=$(promptStr "Twitter consumer secret" $CONSUMERSECRET)
    USERTOKEN=$(promptStr "Twitter user token" $USERTOKEN)
    USERSECRET=$(promptStr "Twitter user secret" $USERSECRET)

    # Tweet capture mode

    if [ "$FIRST_PASS" = 'y' ]; then
	echo
	echo "Choose a tweet capture mode for TCAT to use:"
	echo "1. Track pharases and keywords."
	echo "2. Follow Twitter users."
	echo "3. Capture a 1% sample of all Twitter traffic."
	echo
    fi

    DEFAULT=$CAPTURE_MODE
    CAPTURE_MODE=
    while [ -z "$CAPTURE_MODE" ]; do
	read -p "Tweet capture mode (1=phrases/keywords, 2=users, 3=1% traffic) [$DEFAULT]: " CAPTURE_MODE
	if [ -z "$CAPTURE_MODE" ]; then
	    CAPTURE_MODE=$DEFAULT
	fi
	if [ "$CAPTURE_MODE" != '1' -a \
	    "$CAPTURE_MODE" != '2' -a \
	    "$CAPTURE_MODE" != '3' ] ; then
	    echo "Invalid value (expecting 1, 2 or 3)"
	    CAPTURE_MODE= # clear to reprompt
	fi
    done

    # Expand URLs in tweets

    if [ "$FIRST_PASS" = 'y' ]; then
	echo
	echo "The URL expander periodically expands URLs in Tweets by fully"
	echo "resolving their addresses. This improves the searchability of"
	echo "the datasets, but increases the network traffic."
	echo
    fi

    if promptYN "Install URL expander (better search; more network traffic)" $URLEXPANDYES; then
	URLEXPANDYES=y
    else
	URLEXPANDYES=n
    fi

    # Server name

    if [ "$FIRST_PASS" = 'y' ]; then
	echo
	echo "The name of the server is used to configure the Apache server."
	echo "It should be a hostname or IP address for this machine, where"
	echo "TCAT is being installed on. TCAT will be accessed via a URL"
	echo "containing the server name."
	echo
    fi

    DEFAULT=$SERVERNAME
    SERVERNAME=
    while [ -z "$SERVERNAME" ]; do
	PROMPT="Server name (hostname or IP address)"
	if [ -n "$DEFAULT" ]; then
	    PROMPT="$PROMPT [$DEFAULT]"
	fi
	read -p "$PROMPT: " SERVERNAME
	if [ -z "$SERVERNAME" -a -n "$DEFAULT" ]; then
	    SERVERNAME=$DEFAULT
	fi

	if [ -n "$SERVERNAME" ]; then
	    if ! ping -c 1 -n "$SERVERNAME" >/dev/null 2>&1; then
		if ! promptYN "Cannot ping \"$SERVERNAME\". Use anyway"; then
		    SERVERNAME= # clear value to ask again
		fi
	    fi
	fi
    done

    # Advanced parameters

    if [ "$FIRST_PASS" = 'y' ]; then
	echo
	echo "Advanced pramerters for the file owner, MySQL accounts and TCAT"
	echo "Web logins can be set. Normally, the defaults can be used."
	echo
    fi

    if promptYN "Edit advanced parameters" 'n'; then
	# Prompt user for advanced parameters.
	# Most users don't need to change these.

	# Shell user

	if [ "$FIRST_PASS" = 'y' ]; then
	    echo
	    echo "The Unix user that owns the TCAT files."
	    echo "This user will be created if it does not exist."
	    echo
	fi

	DEFAULT=$SHELLUSER
	SHELLUSER=
	while [ -z "$SHELLUSER" ]; do
	    read -p "Shell user for TCAT files [$DEFAULT]: " SHELLUSER
	    if [ -z "$SHELLUSER" ]; then
		SHELLUSER="$DEFAULT"
	    fi
	    if [ "$SHELLUSER" = 'root' ]; then
		echo "Error: shell user cannot be \"root\"" >&2
		SHELLUSER= # clear value to ask again
	    fi
	done

	# Shell group

	if [ "$FIRST_PASS" = 'y' ]; then
	    echo
	    echo "The Unix group that owns the TCAT files."
	    echo "Normally this is the same the Unix user's group."
	    echo "The group will be created if it does not exist."
	    echo
	fi

	DEFAULT=$SHELLUSER # defaults to same as user
	SHELLGROUP=
	if [ -z "$SHELLGROUP" ]; then
	    read -p "Shell group name for TCAT files [$DEFAULT]: " SHELLGROUP
	    if [ -z "$SHELLGROUP" ]; then
		SHELLGROUP=$DEFAULT
	    fi
	fi

	# MySQL admin user's password

	if [ "$FIRST_PASS" = 'y' ]; then
	    echo
	    echo "The password for the MySQL admin account (called \"root\")."
	    echo "Leave this blank to use a long randomly generated password (recommended)."
	    echo
	fi

	DBPASS=`promptPassword "MySQL admin account password"`; echo

	# MySQL TCAT user

	if [ "$FIRST_PASS" = 'y' ]; then
	    echo
	    echo "The name of the MySQL account that owns the TCAT database."
	    echo
	fi

	DEFAULT=$TCATMYSQLUSER
	TCATMYSQLUSER=
	while [ -z "$TCATMYSQLUSER" ]; do
	    read -p "MySQL TCAT account name [$DEFAULT]: " TCATMYSQLUSER
	    if [ -z "$TCATMYSQLUSER" ]; then
		TCATMYSQLUSER="$DEFAULT"
	    fi
	    if [ "$TCATMYSQLUSER" = 'root' ]; then
		echo "Error: the MySQL admin account cannot be the TCAT database account."
		TCATMYSQLUSER= # clear to ask again
	    fi
	done

	if [ "$FIRST_PASS" = 'y' ]; then
	    echo
	    echo "The password for the MySQL TCAT database account (\"$TCATMYSQLUSER\")."
	    echo "Leave this blank to use a long randomly generated password (recommended)."
	    echo
	fi

	TCATMYSQLPASS=`promptPassword "MySQL TCAT account password"`; echo

	# MySQL memory profile

	if [ "$FIRST_PASS" = 'y' ]; then
	    echo
	    echo "This installer can attempt to configure the MySQL server's"
	    echo "memory profile to improve performance. But this is only"
	    echo "recommended if this machine is dedicated as a server that"
	    echo "only runs TCAT."
	    echo
	fi

	if promptYN "Attempt to optimize MySQL memory profile" $DB_CONFIG_MEMORY_PROFILE; then
	    DB_CONFIG_MEMORY_PROFILE=y
	else
	    DB_CONFIG_MEMORY_PROFILE=n
	fi

	# TCAT Web-UI admin users and passwords

	if [ "$FIRST_PASS" = 'y' ]; then
	    echo
	    echo "Login name and password for accessing the TCAT capture setup Web pages."
	    echo
	fi

	TCATADMINUSER=$(promptStr "TCAT admin login name" $TCATADMINUSER)
	TCATADMINPASS=$(promptPassword "TCAT admin login password"); echo

	if [ "$FIRST_PASS" = 'y' ]; then
	    echo
	    echo "Login name and password for accessing the TCAT analysis Web pages."
	    echo
	fi

	TCATUSER=$(promptStr "TCAT standard login name" $TCATUSER)
	TCATPASS=$(promptPassword "TCAT standard login password"); echo

    fi # end of advanced parameters

    FIRST_PASS=n
done # end of interactive confirm/prompt loop

#----------------------------------------------------------------
# Parameter checks.
#
# Essential for parameters loaded from config file and/or batch mode operation,
# which bypasses the interactive input checks.

if [ "$CAPTURE_MODE" -ne 1 -a \
     "$CAPTURE_MODE" -ne 2 -a \
     "$CAPTURE_MODE" -ne 3 ] ; then
    echo "$PROG: Invalid CAPTURE_MODE (expecting 1, 2 or 3): $CAPTURE_MODE" >&2
    exit 1
fi

if [ -z "$CONSUMERKEY" ]; then
    echo "$PROG: Twitter CONSUMERKEY cannot be blank" >&2
    exit 1
fi
if [ -z "$CONSUMERSECRET" ]; then
    echo "$PROG: Twitter CONSUMERSECRET cannot be blank" >&2
    exit 1
fi
if [ -z "$USERTOKEN" ]; then
    echo "$PROG: Twitter USERTOKEN cannot be blank" >&2
    exit 1
fi
if [ -z "$USERSECRET" ]; then
    echo "$PROG: Twitter USERSECRET cannot be blank" >&2
    exit 1
fi

#----------------------------------------------------------------
# Generate random passwords, if they are a blank string.

apt-get -qq install -y openssl

if [ -z "$DBPASS" ]; then
    DBPASS=`openssl rand -base64 32 | tr -c -d 0-9A-Za-z | tr -d O01iIl`
fi

if [ -z "$TCATMYSQLPASS" ]; then
    TCATMYSQLPASS=`openssl rand -base64 32 | tr -c -d 0-9A-Za-z | tr -d O01iIl`
fi

if [ -z "$TCATADMINPASS" ]; then
    TCATADMINPASS=`openssl rand -base64 32 | tr -c -d 0-9A-Za-z | tr -d O01iIl`
    TCATADMINPASS_GENERATED=y
else
    TCATPASS_GENERATED=
fi

if [ -z "$TCATPASS" ]; then
    TCATPASS=`openssl rand -base64 32 | tr -c -d 0-9A-Za-z | tr -d O01iIl`
    TCATPASS_GENERATED=y
else
    TCATPASS_GENERATED=
fi

#----------------------------------------------------------------
# Install

if [ "$BATCH_MODE" != 'y' ]; then
    echo ""
    echo "Thank you. Now starting installation ..."
    echo ""
fi

# Clear any existing TCAT crontab references
# TODO: are these needed? /etc/crontab is not written to (anymore?)
echo "" > /etc/cron.d/tcat
# These lines used to be written to the global /etc/crontab file
sed -i 's/^# Run TCAT controller every minute$//g' /etc/crontab
sed -i 's/^.*cd \/var\/www\/dmi-tcat\/capture\/stream\/; php controller.php.*$//g' /etc/crontab
sed -i 's/^# Run DMI-TCAT URL expander every hour$//g' /etc/crontab
sed -i 's/^.*cd \/var\/www\/dmi-tcat\/helpers; sh urlexpand.sh.*$//g' /etc/crontab

#----------------------------------------------------------------
# Undo things that prevents a re-install
#
# WARNING: reinstalls are experimental. It certainly does not undo
# everything that was previously installed.
#
# If these were present, the -f option must have been specified for the
# script to get this far. So the user is ok to remove them.

# MySQL already installed?

if dpkg --status $MYSQL_SERVER_PKG >/dev/null 2>&1; then
    # Remove MySQL and re-install it so new root password gets used,
    # otherwise this script will need to be much more complex.
    tput bold
    echo "Uninstalling MySQL ..."
    tput sgr0
    echo

    apt-get -y purge $MYSQL_SERVER_PKG

    rm -rf /var/lib/mysql
    echo
fi

# TCAT directory already exists

if [ -e "$TCAT_DIR" ]; then
    rm -r "$TCAT_DIR"
fi

# Link already exists

if [ -L /etc/apparmor.d/disable/usr.sbin.mysqld ]; then
    rm /etc/apparmor.d/disable/usr.sbin.mysqld
fi

#----------------------------------------------------------------

tput bold
echo "Installing basic prerequisites ..."
tput sgr0
echo ""

# apt-get update
# apt-get -y upgrade
apt-get -y install wget debsums

echo
tput bold
echo "Installing MySQL server and Apache webserver ..."
tput sgr0
echo ""

# Set MySQL root password to avoid prompt during "apt-get install" MySQL server

echo "mysql-server mysql-server/root_password password $DBPASS" |
debconf-set-selections
echo "mysql-server mysql-server/root_password_again password $DBPASS" |
debconf-set-selections

# Install MySQL

echo "$PROG: installing MySQL"
apt-get -y install $MYSQL_SERVER_PKG mysql-client-5.6

# Install Apache

echo "$PROG: installing Apache and PHP"
apt-get -y install \
    apache2-mpm-prefork apache2-utils \
    libapache2-mod-php5 \
    php5-mysql php5-curl php5-cli php-patchwork-utf8
if [ "$UBUNTU_VERSION_MAJOR" -ge 15 ]; then
    echo "$PROG: installing PHP module for geographical search"
    apt-get -y install php5-geos
    php5enmod geos
fi

# Installation and autoconfiguration of MySQL will not work with Apparmor profile enabled

ln -s /etc/apparmor.d/usr.sbin.mysqld /etc/apparmor.d/disable/
/etc/init.d/apparmor restart 

echo ""
tput bold
echo "Downloading DMI-TCAT from github ..."
tput sgr0
echo ""

apt-get -qq install -y git

git clone https://github.com/digitalmethodsinitiative/dmi-tcat.git /var/www/dmi-tcat

echo ""
tput bold
echo "Preliminary DMI-TCAT configuration ..."
tput sgr0
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

chown -R $SHELLUSER:$SHELLGROUP /var/www/dmi-tcat/
cd /var/www/dmi-tcat
mkdir analysis/cache logs proc
chown $WEBUSER:$WEBGROUP analysis/cache
chmod 755 analysis/cache
chown $SHELLUSER:$SHELLGROUP logs proc
chmod 755 logs
chmod 755 proc

echo ""
tput bold
echo "Configuring Apache 2 ..."
tput sgr0
echo ""

# Save Web UI passwords

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
echo "$PROG: login details saved: $FILE"

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
echo "$PROG: login details saved: $FILE"

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
EOF
read -d '' APACHECONF3 <<"EOF"
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
echo "            Require user $TCATADMINUSER $TCATUSER" >> /etc/apache2/sites-available/tcat.conf
echo "        $APACHECONF3"  >> /etc/apache2/sites-available/tcat.conf
a2dissite 000-default
a2ensite tcat.conf

CFG="$TCAT_DIR/config.php"

cp "$TCAT_DIR/config.php.example" "$CFG"
htpasswd -b -c /etc/apache2/passwords $TCATUSER $TCATPASS
sed -i "s/define(\"ADMIN_USER\", \"admin\");/define(\"ADMIN_USER\", \"$TCATADMINUSER\");/g" /var/www/dmi-tcat/config.php
htpasswd -b /etc/apache2/passwords $TCATADMINUSER $TCATADMINPASS
chown $SHELLUSER:$WEBGROUP /etc/apache2/passwords
a2enmod rewrite
/etc/init.d/apache2 restart

echo ""
tput bold
echo "Configuring MySQL server for TCAT ..."
tput sgr0
echo ""

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
echo "$PROG: account details saved: $FILE"

# Save MySQL TCAT database user's password

FILE="${MYSQL_CNF_PREFIX}${TCATMYSQLUSER}${MYSQL_CNF_SUFFIX}"
touch "$FILE"
chown mysql:mysql "$FILE"
chmod 600 "$FILE" # secure file before writing password to it
cat > "$FILE" <<EOF
# MySQL/MariaDB config
[client]
user=$TCATMYSQLUSER
password="${TCATMYSQLPASS}"
EOF
echo "$PROG: account details saved: $FILE"

echo "CREATE DATABASE IF NOT EXISTS twittercapture DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci;" | mysql --defaults-file="$MYSQL_USER_ADMIN_CNF"
echo "GRANT CREATE, DROP, LOCK TABLES, ALTER, DELETE, INDEX, INSERT, SELECT, UPDATE, CREATE TEMPORARY TABLES ON twittercapture.* TO '$TCATMYSQLUSER'@'localhost' IDENTIFIED BY '$TCATMYSQLPASS';" | mysql --defaults-file="$MYSQL_USER_ADMIN_CNF"
echo "FLUSH PRIVILEGES;" | mysql --defaults-file="$MYSQL_USER_ADMIN_CNF"
sed -i "s/dbuser = \"\"/dbuser = \"$TCATMYSQLUSER\"/g" /var/www/dmi-tcat/config.php
sed -i "s/dbpass = \"\"/dbpass = \"$TCATMYSQLPASS\"/g" /var/www/dmi-tcat/config.php
sed -i "s/example.com\/dmi-tcat\//$SERVERNAME\//g" /var/www/dmi-tcat/config.php

if [ "$URLEXPANDYES" == "y" ]; then
   echo ""
   tput bold
   echo "Installation and configuration of the URL expander ..."
   tput sgr0
   echo ""
   apt-get install -y build-essential libevent-dev python-all-dev python-mysqldb python-setuptools python-pip
   easy_install greenlet
   easy_install gevent 
   pip install requests
   CRONLINE="0 *     * * *   $SHELLUSER   (cd /var/www/dmi-tcat/helpers; sh urlexpand.sh)"
   echo "" >> /etc/cron.d/tcat
   echo "# Run DMI-TCAT URL expander every hour" >> /etc/cron.d/tcat
   echo "$CRONLINE" >> /etc/cron.d/tcat
fi

echo ""
tput bold
echo "Activating TCAT controller in cron ..."
tput sgr0
echo ""

CRONLINE="* *     * * *   $SHELLUSER   (cd /var/www/dmi-tcat/capture/stream/; php controller.php)"
echo "" >> /etc/cron.d/tcat
echo "# Run TCAT controller every minute" >> /etc/cron.d/tcat
echo "$CRONLINE" >> /etc/cron.d/tcat

echo ""
tput bold
echo "Setting up logfile rotation ..."
tput sgr0
echo ""

read -d '' LOGROTATE <<"EOF"
/var/www/dmi-tcat/logs/controller.log /var/www/dmi-tcat/logs/track.error.log /var/www/dmi-tcat/logs/follow.error.log /var/www/dmi-tcat/logs/onepercent.error.log  
{ 
   weekly  
   rotate 8  
   compress  
   delaycompress  
   missingok  
   ifempty
EOF
echo "$LOGROTATE" > /etc/logrotate.d/dmi-tcat
echo "   create 644 $SHELLUSER $SHELLGROUP"  >> /etc/logrotate.d/dmi-tcat
echo "}" >> /etc/logrotate.d/dmi-tcat

case "$CAPTURE_MODE" in
    1)  echo "Using role: track" ;;
    2)  echo "Using role: follow"
        sed -i "s/array(\"track\")/array(\"follow\")/g" "$CFG" ;;
    3)  echo "Using role: onepercent"
        sed -i "s/array(\"track\")/array(\"onepercent\")/g" "$CFG" ;;
    *)  echo "$PROG: internal error: bad capture mode: $CAPTURE_MODE" >&2
	exit 3 ;;
esac

sed -i "s/^\$twitter_consumer_key = \"\";/\$twitter_consumer_key = \"$CONSUMERKEY\";/g" /var/www/dmi-tcat/config.php
sed -i "s/^\$twitter_consumer_secret = \"\";/\$twitter_consumer_secret = \"$CONSUMERSECRET\";/g" /var/www/dmi-tcat/config.php
sed -i "s/^\$twitter_user_token = \"\";/\$twitter_user_token = \"$USERTOKEN\";/g" /var/www/dmi-tcat/config.php
sed -i "s/^\$twitter_user_secret = \"\";/\$twitter_user_secret = \"$USERSECRET\";/g" /var/www/dmi-tcat/config.php

# Check if the current MySQL configuration is the system default one
CHANGEDMYCNF=`debsums -ce | grep -c -e "/etc/mysql/my.cnf"`
if [ "$CHANGEDMYCNF" == "0" ]; then
    if [ "$DB_CONFIG_MEMORY_PROFILE" = "y" ]; then
        echo ""
        tput bold
        echo "Attempting to adjust MySQL server profile ..."
        tput sgr0
        echo ""
        MAXMEM=`free -m | head -n 2 | tail -n 1 | tr -s ' ' | cut -d ' ' -f 2`
        # Make this an integer, and non-empty for bash
        MAXMEM=${MAXMEM//[^[:digit:]]/0}
        MAXMEM=${MAXMEM//^$/0}
        echo "Maximum machine memory detected: $MAXMEM Mb"
        if [ "$MAXMEM" -lt "1024" ]; then
            echo "This machine has a limited ammount of memory; leaving system defaults in place."
        else
            echo "[mysqld]" > /etc/mysql/conf.d/tcat-autoconfigured.cnf
            # Set the key buffer to 1/3 of system memory
            SIZE=$(($MAXMEM/3))
            echo "key_buffer              = $SIZE""M" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf
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
            echo "Restarting service ... "
            /etc/init.d/mysql restart
        fi
    fi
fi

echo ""
tput bold
echo "Done: TCAT installed"
tput sgr0
echo ""

echo "Please visit this TCAT installation at these URLs:"
echo "  http://$SERVERNAME/capture/"
echo "  http://$SERVERNAME/analysis/"
echo
echo "TCAT administrator login (for capture setup and analysis):"
echo "  Username: $TCATADMINUSER"
if [ "$TCATADMINPASS_GENERATED" = 'y' ]; then
    echo "  Password: $TCATADMINPASS"
fi
echo
echo "TCAT standard login (for analysis only):"
echo "  Username: $TCATUSER"
if [ "$TCATPASS_GENERATED" = 'y' ]; then
    echo "  Password: $TCATPASS"
fi
echo
echo "If you ever need them, the usernames and passwords have been saved."
echo "TCAT logins have been saved to ${TCAT_CNF_PREFIX}*${TCAT_CNF_SUFFIX}"
echo "MySQL accounts have been saved to ${MYSQL_CNF_PREFIX}*${MYSQL_CNF_SUFFIX}"
echo
echo "The following steps are recommended, but not mandatory"
echo ""
echo " * Set-up your systems e-mail (sendmail)"
echo ""

exit 0
