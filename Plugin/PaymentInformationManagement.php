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

use Adyen\Payment\Helper\MagentoPaymentDetails;
use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Model\PaymentDetailsFactory;
use Magento\Checkout\Model\PaymentInformationManagement as MagentoPaymentInformationManagement;

class PaymentInformationManagement
{
    protected MagentoPaymentDetails $magentoPaymentDetailsHelper;

    public function __construct(
        MagentoPaymentDetails $magentoPaymentDetailsHelper
    ) {
        $this->magentoPaymentDetailsHelper = $magentoPaymentDetailsHelper;
    }

    public function afterGetPaymentInformation(
        MagentoPaymentInformationManagement $magentoPaymentInformationManagement,
        PaymentDetailsInterface $result,
        int $cartId
    ): PaymentDetailsInterface {
        return $this->magentoPaymentDetailsHelper->addAdyenExtensionAttributes($result, $cartId);
    }
}
