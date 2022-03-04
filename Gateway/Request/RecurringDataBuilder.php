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

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Requests;
use Magento\Framework\Model\Context;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RecurringDataBuilder implements BuilderInterface
{
    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * @var Requests
     */
    private $adyenRequestsHelper;

    /**
     * @var PaymentMethods
     */
    private $paymentMethodsHelper;

    /**
     * RecurringDataBuilder constructor.
     *
     * @param Context $context
     * @param Requests $adyenRequestsHelper
     * @param PaymentMethods $paymentMethodsHelper
     */
    public function __construct(
        Context $context,
        Requests $adyenRequestsHelper,
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->appState = $context->getAppState();
        $this->adyenRequestsHelper = $adyenRequestsHelper;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function build(array $buildSubject): array
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $storeId = $payment->getOrder()->getStoreId();

        if ($this->paymentMethodsHelper->isCardPayment($payment)) {
            $body = $this->adyenRequestsHelper->buildCardRecurringData($storeId, $payment);
        } else {
            $body = $this->adyenRequestsHelper->buildAlternativePaymentRecurringData($storeId, $payment);
        }

        return [
            'body' => $body
        ];
    }
}
