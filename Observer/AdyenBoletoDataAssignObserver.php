<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class AdyenBoletoDataAssignObserver extends AbstractDataAssignObserver
{
    const SOCIAL_SECURITY_NUMBER = 'social_security_number';
    const BOLETO_TYPE = 'boleto_type';
    const FIRSTNAME = 'firstname';
    const LASTNAME = 'lastname';

    /**
     * @var array
     */
    protected $additionalInformationList = [
        self::SOCIAL_SECURITY_NUMBER,
        self::BOLETO_TYPE,
        self::FIRSTNAME,
        self::LASTNAME
    ];

    /**
     * @var \Adyen\Payment\Helper\Config
     */
    private $configHelper;

    /**
     * AdyenBoletoDataAssignObserver constructor.
     *
     * @param \Adyen\Payment\Helper\Config $configHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Config $configHelper
    ) {
        $this->configHelper = $configHelper;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        // Remove remaining brand_code information from the previous payment
        $paymentInfo->unsAdditionalInformation('brand_code');

        if (!empty($additionalData[self::BOLETO_TYPE])) {
            $paymentInfo->setCcType($additionalData[self::BOLETO_TYPE]);
        } else {
            $paymentInfo->setCcType($this->configHelper->getAdyenBoletoConfigData('boletotypes'));
        }

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }
    }
}
