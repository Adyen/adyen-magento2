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
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * RecurringDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\Model\Context $context
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Model\Context $context
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->appState = $context->getAppState();
    }


    /**
     * @param array $buildSubject
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function build(array $buildSubject)
    {
        $result = [];

        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();

        $storeId = null;
        if ($this->appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
            $storeId = $payment->getOrder()->getStoreId();
        }

        $enableOneclick = $this->adyenHelper->getAdyenAbstractConfigData('enable_oneclick', $storeId);
        $enableRecurring = $this->adyenHelper->getAdyenAbstractConfigData('enable_recurring', $storeId);

        if ($enableOneclick) {
            $result['enableOneclick'] = true;
        }

        if ($enableRecurring) {
            $result['enableRecurring'] = true;
        }

        if ($payment->getAdditionalInformation('store_cc') === '1') {
            $result['paymentMethod']['storeDetails'] = true;
        }

        return $result;
    }
}
