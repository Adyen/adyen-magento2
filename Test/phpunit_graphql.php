<?php

ini_set('date.timezone', 'Europe/Amsterdam');

define('TESTS_BASE_URL', 'http://' . getenv('MAGENTO_HOST'));
define('TESTS_MAGENTO_INSTALLATION', 'disabled');
define('TESTS_CLEANUP', 'enabled');
