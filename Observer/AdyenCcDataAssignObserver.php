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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Class DataAssignObserver
 */
class AdyenCcDataAssignObserver extends AdyenAbstractDataAssignObserver
{
    /**
     * Approved root level keys from additional data array
     *
     * @var array
     */
    protected $approvedAdditionalDataKeys = [
        self::STATE_DATA,
        self::COMBO_CARD_TYPE,
        self::NUMBER_OF_INSTALLMENTS
    ];

    /**
     * Approved root level keys from the checkout component's state data object
     *
     * @var array
     */
    protected $approvedStateDataKeys = [
        self::BROWSER_INFO,
        self::PAYMENT_METHOD,
        self::RISK_DATA,
        self::STORE_PAYMENT_METHOD
    ];

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        // Get request fields
        $data = $this->readDataArgument($observer);

        $additionalData = $this->getValidatedAdditionalData($data);

        // Set additional data in the payment
        $paymentInfo = $this->readPaymentModelArgument($observer);
        foreach ($additionalData as $key => $data) {
            $paymentInfo->setAdditionalInformation($key, $data);
        }

        // set ccType
        if (!empty($additionalData[self::CC_TYPE])) {
            $paymentInfo->setCcType($additionalData[self::CC_TYPE]);
        }
    }
}
