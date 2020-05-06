#!/bin/bash
#
# Installer for DMI-TCAT on Linux.
#
# This script prompts the user interactively for parameters, or it can
# be run in batch mode (with the -b option).
#
# The paramaters can also be loaded from a configuration file (with
# the -c option).  The format of the config file is a Bash shell file
# that sets values for some/all of the parameter environment variables
# from the top section of this file (since the config file is simply
# sourced by this script).
#
# To be able to run in batch mode, a config file *must* minimally
# provide values for CONSUMERKEY, CONSUMERSECRET, USERTOKEN and
# USERSECRET.
#
# Run with -h for help.
#
# Supported distributions:
# - Ubuntu 18.04
# - Debian 9.*
#
#----------------------------------------------------------------

# TCAT Installer parameters

# Convention for Boolean: 'y' true; all other values (e.g. blank or 'n') false

# Twitter API credentials and capture options

CONSUMERKEY=
CONSUMERSECRET=
USERTOKEN=
USERSECRET=

CAPTURE_MODE=1 # 1=track phrases/keywords, 2=follow users, 3=onepercent

URLEXPANDYES=y # install URL Expander or not

# Apache

SERVERNAME= # should default to this machine's IP address (-s overrides)

# TCAT
TCAT_AUTO_UPDATE=0 # 0=off, 1=trivial, 2=substantial, 3=expensive

# Unix user and group to own the TCAT files

SHELLUSER=tcat
SHELLGROUP=tcat

# MySQL (blank password means randomly generate it)

DBPASS= # password for the MySQL "root" administrator account

TCATMYSQLUSER=tcatdbuser
TCATMYSQLPASS=

DB_CONFIG_MEMORY_PROFILE=y

LETSENCRYPT=n

# TCAT Web user interface logins (blank password means randomly generate it)

TCATADMINUSER=admin
TCATADMINPASS=

TCATUSER=tcat
TCATPASS=

# End of TCAT installer parameters

#----------------------------------------------------------------
# Error checking

PROG=`basename "$0"`

# Trap to abort script when a command fails.

trap "echo $PROG: command failed: install aborted; exit 3" ERR
# Can't figure out which command failed? Run using "bash -x" or uncomment:
# set -x # write each command to stderr before it is exceuted

# set -e # fail if a command fails (this works in sh, since trap ERR does not)

set -u # fail on attempts to expand undefined variables

#----------------------------------------------------------------
# Other constants (these should not need changing)

# Release of DMI-TCAT to install
# These can be changed using the -R and -B command line options

TCAT_GIT_REPOSITORY=https://github.com/digitalmethodsinitiative/dmi-tcat.git
TCAT_GIT_BRANCH= # empty string means use shallow clone of 'master' branch
                 # non-empty string means use a full clone of the named branch

# Where the MySQL defaults files are written

MYSQL_CNF_PREFIX='/etc/mysql/conf.d/tcat-'
MYSQL_CNF_SUFFIX='.cnf'

# Where the TCAT logins are written

TCAT_CNF_PREFIX='/etc/apache2/tcat-login-'
TCAT_CNF_SUFFIX='.txt'

# Where the TCAT logins for Apache Basic Authentication credentials are written

APACHE_PASSWORDS_FILE=/etc/apache2/tcat.htpasswd

# Where to install TCAT files

TCAT_DIR=/var/www/dmi-tcat

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

SHORT_OPTS="B:bc:fGhlR:s:U"
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
CMD_SERVERNAME=
DO_UPDATE_UPGRADE=y
DO_SAVE_TCAT_LOGINS=
FORCE_INSTALL=
HELP=

while [ $# -gt 0 ]; do
    case "$1" in
        -B) TCAT_GIT_BRANCH="$2"; shift;;
        -b) BATCH_MODE=y;;
	-c) CONFIG_FILE="$2"; shift;;
	-f) FORCE_INSTALL=y;;
	-G) GEO_SEARCH=n;;
	-l) DO_SAVE_TCAT_LOGINS=y;;
        -R) TCAT_GIT_REPOSITORY="$2"; shift;;
        -s) CMD_SERVERNAME="$2"; shift;;
	-U) DO_UPDATE_UPGRADE=n;;
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

  -l             save a copy of TCAT login username and passwords in plain text

  -G             install without geographical search (for Ubuntu < 15.x)
  -U             do not run apt-get update and apt-get upgrade

  -h             show this help message

  -R repoURL     GIT repository for TCAT (default: official DMI-TCAT on GitHub)
  -B branch      GIT branch in repository to install (default: master)
  -f             force install attempt on unsupported distributions
                 (for developers only: install or TCAT will probably not work)
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

