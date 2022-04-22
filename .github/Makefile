#  - name: Install N98-Magerun
n98-magerun2.phar:
	wget -q https://files.magerun.net/n98-magerun2.phar
	chmod +x ./n98-magerun2.phar

sys-check: n98-magerun2.phar
	./n98-magerun2.phar sys:check

magento:
	sed '/exec /d' /tmp/install_magento.sh | bash

install:
	composer config --json repositories.local '{"type": "path", "url": "/data/extensions/workdir", "options": { "symlink": false } }'
	composer require "adyen/module-payment:*"
	vendor/bin/phpcs --standard=Magento2 --extensions=php,phtml --error-severity=10 --ignore-annotations -n -p vendor/adyen/module-payment
	bin/magento module:enable Adyen_Payment
	bin/magento setup:upgrade
	bin/magento setup:di:compile

# - name: Configure the plugin
configure: n98-magerun2.phar
	bin/magento config:set payment/adyen_abstract/demo_mode 1
	bin/magento config:set payment/adyen_hpp/active 1
	bin/magento config:set payment/adyen_cc/active 1
	bin/magento config:set payment/adyen_oneclick/active 1
	bin/magento config:set payment/adyen_abstract/has_holder_name 1
	bin/magento config:set payment/adyen_boleto/active 1
	bin/magento config:set payment/adyen_boleto/boletotypes 'boletobancario_santander'
	bin/magento config:set payment/adyen_abstract/merchant_account "${ADYEN_MERCHANT}"
	./n98-magerun2.phar config:store:set --encrypt payment/adyen_abstract/api_key_test "${ADYEN_API_KEY}" > /dev/null
	bin/magento config:set payment/adyen_abstract/client_key_test "${ADYEN_CLIENT_KEY}"

# - name: Clear cache
flush:
	bin/magento cache:flush

plugin: install configure flush

# Setup permissions
fs:
	find var generated vendor pub/static pub/media app/etc -type f -exec chmod g+w {} +
	find var generated vendor pub/static pub/media app/etc -type d -exec chmod g+ws {} +
	chown -R www-data:www-data .
	chmod u+x bin/magento
	echo "memory_limit = -1" > /usr/local/etc/php/conf.d/memory.ini

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
PHPUNIT_CONFIG=vendor/adyen/module-payment/Test/api-functional/phpunit_graphql.xml
TEST_SUITE=vendor/adyen/module-payment/Test/api-functional/GraphQl

graphql:
	cd ${MAGENTO_ROOT}/dev/tests/api-functional; 
	${MAGENTO_ROOT}/vendor/bin/phpunit --configuration ${MAGENTO_ROOT}/${PHPUNIT_CONFIG} ${MAGENTO_ROOT}/${TEST_SUITE}

unit:
	vendor/bin/phpunit vendor/adyen/module-payment/Test/Unit