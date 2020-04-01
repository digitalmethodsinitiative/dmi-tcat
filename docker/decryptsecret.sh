#!/bin/sh
gpg --quiet --batch --yes --decrypt --passphrase="$CONFIG_PASSPHRASE" \
--output docker/config docker/testconfig.gpg