# Expected OS version

OS=`uname -s`
if [ $OS != 'Linux' ]; then
    echo "$PROG: error: unsupported operating system: not Linux: $OS" >&2
    exit 1
fi

if ! which lsb_release >/dev/null 2>&1; then
    echo "$PROG: error: unsupported distribution: missing lsb_release" >&2
    exit 1
fi

DISTRIBUTION_ID=`lsb_release -i -s`

if [ "$DISTRIBUTION_ID" = 'Ubuntu' ]; then
    UBUNTU_VERSION=`lsb_release -r -s`
    DEBIAN_VERSION=
    UBUNTU_VERSION_MAJOR=$(echo $UBUNTU_VERSION |
	awk -F . '{if (match($1, /^[0-9]+$/)) print $1}')

    if [ -z "$UBUNTU_VERSION_MAJOR" ]; then
	echo "$PROG: error: unexpected Ubuntu version: $UBUNTU_VERSION" >&2
	exit 1
    fi
    if [ "$UBUNTU_VERSION" != '18.04' ]; then
	if [ -z "$FORCE_INSTALL" ]; then
	    echo "$PROG: error: unsupported distribution: Ubuntu $UBUNTU_VERSION" >&2
	    exit 1
	fi
    fi

    if [ "$UBUNTU_VERSION_MAJOR" -lt 15 ]; then
	echo "Warning: no geographical search on Ubuntu $UBUNTU_VERSION < 15.x"

	if [ "$GEO_SEARCH" = 'y' ]; then
	    if [ "$BATCH_MODE" = 'y' ]; then
		echo "$PROG: aborted (use -G to leave out geo search)" >&2
		exit 1
	    else
		if ! promptYN "Continue install without geographical search";
		then
		    echo "$PROG: aborted by user"
		    exit 1
		fi
	    fi
	fi
    fi

elif [ "$DISTRIBUTION_ID" = 'Debian' ]; then
    DEBIAN_VERSION=`lsb_release -r -s`
    UBUNTU_VERSION=
    DEBIAN_VERSION_MAJOR=$(echo $DEBIAN_VERSION |
	awk -F . '{if (match($1, /^[0-9]+$/)) print $1}')

    if [ -z "$DEBIAN_VERSION" ]; then
	echo "$PROG: error: unexpected Debian version: $DEBIAN_VERSION" >&2
	exit 1
    fi
    if [ -a "$DEBIAN_VERSION_MAJOR" != '9' ]; then
	if [ -z "$FORCE_INSTALL" ]; then
	    echo "$PROG: error: unsupported distribution: Debian $DEBIAN_VERSION" >&2
	    exit 1
	fi
    fi

else
    echo "$PROG: error: unsupported distribution: $DISTRIBUTION_ID" >&2
    exit 1
fi

# Already installed?

if [ -e "$TCAT_DIR" ]; then
    echo "$PROG: cannot install: TCAT already installed: $TCAT_DIR" >&2
    exit 1
fi

# Install Debian keyring

if [ -n "$DEBIAN_VERSION" ]; then
    apt-get install -y debian-archive-keyring debian-keyring
fi

# MySQL server package name for apt-get

if [ -n "$UBUNTU_VERSION" ]; then
    UBUNTU_MYSQL_SVR_PKG=mariadb-server

    if dpkg --status $UBUNTU_MYSQL_SVR_PKG >/dev/null 2>&1; then
	echo "$PROG: cannot install: $UBUNTU_MYSQL_SVR_PKG already installed" >&2
	exit 1
    fi

