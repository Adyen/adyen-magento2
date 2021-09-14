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

namespace Adyen\Payment\Model\Config\Source;

/**
 * Class DonationAmounts
 * @package Adyen\Payment\Model\Config\Source
 */
class DonationAmounts
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
     * DonationAmounts constructor.
     * @param \Adyen\Payment\Helper\Data $_adyenHelper
     * @param \Magento\Store\Model\StoreManagerInterface $_storeManager
     */
    public function __construct(\Adyen\Payment\Helper\Data $_adyenHelper,
        \Magento\Store\Model\StoreManagerInterface $_storeManager)
    {
        $this->_adyenHelper = $_adyenHelper;
        $this->_storeManager = $_storeManager;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $validatedDonationAmounts = [];
        $donationAmounts = $this->_adyenHelper->getAdyenGivingConfigData('donation_amounts');
        if($donationAmounts){
            $donationAmounts =  explode(',', $donationAmounts);
            $baseCurrencyRate = $this->_storeManager->getStore()->getBaseCurrency()->getRate('EUR');
            foreach ($donationAmounts as $amount) {
                if ($amount * $baseCurrencyRate >= 1) {
                    array_push($validatedDonationAmounts, $amount);
                }
            }
        }
        return $validatedDonationAmounts;
    }
}
