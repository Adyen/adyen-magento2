<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\AdminMessage;

class AdyenGivingMessage implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     *
     * @var array
     */
    protected $removedDonationAmounts = [];

    const MESSAGE_IDENTITY = 'Adyen Giving message';

    /**
     * AdyenGivingMessage constructor.
     * @param \Adyen\Payment\Helper\Data $_adyenHelper
     * @param \Magento\Store\Model\StoreManagerInterface $_storeManager
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $_adyenHelper,
        \Magento\Store\Model\StoreManagerInterface $_storeManager
    ) {
        $this->_adyenHelper = $_adyenHelper;
        $this->_storeManager = $_storeManager;
    }

    public function getIdentity()
    {
        // Retrieve unique message identity
        return self::MESSAGE_IDENTITY;
    }

    public function isDisplayed()
    {
        $isAdyenGivingEnabled = $this->_adyenHelper->getAdyenGivingConfigData('active');
        // Only execute if Adyen giving is enabled
        if ($isAdyenGivingEnabled) {
            $donationAmounts = $this->_adyenHelper->getAdyenGivingConfigData('donation_amounts');
            if($donationAmounts){
                $donationAmounts =  explode(',', $donationAmounts);
                $donationAmounts = array_map('trim', $donationAmounts);
                $baseCurrencyRate = $this->_storeManager->getStore()->getBaseCurrency()->getRate('EUR');
                foreach ($donationAmounts as $amount) {
                    if ($amount * $baseCurrencyRate < 1) {
                        array_push($this->removedDonationAmounts, $amount);
                    }
                }
            }
            if (count($this->removedDonationAmounts) > 0) {
                return true;
           };
        }
        return false;
    }

    public function getText()
    {
        return 'For the Adyen giving the amounts that are less than 1 EUR are removed. In this case the amounts '
            . implode(",", $this->removedDonationAmounts);
    }

    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }
}
