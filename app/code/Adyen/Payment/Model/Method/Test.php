<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Adyen\Payment\Model\Method;

/**
 * Class Checkmo
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 */
class Test extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_CHECKMO_CODE = 'test';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CHECKMO_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'Magento\OfflinePayments\Block\Form\Checkmo';

    /**
     * @var string
     */
    protected $_infoBlockType = 'Magento\OfflinePayments\Block\Info\Checkmo';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * @return string
     */
    public function getPayableTo()
    {
        return $this->getConfigData('payable_to');
    }

    /**
     * @return string
     */
    public function getMailingAddress()
    {
        return $this->getConfigData('mailing_address');
    }
}
