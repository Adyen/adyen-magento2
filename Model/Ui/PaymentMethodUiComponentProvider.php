<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Data;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;

class PaymentMethodUiComponentProvider implements TokenUiComponentProviderInterface
{
    /**
     * @var TokenUiComponentInterfaceFactory
     */
    private $componentFactory;

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @param TokenUiComponentInterfaceFactory $componentFactory
     * @param Data $adyenHelper
     */
    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory,
        Data $adyenHelper
    ) {
        $this->componentFactory = $componentFactory;
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * Get UI component for token
     *
     * @param PaymentTokenInterface $paymentToken
     * @return TokenUiComponentInterface
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken): TokenUiComponentInterface
    {
        $type = $paymentToken->getType();
        $details['icon'] = $this->adyenHelper->getVariantIcon($type);
        $details['created'] = $paymentToken->getCreatedAt();

        $component = $this->componentFactory->create(
            [
                'config' => [
                    'code' => AdyenHppConfigProvider::HPP_VAULT_CODE,
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $details,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
                ],
                'name' => 'Adyen_Payment/js/view/payment/method-renderer/payment_method_vault'
            ]
        );

        return $component;
    }
}
