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
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Form;

use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Block\Form;
use Magento\Store\Model\ScopeInterface;

class PayByLink extends Form
{

    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/pay_by_link.phtml';

    /**
     * Uses the PBL days to expire config to return the date on which the links should expire
     * To be used in the PBL generation form as a suggestion
     *
     * @return string
     * @throws Exception
     */
    public function getDefaultExpiryDate()
    {
        try {
            $defaultExpiryDays = $this->_scopeConfig->getValue(
                AdyenPayByLinkConfigProvider::DAYS_TO_EXPIRE_CONFIG_PATH, ScopeInterface::SCOPE_STORE,
                $this->_storeManager->getStore()->getId()
            );
        } catch (NoSuchEntityException $e) {
            // There was a problem fetching the store, use the minimum expiry days as default
            $defaultExpiryDays = AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS;
        }
        return strval($this->getNowPlusDays($defaultExpiryDays, false));
    }

    /**
     * Returns the current date plus the minimum days to expire PBLs
     *
     * @throws Exception
     */
    public function getMinExpiryTimestamp()
    {
        return $this->getNowPlusDays(AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS);
    }

    /**
     * Returns the current date plus the maximum days to expire PBLs
     *
     * @throws Exception
     */
    public function getMaxExpiryTimestamp()
    {
        return $this->getNowPlusDays(AdyenPayByLinkConfigProvider::MAX_EXPIRY_DAYS);
    }

    /**
     *
     * Get the current date plus a supplied amount of days, either as a timestamp or a formatted date string
     *
     * @param int $days
     * @param bool $timestamp
     * @return float|int|string
     * @throws Exception
     */
    private function getNowPlusDays($days, $timestamp = true)
    {
        $date = new DateTime('now', new DateTimeZone('UTC'));
        try {
            $date->add(new DateInterval('P' . $days . 'D'));
        } catch (Exception $e) {
            /*
            Ignore exceptions and return original date, the validator will make sure that the selected
            date is within the accepted range
            */
        }
        return $timestamp ? $date->getTimestamp() * 1000 : $date->format(AdyenPayByLinkConfigProvider::DATE_FORMAT);
    }
}
