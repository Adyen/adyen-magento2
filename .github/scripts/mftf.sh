#!/bin/bash
        
# docker-compose -f .github/docker-compose.yml up -d
# sleep 120

# Install plugin 
composer config --json repositories.local '{"type": "path", "url": "/data/extensions/workdir", "options": { "symlink": false } }'
composer require "adyen/module-payment:*"
bin/magento module:enable Adyen_Payment
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush

# Configuration
bin/magento config:set cms/wysiwyg/enabled disabled
bin/magento config:set admin/security/admin_account_sharing 1
bin/magento config:set admin/security/use_form_key 0
bin/magento cache:clean config full_page

# Build test project
vendor/bin/mftf build:project
          
# Edit environmental settings
rm -f dev/tests/acceptance/.env;
vendor/bin/mftf setup:env \
    --MAGENTO_BASE_URL "http://${MAGENTO_HOST}/" \
    --MAGENTO_BACKEND_NAME $ADMIN_URLEXT \
    --MAGENTO_ADMIN_USERNAME $ADMIN_USERNAME \
    --MAGENTO_ADMIN_PASSWORD $ADMIN_PASSWORD \
    --BROWSER chrome \
    --ELASTICSEARCH_VERSION 7;
echo 'SELENIUM_HOST=selenium' >> dev/tests/acceptance/.env;

# Enable the Magento CLI commands
cp dev/tests/acceptance/.htaccess.sample dev/tests/acceptance/.htaccess