elif [ -n "$DEBIAN_VERSION" ]; then
    if dpkg --status mariadb-server >/dev/null 2>&1; then
	echo "$PROG: cannot install: mariadb-server already installed" >&2
	exit 1
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
	echo "  Server: $SERVERNAME (TCAT will be at http://$SERVERNAME/ or https://$SERVERNAME/ when using Let's Encrypt)"

	if [ $TCAT_AUTO_UPDATE = '0' ]; then
	    echo "  Automatically update TCAT: (not enabled)"
	else
	    echo "  Automatically update TCAT: enabled, level=$TCAT_AUTO_UPDATE"
	fi

	echo "  Advanced parameters:"
	echo "    Shell user: $SHELLUSER"
	echo "    Shell group: $SHELLGROUP"
	echo "    MySQL TCAT database account: $TCATMYSQLUSER"
	echo "    MySQL memory profile auto-configure: $DB_CONFIG_MEMORY_PROFILE"
	echo "    TCAT admin login name: $TCATADMINUSER"
	echo "    TCAT standard login name: $TCATUSER"
	echo
	if promptYN "Use these values (or \"q\" to quit)"; then
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
	echo "Values must be provided for them: they cannot be left blank."
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

    # Estimate whether it should be possible to use Let's Encrypt

    ALLOW_LETSENCRYPT=false
    # Notice Let's Encrypt cannot be used on Amazon elastic cloud
    if [[ "$SERVERNAME" =~ [a-zA-Z] && ! "$SERVERNAME" =~ amazonaws.com$ && ! "$SERVERNAME" =~ [:] && "$SERVERNAME" != "localhost" ]]; then
        ALLOW_LETSENCRYPT=true
    fi
    if [ "$ALLOW_LETSENCRYPT" = true ]; then
        if promptYN "Install Let's Encrypt (free TLS certificate). Requires publicly accessible host name" 'y'; then
        	LETSENCRYPT=y
        else
	        LETSENCRYPT=n
        fi
    fi

    # Automatic updates

    if [ "$FIRST_PASS" = 'y' ]; then
	echo
	echo "TCAT can automatically update itself in the background."
	echo "When updates are applied, the database is usually locked and"
	echo "tweet capturing may be blocked. A lower update level means"
	echo "long lock times and interrupted captures can be avoided, at the"
	echo "cost of not automatically receiving some updates. Zero means"
	echo "automatic updates are disabled."
	echo
    fi

    DEFAULT=$TCAT_AUTO_UPDATE
    TCAT_AUTO_UPDATE=
    while [ -z "$TCAT_AUTO_UPDATE" ]; do
	read -p "Automatically upgrade TCAT (0=off, 1=trivial,2=substantial,3=expensive) [$DEFAULT]: " TCAT_AUTO_UPDATE
	if [ -z "$TCAT_AUTO_UPDATE" ]; then
	    TCAT_AUTO_UPDATE=$DEFAULT
	fi
	if [ "$TCAT_AUTO_UPDATE" != '0' -a \
	    "$TCAT_AUTO_UPDATE" != '1' -a \
	    "$TCAT_AUTO_UPDATE" != '2' -a \
	    "$TCAT_AUTO_UPDATE" != '3' ] ; then
	    echo "Invalid value (expecting 0, 1, 2 or 3)"
	    TCAT_AUTO_UPDATE= # clear to reprompt
	fi
    done

    # Advanced parameters

    if [ "$FIRST_PASS" = 'y' ]; then
	echo
	echo "Advanced parameters for the file owner, MySQL accounts and TCAT"
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

if [ "$TCAT_AUTO_UPDATE" -ne 0 -a \
     "$TCAT_AUTO_UPDATE" -ne 1 -a \
     "$TCAT_AUTO_UPDATE" -ne 2 -a \
     "$TCAT_AUTO_UPDATE" -ne 3 ] ; then
    echo "$PROG: invalid TCAT_AUTO_UPDATE (expecting 0, 1, 2 or 3): $TCAT_AUTO_UPDATE" >&2
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
    TCATADMINPASS_GENERATED=
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

apt-get -qq install -y git

# Clear any existing TCAT crontab references
echo "" > /etc/cron.d/tcat

