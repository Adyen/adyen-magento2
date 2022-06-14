<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Customer;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\Ui\AdyenHppConfigProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractTokenRenderer;

/** TODO: Check why this is not being called */
class PaymentMethodRenderer extends AbstractTokenRenderer
{
    /** @var Data */
    private $dataHelper;

    public function __construct(Data $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    /**
     * @return string
     */
    public function getNumberLast4Digits()
    {
        return '1234';
    }

    /**
     * @return string
     */
    public function getExpDate()
    {
        return 'MONDAY';
    }

    /**
     * @return string
     */
    public function getIconUrl()
    {
        return 'https://emspay.nl/sites/emspay.nl/files/images/Paypal_logo.jpg';
    }

    /**
     * @return int
     */
    public function getIconHeight()
    {
        return 20;
    }

    /**
     * @return int
     */
    public function getIconWidth()
    {
        return 10;
    }

    public function canRender(PaymentTokenInterface $token)
    {
        return $token->getPaymentMethodCode() === AdyenHppConfigProvider::CODE;
    }
}