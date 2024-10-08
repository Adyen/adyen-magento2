<?php

namespace Adyen\Payment\Gateway\Request\Header;

interface ExternalPlatformHeaderDataBuilderInterface
{
    const EXTERNAL_PLATFORM_NAME = 'external-platform-name';
    const EXTERNAL_PLATFORM_VERSION = 'external-platform-version';
    const EXTERNAL_PLATFORM_EDITION = 'external-platform-edition';
    const EXTERNAL_PLATFORM_FRONTEND_TYPE = 'external-platform-frontendtype';

    const FRONTEND_TYPE = 'frontendType';
    const FRONTEND_TYPE_HEADLESS = 'headless';

    const MERCHANT_APPLICATION_NAME = 'merchant-application-name';
    const MERCHANT_APPLICATION_VERSION = 'merchant-application-version';

    /*
    * @param array $buildSubject
    * @return array
    */
    public function build(array $buildSubject);
}
