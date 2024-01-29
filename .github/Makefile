# Install N98-Magerun
n98-magerun2.phar:
	wget -q https://files.magerun.net/n98-magerun2.phar
	chmod +x ./n98-magerun2.phar

# Check Magento installation
sys-check: n98-magerun2.phar
	./n98-magerun2.phar sys:check

# Install Magento (without starting Apache)
magento:
	sed '/exec /d' /tmp/install_magento.sh | bash

# Plugin install
install:
	composer config --json repositories.local '{"type": "path", "url": "/data/extensions/workdir", "options": { "symlink": false } }'
	composer require "adyen/module-payment:*"
	vendor/bin/phpcs --standard=Magento2 --extensions=php,phtml --error-severity=10 --ignore-annotations -n -p vendor/adyen/module-payment
	bin/magento module:enable Adyen_Payment
	bin/magento setup:upgrade
	bin/magento setup:di:compile

# Install developer module
dev-module:
	bin/magento module:enable Adyen_Developer
	bin/magento setup:upgrade

# Configuration
configure: n98-magerun2.phar
	bin/magento config:set payment/adyen_abstract/demo_mode 1
	bin/magento adyen:enablepaymentmethods:run
	bin/magento config:set payment/adyen_abstract/has_holder_name 1
	bin/magento config:set payment/adyen_pay_by_link/days_to_expire 5
	bin/magento config:set payment/adyen_giving/active 1
	bin/magento config:set payment/adyen_giving/charity_description 'test'
	bin/magento config:set payment/adyen_giving/charity_website 'https://adyen.com'
	bin/magento config:set payment/adyen_giving/charity_merchant_account "${DONATION_ACCOUNT}"
	bin/magento config:set payment/adyen_giving/donation_amounts '1,5,10'
	bin/magento config:set payment/adyen_giving/background_image ''
	bin/magento config:set payment/adyen_abstract/merchant_account "${ADYEN_MERCHANT}"
	bin/magento config:set payment/adyen_abstract/notifications_ip_check 0
	bin/magento config:set payment/adyen_abstract/payment_authorized 'processing'
	bin/magento config:set payment/adyen_abstract/payment_pre_authorized 'pending_payment'
	bin/magento config:set payment/adyen_abstract/capture_mode 'manual'
	bin/magento config:set payment/adyen_abstract/paypal_capture_mode 0
	bin/magento config:set payment/adyen_abstract/recurring_configuration '{"adyen_cc":{"name":"Credit Card","enabled":"1","recurringProcessingModel":"CardOnFile"}}'
	bin/magento config:set payment/adyen_cc_vault/require_cvc 1
	bin/magento config:set payment/adyen_abstract/client_key_test "${ADYEN_CLIENT_KEY}"
	bin/magento config:set payment/adyen_abstract/notification_username 'admin'
	bin/magento config:set payment/adyen_abstract/payment_methods_active 1
	./n98-magerun2.phar config:store:set --encrypt payment/adyen_abstract/notification_password '1234'  > /dev/null
	./n98-magerun2.phar config:store:set --encrypt payment/adyen_abstract/api_key_test "${ADYEN_API_KEY}" > /dev/null

# Clear cache
flush:
	bin/magento cache:flush

# Setup upgrade
enable-express:
	bin/magento module:enable Adyen_ExpressCheckout
	bin/magento setup:upgrade
	bin/magento config:set payment/adyen_express/show_google_pay_on "1,2,3"
	bin/magento config:set payment/adyen_express/show_apple_pay_on "1,2,3"
	bin/magento cache:clean

# Full plugin setup
plugin: install configure flush

# Setup permissions
fs:
	find var generated vendor pub/static pub/media app/etc -type f -exec chmod g+w {} +
	find var generated vendor pub/static pub/media app/etc -type d -exec chmod g+ws {} +
	chown -R www-data:www-data .
	chmod u+x bin/magento
	echo "memory_limit = -1" > /usr/local/etc/php/conf.d/memory.ini

# Deploy sample data
sampledata:
	composer config http-basic.repo.magento.com "${MAGENTO_USERNAME}" "${MAGENTO_PASSWORD}"
	composer config repositories.magento composer https://repo.magento.com/
	bin/magento sampledata:deploy
	bin/magento setup:upgrade