# Remove entries in /etc/crontab from by a previous version of the installer.
# Note: this version of the installer does not add entries to /etc/crontab.
# These lines used to be written to the global /etc/crontab file
sed -i 's/^# Run TCAT controller every minute$//g' /etc/crontab
sed -i 's/^.*cd \/var\/www\/dmi-tcat\/capture\/stream\/; php controller.php.*$//g' /etc/crontab
sed -i 's/^# Run DMI-TCAT URL expander every hour$//g' /etc/crontab
sed -i 's/^.*cd \/var\/www\/dmi-tcat\/helpers; sh urlexpand.sh.*$//g' /etc/crontab

#----------------------------------------------------------------

if [ "$DO_UPDATE_UPGRADE" = 'y' ]; then
    tput bold
    echo "Updating and upgrading ..."
    tput sgr0
    echo ""

    apt-get update

    if [ "$BATCH_MODE" = 'y' ]; then
        # Upgrade without user interaction
        # http://askubuntu.com/questions/146921/how-do-i-apt-get-y-dist-upgrade-without-a-grub-config-prompt
	DEBIAN_FRONTEND=noninteractive \
	    apt-get -y \
	    -o Dpkg::Options::="--force-confdef" \
	    -o Dpkg::Options::="--force-confold" \
	    upgrade
    else
	# Upgrade normally: which might prompt the user in some situations
	apt-get -y upgrade
    fi
fi

#----------------------------------------------------------------
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

echo "$PROG: installing Apache and PHP"

if [ -n "$UBUNTU_VERSION" ]; then
    echo "$PROG: installing MySQL for Ubuntu"
    apt-get -y install $UBUNTU_MYSQL_SVR_PKG mariadb-client

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

    if [ -L /etc/apparmor.d/disable/usr.sbin.mysqld ]; then
	rm /etc/apparmor.d/disable/usr.sbin.mysqld # remove so "ln" will work
    fi
    ln -s /etc/apparmor.d/usr.sbin.mysqld /etc/apparmor.d/disable/
    /etc/init.d/apparmor restart

elif [ -n "$DEBIAN_VERSION" ]; then
    echo "$PROG: installing MySQL for Debian"

    apt-get -y install mariadb-server

    echo "$PROG: installing Apache for Debian"

    apt-get -y install \
    apache2-utils \
    libapache2-mod-php7.0 \
    php7.0-mysql php7.0-curl php7.0-cli php-patchwork-utf8 php7.0-mbstring

    # Build and enable PHP GEOS module for PHP 7.0

    apt-get install -y build-essential automake make gcc g++ php7.0-dev
    wget http://download.osgeo.org/geos/geos-3.6.2.tar.bz2
    tar -xjf geos-3.6.2.tar.bz2
    cd geos-3.6.2/
    ./configure --enable-php
    make -j 4
    make install
    ldconfig
    cd ../
    git clone https://git.osgeo.org/gogs/geos/php-geos.git --depth 1
    cd php-geos
    sh autogen.sh
    ./configure
    make -j 4
    make install
    cd ../
    echo "extension=geos.so" > /etc/php/7.0/mods-available/geos.ini
    phpenmod geos

else
    echo "$PROG: internal error: unexpected OS" >&2
    exit 3
fi

# Disable Linux HugePage support (needed for TokuDB)
tput bold
echo "Disabling Linux kernel transparant HugePage support" 1>&2
tput sgr0
if test -f /sys/kernel/mm/transparent_hugepage/enabled; then
   echo never > /sys/kernel/mm/transparent_hugepage/enabled
fi
if test -f /sys/kernel/mm/transparent_hugepage/defrag; then
   echo never > /sys/kernel/mm/transparent_hugepage/defrag
fi

# Install the TokuDB storage engine

tput bold
echo "Installing TokuDB storage engine ..."
tput sgr0

apt-get -y install mariadb-plugin-tokudb
# Enable the TokuDB storage engine
sed -i 's/^#plugin-load-add=ha_tokudb.so/plugin-load-add=ha_tokudb.so/g' /etc/mysql/mariadb.conf.d/tokudb.cnf
# Restart mariadb
systemctl restart mariadb

echo ""
tput bold
echo "Downloading DMI-TCAT from github ..."
tput sgr0
echo ""

