#!/bin/bash
if ! ps aux | grep  "[p]ython /var/www/dmi-tcat/helpers/urlexpand.py"
    then
        python /var/www/dmi-tcat/helpers/urlexpand.py
fi
