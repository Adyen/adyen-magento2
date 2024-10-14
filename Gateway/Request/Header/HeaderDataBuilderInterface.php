<?php

namespace Adyen\Payment\Gateway\Request\Header;

interface HeaderDataBuilderInterface
{
    const EXTERNAL_PLATFORM_NAME = 'external-platform-name';
    const EXTERNAL_PLATFORM_VERSION = 'external-platform-version';
    const EXTERNAL_PLATFORM_EDITION = 'external-platform-edition';
    const EXTERNAL_PLATFORM_FRONTEND_TYPE = 'external-platform-frontendtype';
    const MERCHANT_APPLICATION_NAME = 'merchant-application-name';
    const MERCHANT_APPLICATION_VERSION = 'merchant-application-version';

    const ADDITIONAL_DATA_FRONTEND_TYPE_KEY = 'frontendType';
    const FRONTEND_TYPE_HEADLESS_VALUE = 'headless';
}

