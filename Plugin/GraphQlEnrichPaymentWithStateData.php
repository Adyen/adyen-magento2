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
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Lars Roettig <l.roettig@techdivisivion.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Plugin;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Tests\NamingConvention\true\string;

class GraphQlEnrichPaymentWithStateData
{
    /**
     * @var StateData
     */
    private StateData $stateData;

    /**
     * @param StateData $stateData
     */
    public function __construct(StateData $stateData)
    {
        $this->stateData = $stateData;
    }

    /**
     * Make sure that additional data enriched when payment is loaded from database.
     *
     * @param PaymentMethodManagementInterface $subject
     * @param PaymentInterface | null $payment
     * @param string|int $cartId
     * @return PaymentInterface | null
     */
    public function afterGet(
        PaymentMethodManagementInterface $subject,
        ?PaymentInterface $payment,
        string $cartId
    ): ?PaymentInterface {

        $stateData = $this->stateData->getStateData((int)$cartId);

        if ($payment === null || empty($stateData)) {
            return $payment;
        }

        $additionalData = $payment->getAdditionalData() ?? [];

        if (!is_array($additionalData)) {
            return $payment;
        }

        $additionalData[AdyenCcDataAssignObserver::STATE_DATA] = $stateData;
        $payment->setAdditionalData($additionalData);

        return $payment;
    }
}
