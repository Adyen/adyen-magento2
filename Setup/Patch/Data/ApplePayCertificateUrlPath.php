<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class ApplePayCertificateUrlPath implements DataPatchInterface
{
    const APPLEPAY_CERTIFICATE_URL = 'https://docs.adyen.com/payment-methods/apple-pay/web-component/apple-developer-merchantid-domain-association.zip';
    const APPLEPAY_CERTIFICATE_CONFIG_PATH = 'payment/adyen_hpp/apple_pay_certificate_url';

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @param WriterInterface $configWriter
     */
    public function __construct(
        WriterInterface $configWriter
    ) {
        $this->configWriter = $configWriter;
    }

    /**
     * @return void
     */
    public function apply()
    {
        $this->configWriter->save(
            self::APPLEPAY_CERTIFICATE_CONFIG_PATH,
            self::APPLEPAY_CERTIFICATE_URL,
        );
    }

    /**
     * @return array|string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @return array|string[]
     */
    public function getAliases()
    {
        return [];
    }
}
