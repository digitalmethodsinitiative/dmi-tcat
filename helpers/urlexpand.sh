#!/bin/bash
if ! ps aux | grep  "[p]ython /var/www/dmi-tcat/helpers/urlexpand.py"
    then
        timeout -s SIGKILL 3500 python /var/www/dmi-tcat/helpers/urlexpand.py
fi
