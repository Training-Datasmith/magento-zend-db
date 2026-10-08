#!/bin/sh
set -e
cd /tmp
curl -fsSLO https://getcomposer.org/installer
curl -fsSLO https://composer.github.io/installer.sig
EXPECTED="$(cat installer.sig)"
ACTUAL="$(php -r "echo hash_file('sha384', 'installer');")"
if [ "$EXPECTED" != "$ACTUAL" ]; then
  echo "Composer installer signature mismatch" >&2
  exit 1
fi
php installer --version=2.2.30 --install-dir=/usr/local/bin --filename=composer
rm -f installer installer.sig
