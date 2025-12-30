# Variables
MAGENTO_ROOT=/var/www/html
# End of variables

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
	composer config --json repositories.local '{"type": "path", "url": "/data/extensions/workdir", "options": { "symlink": false }, "canonical": false }'
	composer require "adyen/module-payment:dev-ECP-9322" -W
	bin/magento module:enable Adyen_Payment
	bin/magento setup:upgrade
	bin/magento setup:di:compile
	bin/magento cron:install

# Install developer module
dev-module:
	bin/magento module:enable Adyen_Developer
	bin/magento setup:upgrade

# Configuration
configure: n98-magerun2.phar
	bin/magento config:set payment/adyen_abstract/demo_mode 1
	bin/magento adyen:enablepaymentmethods:run
	bin/magento config:set payment/adyen_abstract/has_holder_name 1
	bin/magento config:set payment/adyen_pay_by_link/active 1
	bin/magento config:set payment/adyen_pay_by_link/days_to_expire 5
	bin/magento config:set payment/adyen_giving/active 1
	bin/magento config:set payment/adyen_abstract/merchant_account "${ADYEN_MERCHANT}"
	bin/magento config:set payment/adyen_abstract/notifications_ip_check 0
	bin/magento config:set payment/adyen_abstract/payment_authorized 'processing'
	bin/magento config:set payment/adyen_abstract/payment_pre_authorized 'adyen_authorized'
	bin/magento config:set payment/adyen_abstract/capture_mode 'manual'
	bin/magento config:set payment/adyen_abstract/paypal_capture_mode 0
	bin/magento config:set payment/adyen_abstract/recurring_configuration '{"adyen_cc":{"name":"Cards","enabled":"1","recurringProcessingModel":"CardOnFile"},"adyen_sepadirectdebit":{"name":"SEPA Direct Debit","enabled":"1","recurringProcessingModel":"CardOnFile"}}'
	bin/magento config:set payment/adyen_cc_vault/require_cvc 1
	bin/magento config:set payment/adyen_abstract/client_key_test "${ADYEN_CLIENT_KEY}"
	bin/magento config:set payment/adyen_abstract/notification_username 'admin'
	bin/magento config:set payment/adyen_abstract/payment_methods_active 1
	bin/magento config:set payment/adyen_abstract/webhook_notification_processor 'queue'
	./n98-magerun2.phar config:store:set --encrypt payment/adyen_abstract/notification_password '1234'  > /dev/null
	./n98-magerun2.phar config:store:set --encrypt payment/adyen_abstract/api_key_test "${ADYEN_API_KEY}" > /dev/null

# Clear cache
flush:
	bin/magento cache:flush

# Setup upgrade
enable-express:
	bin/magento module:enable Adyen_ExpressCheckout
	bin/magento setup:upgrade
	bin/magento config:set payment/adyen_googlepay/express_show_on "1,2,3"
	bin/magento config:set payment/adyen_applepay/express_show_on "1,2,3"
	bin/magento config:set payment/adyen_paypal_express/express_show_on "1,2,3"
	bin/magento cache:clean

# Full plugin setup
plugin: install configure flush

# Setup permissions
fs:
	find var generated vendor pub/static pub/media app/etc -type f -exec chmod g+w {} +
	find var generated vendor pub/static pub/media app/etc -type d -exec chmod g+ws {} +
	chmod 777 -R var
	chown -R www-data:www-data .
	chmod u+x bin/magento
	echo "memory_limit = -1" > /usr/local/etc/php/conf.d/memory.ini

# Production mode
production:
	bin/magento deploy:mode:set production

# GraphQL tests
GRAPHQL_XML_DIST=${MAGENTO_ROOT}/vendor/adyen/module-payment/Test/phpunit_graphql.xml.dist
GRAPHQL_XML=${MAGENTO_ROOT}/dev/tests/api-functional/phpunit_graphql.xml

setup-graphql:
	sed '/TESTS_BASE_URL/s/magento.url/${MAGENTO_HOST}/g' ${GRAPHQL_XML_DIST} >> ${GRAPHQL_XML}
	bin/magento indexer:set-mode realtime
	bin/magento cache:clean
	bin/magento indexer:reindex

graphql:
	@cd ${MAGENTO_ROOT}/dev/tests/api-functional && ${MAGENTO_ROOT}/vendor/bin/phpunit -c ${GRAPHQL_XML}
# End of GraphQL tests

# REST API tests
REST_XML_DIST=${MAGENTO_ROOT}/vendor/adyen/module-payment/Test/phpunit_rest.xml.dist
REST_XML=${MAGENTO_ROOT}/dev/tests/api-functional/phpunit_rest.xml

setup-restapi:
	sed '/TESTS_BASE_URL/s/magento.url/${MAGENTO_HOST}/g' ${REST_XML_DIST} >> ${REST_XML}

restapi:
	@cd ${MAGENTO_ROOT}/dev/tests/api-functional && ${MAGENTO_ROOT}/vendor/bin/phpunit -c ${REST_XML}

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
		--MAGENTO_BASE_URL "http://${MAGENTO_HOST}/" \
		--MAGENTO_BACKEND_NAME "${ADMIN_URLEXT}" \
		--MAGENTO_ADMIN_USERNAME "${ADMIN_USERNAME}" \
		--BROWSER chrome \
		--ELASTICSEARCH_VERSION 7;
	echo 'SELENIUM_HOST=selenium' >> dev/tests/acceptance/.env;

	echo 'magento/MAGENTO_ADMIN_PASSWORD=${ADMIN_PASSWORD}' >> dev/tests/acceptance/.credentials

	cp dev/tests/acceptance/.htaccess.sample dev/tests/acceptance/.htaccess

	vendor/bin/mftf doctor

# Magento Functional Testing Framework
mftf:
	vendor/bin/mftf run:group --remove -- AdyenMagentoSuite || vendor/bin/mftf run:failed

phpstan-run:
	vendor/bin/phpstan analyse -c vendor/adyen/module-payment/phpstan.neon

phpunit-run:
	cp vendor/adyen/module-payment/Test/phpunit.xml.dist dev/tests/unit/phpunit.xml
	cd dev/tests/unit; \
		../../../vendor/bin/phpunit \
		--coverage-clover=../../../build/clover.xml.dist \
		--log-junit=../../../build/tests-log.xml.dist \
		-c phpunit.xml \
		../../../vendor/adyen/module-payment/Test/Unit

phpunit-fix-report:
	sed '/file/s/\/var\/www\/html\/vendor\/adyen\/module-payment\///g' ${MAGENTO_ROOT}/build/tests-log.xml.dist >> ${MAGENTO_ROOT}/build/tests-log.xml
	sed '/file/s/\/var\/www\/html\/vendor\/adyen\/module-payment\///g' ${MAGENTO_ROOT}/build/clover.xml.dist >> ${MAGENTO_ROOT}/build/clover.xml

codesniffer-run:
	vendor/bin/phpcs --standard=Magento2 \
		--extensions=php,phtml \
		--error-severity=10 \
		--warning-severity=0 \
		--ignore-annotations \
		-p vendor/adyen/module-payment
