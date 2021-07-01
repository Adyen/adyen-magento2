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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Block\Form;
use Magento\Store\Model\ScopeInterface;

class PayByLink extends Form
{

    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/pay_by_link.phtml';

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
        return $this->getNowPlusDays($defaultExpiryDays, false);
    }

    public function getMinExpiryDate(): int
    {
        return $this->getNowPlusDays(AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS);
    }

    public function getMaxExpiryDate(): int
    {
        return $this->getNowPlusDays(AdyenPayByLinkConfigProvider::MAX_EXPIRY_DAYS);
    }

    private function getNowPlusDays($days, $timestamp = true)
    {
        $date = new DateTime('now', new DateTimeZone('UTC'));
        try {
            $date->add(new DateInterval('P' . $days . 'D'));
        } catch (\Exception $e) {
            /*
            Ignore exceptions and return original date, the validator will make sure that the selected
            date is within the accepted range
            */
        }
        return $timestamp ? $date->getTimestamp() * 1000 : $date->format(AdyenPayByLinkConfigProvider::DATE_FORMAT);
    }
}
