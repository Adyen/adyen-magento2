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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * Helper class for anything related to Case Management (Manual Review)
 *
 * Class AdyenOrderPayment
 * @package Adyen\Payment\Helper
 */
class CaseManagement extends AbstractHelper
{
    const FRAUD_MANUAL_REVIEW = 'fraudManualReview';

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * AdyenOrderPayment constructor.
     *
     * @param Context $context
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Context $context,
        AdyenLogger $adyenLogger
    ) {
        parent::__construct($context);
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * Based on the passed array, check if manual review is required
     *
     * @param array $additionalData
     * @return bool
     */
    public function requiresManualReview(array $additionalData): bool
    {
        if (!array_key_exists(self::FRAUD_MANUAL_REVIEW, $additionalData)) {
            return false;
        }

        // Strict comparison to 'true' since it will be sent as a string
        if ($additionalData[self::FRAUD_MANUAL_REVIEW] === 'true') {
            return true;
        }

        return false;
    }
}
