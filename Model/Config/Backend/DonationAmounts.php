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

namespace Adyen\Payment\Model\Config\Backend;

use Adyen\Payment\Helper\Data;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class DonationAmounts
 * @package Adyen\Payment\Model\Config\Source
 */
class DonationAmounts extends Value
{
    const MIN_DONATION_IN_EUR = 1;

    /**
     * @var Data
     */
    protected $_adyenHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function validateBeforeSave()
    {
        if (!$this->validateDonationAmounts(explode(',', $this->getValue()))) {
            throw new \Magento\Framework\Validator\Exception(
                new Phrase(
                    'The Adyen Giving donation amounts are not valid, ' .
                    'please enter amounts higher than ' . self::MIN_DONATION_IN_EUR . 'EUR separated by commas. ' .
                    'Also, make sure that there is a currency rate for EUR in your Magento system.'
                )
            );
        }
    }

    public function validateDonationAmounts($amounts = array())
    {
        if ((bool)$this->getFieldsetDataValue('active')) {

            $baseCurrencyRate = $this->storeManager->getStore()->getBaseCurrency()->getRate('EUR');

            // Fail if the field is empty, the array is associative, or if there isn't a currency rate for EUR
            if (empty($amounts) || array_values($amounts) !== $amounts || !$baseCurrencyRate) {
                return false;
            }

            foreach ($amounts as $amount) {
                // Fail if one of the amounts is empty, not numeric, or less than the minimum donation amount
                if ($amount === '' || !is_numeric($amount) || $amount * $baseCurrencyRate < self::MIN_DONATION_IN_EUR) {
                    return false;
                }
            }
        }
        return true;
    }
}
