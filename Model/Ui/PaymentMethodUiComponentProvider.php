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

use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodFactory;
use Adyen\Payment\Helper\Recurring;
use Adyen\Payment\Helper\Vault;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;

class PaymentMethodUiComponentProvider implements TokenUiComponentProviderInterface
{
    /** @var TokenUiComponentInterfaceFactory  */
    private $componentFactory;

    /** @var Data  */
    private $adyenHelper;

    /** @var Vault */
    private $vaultHelper;

    /** @var PaymentMethodFactory */
    private $paymentMethodFactory;

    /**
     * @param TokenUiComponentInterfaceFactory $componentFactory
     * @param Data $adyenHelper
     * @param Vault $vaultHelper
     */
    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory,
        Data $adyenHelper,
        Vault $vaultHelper,
        PaymentMethodFactory $paymentMethodFactory
    ) {
        $this->componentFactory = $componentFactory;
        $this->adyenHelper = $adyenHelper;
        $this->vaultHelper = $vaultHelper;
        $this->paymentMethodFactory = $paymentMethodFactory;
    }

    /**
     * Get UI component for token
     *
     * @param PaymentTokenInterface $paymentToken
     * @return TokenUiComponentInterface
     * @throws PaymentMethodException
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken): TokenUiComponentInterface
    {
        $tokenType = $this->vaultHelper->getAdyenTokenType($paymentToken);
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        $adyenPaymentMethod = $this->paymentMethodFactory::createAdyenPaymentMethod($details['type']);
        $details['icon'] = $this->adyenHelper->getVariantIcon($details['type']);
        $details['created'] = $paymentToken->getCreatedAt();
        $details['displayToken'] = $tokenType === Recurring::CARD_ON_FILE;
        $details['label'] = $adyenPaymentMethod->getLabel();

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
