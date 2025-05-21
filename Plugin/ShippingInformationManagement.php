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
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement as MagentoShippingInformationManagement;

class ShippingInformationManagement
{
    protected MagentoPaymentDetails $magentoPaymentDetailsHelper;

    public function __construct(
        MagentoPaymentDetails $magentoPaymentDetailsHelper
    ) {
        $this->magentoPaymentDetailsHelper = $magentoPaymentDetailsHelper;
    }

    public function afterSaveAddressInformation(
        MagentoShippingInformationManagement $shippingInformationManagement,
        PaymentDetailsInterface $result,
        int $cartId,
        ShippingInformationInterface $addressInformation
    ): PaymentDetailsInterface {
        return $this->magentoPaymentDetailsHelper->addAdyenExtensionAttributes($result, $cartId);
    }
}
