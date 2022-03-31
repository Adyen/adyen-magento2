#!/bin/bash
set -euo pipefail

# Checkout E2E tests
git clone https://github.com/Adyen/adyen-integration-tools-tests.git;
cd adyen-integration-tools-tests;

# Setup environment
npm ci;
npx playwright install --with-deps;

# Run tests
MAGENTO_BASE_URL=http://magento2.test.com npx playwright test --project=chromium --config=./projects/magento/magento.config.cjs;