<?php

namespace Adyen\Payment\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

class ApplePayCertificateUrlPath implements DataPatchInterface, PatchVersionInterface
{
    /**
     * @var WriterInterface
     */
    private $configWriter;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup, WriterInterface $configWriter)
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configWriter = $configWriter;
    }

    const APPLEPAY_CERTIFICATE_URL = 'https://docs.adyen.com/reuse/payment-method-pages/apple-pay/adyen-certificate/apple-developer-merchantid-domain-association.zip';
    const APPLEPAY_CERTIFICATE_CONFIG_PATH = 'payment/adyen_hpp/apple_pay_certificate_url';


    public function apply()
    {
        $this->configWriter->save(
            self::APPLEPAY_CERTIFICATE_CONFIG_PATH,
            self::APPLEPAY_CERTIFICATE_URL,
        );}

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }

    public static function getVersion(): string
    {
        // What should this return?
        return [];
//        return '8.8.0';
    }
}