# Production mode
production:
	bin/magento deploy:mode:set production

# JavaScript bundling
bundlejs:
	bin/magento config:set dev/js/enable_js_bundling 1
	bin/magento config:set dev/js/minify_files 1
	bin/magento config:set dev/static/sign 1
	bin/magento config:set dev/js/merge_files 0
	bin/magento setup:static-content:deploy
	bin/magento cache:clean config

MAGENTO_ROOT=/var/www/html
GRAPHQL_XML=${MAGENTO_ROOT}/dev/tests/api-functional/phpunit_graphql.xml.dist
GRAPHQL_PHP=/data/extensions/workdir/Test/phpunit_graphql.php
GRAPHQL_SUITE=${MAGENTO_ROOT}/vendor/adyen/module-payment/Test/api-functional/GraphQl

# GraphQL tests
graphql:
	@cd ${MAGENTO_ROOT}/dev/tests/api-functional && \
		${MAGENTO_ROOT}/vendor/bin/phpunit --prepend ${GRAPHQL_PHP} --configuration ${GRAPHQL_XML} ${GRAPHQL_SUITE}

# REST API tests
restapi:
	@cd ${MAGENTO_ROOT}/dev/tests/api-functional && \
        ${MAGENTO_ROOT}/vendor/bin/phpunit --prepend /data/extensions/workdir/Test/phpunit_rest.php \
        --configuration ${MAGENTO_ROOT}/dev/tests/api-functional/phpunit_rest.xml.dist \
        ${MAGENTO_ROOT}/vendor/adyen/module-payment/Test/api-functional/Webapi

# Destroy services
clean:
	docker-compose -f workflows/templates/docker-compose.yml down --volumes --rmi local
	docker-compose -f docker-compose.yml -f docker-compose.mftf.yml down --volumes --rmi local

# Docker image build
image:
	docker-compose -f workflows/templates/docker-compose.yml -f docker-compose.e2e.yml build
	docker-compose -f docker-compose.yml -f docker-compose.mftf.yml build

# Setup environment to run functional tests (https://devdocs.magento.com/mftf/docs/getting-started.html)
setup-mftf:
	bin/magento config:set currency/options/allow USD,EUR
	bin/magento config:set currency/options/default USD
	bin/magento config:set currency/options/base USD

	bin/magento config:set cms/wysiwyg/enabled disabled
	bin/magento config:set admin/security/admin_account_sharing 1
	bin/magento config:set admin/security/use_form_key 0
	bin/magento cache:clean config full_page

	vendor/bin/mftf build:project

	rm -f dev/tests/acceptance/.env;
	vendor/bin/mftf setup:env \
		--MAGENTO_BASE_URL "https://${MAGENTO_HOST}/" \
		--MAGENTO_BACKEND_NAME "${ADMIN_URLEXT}" \
		--MAGENTO_ADMIN_USERNAME "${ADMIN_USERNAME}" \
		--MAGENTO_ADMIN_PASSWORD "${ADMIN_PASSWORD}" \
		--BROWSER chrome \
		--ELASTICSEARCH_VERSION 7;
	echo 'SELENIUM_HOST=selenium' >> dev/tests/acceptance/.env;

	echo 'magento/MAGENTO_ADMIN_PASSWORD=${ADMIN_PASSWORD}' >> dev/tests/acceptance/.credentials

	cp dev/tests/acceptance/.htaccess.sample dev/tests/acceptance/.htaccess

	vendor/bin/mftf doctor

# Magento Functional Testing Framework
mftf:
	vendor/bin/mftf run:group --remove -- AdyenMagentoSuite || vendor/bin/mftf run:failed

# Simulate a full CI build
ci:
	docker-compose -f workflows/templates/docker-compose.yml run --rm web make magento
	docker-compose -f workflows/templates/docker-compose.yml up -d web

	docker exec magento2-container make fs
	docker exec magento2-container make sys-check
	docker exec -u www-data magento2-container make plugin
	docker exec magento2-container /etc/init.d/cron stop
	docker exec -u www-data magento2-container make unit
	docker exec magento2-container make graphql
	docker exec -u www-data magento2-container make production
	docker exec magento2-container make fs

	docker-compose -f workflows/templates/docker-compose.yml run --rm playwright /e2e.sh
