#!/bin/sh
#
# Interactive shell as www-data in the project root. Used by
# `./dev azure shell` (least privilege; `--root` opens a root shell instead).
set -eu

if [ "$(id -u)" -eq 0 ]; then
    exec su -s /bin/bash www-data -c 'cd /var/www/html && exec bash'
fi

cd /var/www/html
exec bash
