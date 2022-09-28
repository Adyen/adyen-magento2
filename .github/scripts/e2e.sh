#!/bin/bash
set -euo pipefail

# Checkout E2E tests
cd /tmp;
git clone https://github.com/Adyen/adyen-integration-tools-tests.git;
cd adyen-integration-tools-tests;
git checkout $INTEGRATION_TESTS_BRANCH;

# Setup environment
rm -rf package-lock.json;
npm i;
npx playwright install --with-deps;

# Run tests
npm run test:ci:magento