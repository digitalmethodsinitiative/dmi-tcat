#!/bin/bash

SCRIPT=$(readlink -f "$0")
SCRIPTPATH=$(dirname "$SCRIPT")

if ! ps aux | grep  "[p]ython $SCRIPTPATH/urlexpand.py"
    then
        timeout -s SIGKILL 110 python "$SCRIPTPATH"/urlexpand.py &>/dev/null
fi
