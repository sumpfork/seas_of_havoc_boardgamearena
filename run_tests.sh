#!/bin/bash

# Set the environment variable for BGA stubs
export APP_GAMEMODULE_PATH="/Users/pgorniak/src/p/bga-sharedcode/misc/"

# Install PHPUnit if not already installed
if [ ! -d "vendor" ]; then
    echo "Installing PHPUnit via Composer..."
    composer install
fi

# Run the tests
echo "Running PHPUnit tests..."
cd modules
../vendor/bin/phpunit --bootstrap autoload.php tests/

echo "Tests completed!"
