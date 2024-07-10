#!/bin/bash

# Base configuration and installation
set -euo pipefail
cd /tmp
git clone https://github.com/Adyen/adyen-integration-tools-tests.git
cd adyen-integration-tools-tests
git checkout $INTEGRATION_TESTS_BRANCH
rm -rf package-lock.json
npm i
npx playwright install

option="$1"

# Run the desired group of tests
case $option in
    "standard")
        echo "Running Standard Set of E2E Tests."
        npm run test:ci:magento
        ;;
    "express-checkout")
        echo "Running Express Checkout E2E Tests."
        npm run test:ci:magento:express-checkout
        ;;
    "all")
        echo "Running All Magento E2E Tests"
        npm run test:ci:magento:all
        ;;
esac
