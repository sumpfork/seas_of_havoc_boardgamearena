#!/bin/bash
set -e

cd "$(dirname "$0")"

node modules/tests/PlaytestUiRegressionTest.js

# BGA framework stubs (https://github.com/elaskavaia/bga-sharedcode). Set APP_GAMEMODULE_PATH to
# its misc/ folder, or check it out next to this repository.
export APP_GAMEMODULE_PATH="${APP_GAMEMODULE_PATH:-$(cd .. && pwd)/bga-sharedcode/misc/}"
if [ ! -d "$APP_GAMEMODULE_PATH" ]; then
    echo "BGA stubs not found at $APP_GAMEMODULE_PATH" >&2
    echo "Clone https://github.com/elaskavaia/bga-sharedcode next to this repo or set APP_GAMEMODULE_PATH." >&2
    exit 1
fi

# Install PHPUnit if not already installed
if [ ! -d "vendor" ]; then
    echo "Installing PHPUnit via Composer..."
    composer install
fi

# Run the tests
echo "Running PHPUnit tests..."
cd modules
php -d zend.assertions=1 ../vendor/bin/phpunit --bootstrap autoload.php tests/

echo "Tests completed!"
