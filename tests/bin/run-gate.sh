#!/bin/sh
set -e
cd "$(dirname "$0")/.."
export PHPUNIT="${PHPUNIT:-../vendor/bin/phpunit}"
export PHP="${PHP:-php}"

"$PHPUNIT" -c phpunit.xml.dist
"$PHPUNIT" -c phpunit.xml.dist
"$PHP" bin/seeded-reorder.php
"$PHP" bin/seeded-reorder.php