if [ -z "$TCAT_GIT_BRANCH" ]; then
    # Can do a shallow clone to minimize the data download
    # Can be changed later with "git fetch --depth" or "git fetch --unshallow"
    GIT_DEPTH="--depth 1"
    TCAT_GIT_BRANCH=master
else
    # TODO: work out how to checkout another branch from a shallow clone
    # Workaround: do not do a shallow clone
    GIT_DEPTH=
fi

echo
echo "Cloning $TCAT_GIT_REPOSITORY into $TCAT_DIR $GIT_DEPTH"
git clone $GIT_DEPTH "$TCAT_GIT_REPOSITORY" "$TCAT_DIR"

echo
echo "Using branch/tag $TCAT_GIT_BRANCH"
git -C "$TCAT_DIR" checkout "$TCAT_GIT_BRANCH"

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

chown -R $SHELLUSER:$SHELLGROUP "$TCAT_DIR"
cd "$TCAT_DIR"
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

if [ "$DO_SAVE_TCAT_LOGINS" = 'y' ]; then
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
    echo "$PROG: TCAT login details saved: $FILE"

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
    echo "$PROG: TCAT login details saved: $FILE"
fi

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

if [ -n "$UBUNTU_VERSION" ]; then
    /etc/init.d/apache2 restart
elif [ -n "$DEBIAN_VERSION" ]; then
    systemctl restart apache2
else
    echo "$PROG: internal error: unexpected OS" >&2
    exit 3
fi

# Install Let's Encrypt via certbot
if [ "$LETSENCRYPT" = 'y' ]; then
    apt-get install -y certbot python-certbot-apache
    certbot --apache -d $SERVERNAME
    apache2ctl restart
fi

echo ""
tput bold
echo "Configuring MySQL server for TCAT (authentication) ..."
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

# Install MySQL server timezone data

mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql --defaults-file="$MYSQL_USER_ADMIN_CNF" mysql 

# Create twittercapture database

echo "CREATE DATABASE IF NOT EXISTS twittercapture DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci;" | mysql --defaults-file="$MYSQL_USER_ADMIN_CNF"
echo "GRANT CREATE, DROP, LOCK TABLES, ALTER, DELETE, INDEX, INSERT, SELECT, UPDATE, CREATE TEMPORARY TABLES ON twittercapture.* TO '$TCATMYSQLUSER'@'localhost' IDENTIFIED BY '$TCATMYSQLPASS';" | mysql --defaults-file="$MYSQL_USER_ADMIN_CNF"
echo "FLUSH PRIVILEGES;" | mysql --defaults-file="$MYSQL_USER_ADMIN_CNF"
sed -i "s/dbuser = \"\"/dbuser = \"$TCATMYSQLUSER\"/g" "$CFG"
sed -i "s/dbpass = \"\"/dbpass = \"$TCATMYSQLPASS\"/g" "$CFG"
sed -i "s/example.com\/dmi-tcat\//$SERVERNAME\//g" "$CFG"
if [ "$LETSENCRYPT" = 'y' ]; then
    sed -i "s/http:\/\//https:\/\//g" "$CFG"
fi

if [ "$URLEXPANDYES" = 'y' ]; then
   echo ""
   tput bold
   echo "Enabling URL expander ..."
   tput sgr0
   echo ""
   VAR=ENABLE_URL_EXPANDER
   VALUE="TRUE"
   sed -i "s|^define('$VAR',[^)]*);|define('$VAR', $VALUE);|" "$CFG"
fi

echo ""
tput bold
echo "Activating TCAT controller in cron ..."
tput sgr0
echo ""

CRONLINE="* *     * * *   $SHELLUSER   (cd \"$TCAT_DIR/capture/stream\"; php controller.php)"
echo "" >> /etc/cron.d/tcat
echo "# Run TCAT controller every minute" >> /etc/cron.d/tcat
echo "$CRONLINE" >> /etc/cron.d/tcat

echo ""
tput bold
echo "Setting up logfile rotation ..."
tput sgr0
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

# Edit config file to change:
#     define('CAPTUREROLES', serialize(array('track')));
# To contain desired value according to the capture mode.

