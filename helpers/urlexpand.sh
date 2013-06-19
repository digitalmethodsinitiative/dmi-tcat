#!/bin/bash
if ! ps aux | grep  "[p]ython /home/eelke/urlexpander/urlexpand.py"
    then
        python /home/eelke/urlexpander/urlexpand.py
fi
