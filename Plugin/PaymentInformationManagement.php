<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Plugin;

use Adyen\Payment\Helper\PaymentMethodsFilter;
use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Model\PaymentDetailsFactory;
use Magento\Checkout\Model\PaymentInformationManagement as MagentoPaymentInformationManagement;

class PaymentInformationManagement
{
    protected PaymentMethodsFilter $paymentMethodsFilter;

    public function __construct(
        PaymentMethodsFilter $paymentMethodsFilter
    ) {
        $this->paymentMethodsFilter = $paymentMethodsFilter;
    }

    public function afterGetPaymentInformation(
        MagentoPaymentInformationManagement $magentoPaymentInformationManagement,
        PaymentDetailsInterface $result,
        int $cartId
    ): PaymentDetailsInterface {
        $magentoPaymentMethods = $result->getPaymentMethods();

        list($magentoPaymentMethods, $adyenPaymentMethodsResponse) =
            $this->paymentMethodsFilter->sortAndFilterPaymentMethods($magentoPaymentMethods, $cartId);

        $result->setPaymentMethods($magentoPaymentMethods);

        $extensionAttributes = $result->getExtensionAttributes();
        $extensionAttributes->setAdyenPaymentMethodsResponse($adyenPaymentMethodsResponse);
        $result->setExtensionAttributes($extensionAttributes);

        return $result;
    }
}
