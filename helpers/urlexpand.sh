#!/bin/bash
if ! ps aux | grep  "[p]ython /var/www/dmi-tcat/helpers/urlexpand.py"
    then
        timeout 3500 -s SIGKILL python /var/www/dmi-tcat/helpers/urlexpand.py
fi
