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

use DateInterval;
use DateTime;
use DateTimeZone;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Block\Form;
use Magento\Store\Model\ScopeInterface;

class PayByLink extends Form
{

    const MIN_EXPIRY_DAYS = 1;
    const MAX_EXPIRY_DAYS = 70;
    const DAYS_TO_EXPIRE_CONFIG_PATH = "payment/adyen_pay_by_link/days_to_expire";

    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/pay_by_link.phtml';

    /**
     * Get the minimum expiry date
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function getDefaultExpiryDate(): int
    {
        $defaultExpiryDays = $this->_scopeConfig->getValue(
            self::DAYS_TO_EXPIRE_CONFIG_PATH, ScopeInterface::SCOPE_STORE,
            $this->_storeManager->getStore()->getId()
        );
        return $this->getNowPlusDays(2);
    }

    /**
     * @throws \Exception
     */
    public function getMinExpiryDate(): int
    {
        return $this->getNowPlusDays(self::MIN_EXPIRY_DAYS);
    }

    /**
     * @throws \Exception
     */
    public function getMaxExpiryDate(): int
    {
        return $this->getNowPlusDays(self::MAX_EXPIRY_DAYS);
    }

    public function getNowPlusDays($days)
    {
        $date = new DateTime('now', new DateTimeZone('UTC'));
        return $date->add(new DateInterval('P' . $days . 'D'))
                ->getTimestamp() * 1000;
    }
}
