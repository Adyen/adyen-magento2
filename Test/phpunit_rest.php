<?php

ini_set('date.timezone', 'Europe/Amsterdam');

define('TESTS_BASE_URL', 'http://' . getenv('MAGENTO_HOST'));
define('TESTS_WEBSERVICE_USER', 'admin');
define('TESTS_WEBSERVICE_APIKEY', '123123q');
define('TESTS_MAGENTO_INSTALLATION', 'disabled');
define('TESTS_CLEANUP', 'enabled');