case "$CAPTURE_MODE" in
    1)  CAPTURE_MODE_VALUE=track ;;
    2)  CAPTURE_MODE_VALUE=follow ;;
    3)  CAPTURE_MODE_VALUE=onepercent ;;
    *)  echo "$PROG: internal error: bad capture mode: $CAPTURE_MODE" >&2
	exit 3 ;;
esac

echo "Using role: ${CAPTURE_MODE_VALUE}"
sed -i -E "s/^(.*CAPTUREROLES.*\()[^\)]+(\).*)$/\1'${CAPTURE_MODE_VALUE}'\2/" "$CFG"

# Edit config file to contain Twitter API credentials

sed -i "s/^\$twitter_consumer_key = \"\";/\$twitter_consumer_key = \"$CONSUMERKEY\";/g" "$CFG"
sed -i "s/^\$twitter_consumer_secret = \"\";/\$twitter_consumer_secret = \"$CONSUMERSECRET\";/g" "$CFG"
sed -i "s/^\$twitter_user_token = \"\";/\$twitter_user_token = \"$USERTOKEN\";/g" "$CFG"
sed -i "s/^\$twitter_user_secret = \"\";/\$twitter_user_secret = \"$USERSECRET\";/g" "$CFG"

# Configure TCAT automatic updates

VARIABLE=AUTOUPDATE_ENABLED
if [ $TCAT_AUTO_UPDATE -eq 0 ]; then
    VALUE=false
else
    VALUE=true
fi
sed -i "s/^define('$VARIABLE',[^)]*);/define('$VARIABLE', $VALUE);/g" "$CFG"

VARIABLE=AUTOUPDATE_LEVEL
case $TCAT_AUTO_UPDATE in
    0) VALUE="'trivial'";; # note: quotes in value are important
    1) VALUE="'trivial'";;
    2) VALUE="'substantial'";;
    3) VALUE="'expensive'";;
    *) echo "$PROG: internal error: bad TCAT_AUTO_UPDATE" >&2; exit 1;;
esac
sed -i "s/^define('$VARIABLE',[^)]*);/define('$VARIABLE', $VALUE);/g" "$CFG"

# Check if the current MySQL configuration is the system default one

apt-get -y install debsums

# TCAT will not function fully on modern versions of MySQL without some modified settings

echo "[mysqld]" > /etc/mysql/conf.d/tcat-autoconfigured.cnf

echo ""
tput bold
echo "Configuring MySQL server (compatibility) ..."
tput sgr0
echo ""

echo "sql-mode=\"NO_AUTO_VALUE_ON_ZERO,ALLOW_INVALID_DATES\"" >> /etc/mysql/conf.d/tcat-autoconfigured.cnf

if [ "$DB_CONFIG_MEMORY_PROFILE" = "y" ]; then
    echo ""
    tput bold
    echo "Configuring MySQL server (memory profile) ..."
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
        if [ -n "$UBUNTU_VERSION" ]; then
            /etc/init.d/mysql restart
        elif [ -n "$DEBIAN_VERSION" ]; then
            systemctl restart mysql
        else
            echo "$PROG: internal error: unexpected OS" >&2
            exit 3
        fi
    fi
fi

echo ""
tput bold
echo "Done: TCAT installed"
tput sgr0
echo ""

echo "Please visit this TCAT installation at these URLs:"
if [ "$LETSENCRYPT" = 'y' ]; then
    echo "  https://$SERVERNAME/capture/"
    echo "  https://$SERVERNAME/analysis/"
else
    echo "  http://$SERVERNAME/capture/"
    echo "  http://$SERVERNAME/analysis/"
fi
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
if [ "$DO_SAVE_TCAT_LOGINS" = 'y' ]; then
    echo "TCAT logins have been saved to ${TCAT_CNF_PREFIX}*${TCAT_CNF_SUFFIX}."
else
  if [ "$TCATPASS_GENERATED" = 'y' ]; then
      echo "IMPORTANT: please save the above generated TCAT Web login passwords."
  fi
fi
echo "MySQL accounts have been saved to ${MYSQL_CNF_PREFIX}*${MYSQL_CNF_SUFFIX}."
echo
echo "The following steps are recommended, but not mandatory"
echo ""
echo " * Set-up your systems e-mail (sendmail)"
echo ""

exit 0
