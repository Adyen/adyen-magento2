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

use Magento\Payment\Gateway\Request\BuilderInterface;

class RecurringDataBuilder implements BuilderInterface
{
    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * @var \Adyen\Payment\Helper\Requests
     */
    private $adyenRequestsHelper;

    /**
     * RecurringDataBuilder constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Adyen\Payment\Helper\Requests $adyenRequestsHelper
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Adyen\Payment\Helper\Requests $adyenRequestsHelper
    ) {
        $this->appState = $context->getAppState();
        $this->adyenRequestsHelper = $adyenRequestsHelper;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $storeId = $payment->getOrder()->getStoreId();
        $request['body'] = $this->adyenRequestsHelper->buildRecurringData(
            $storeId,
            $payment,
            []
        );
        return $request;
    }
}
