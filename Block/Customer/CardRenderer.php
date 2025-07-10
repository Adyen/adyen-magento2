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

namespace Adyen\Payment\Block\Customer;

use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Helper\Data;
use Magento\Framework\View\Element\Template;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;
use Magento\Payment\Model\CcConfigProvider;

class CardRenderer extends AbstractCardRenderer
{
    protected Data $adyenHelper;
    protected Vault $vaultHelper;

    public function __construct(
        Template\Context $context,
        CcConfigProvider $iconsProvider,
        array $data,
        Data $adyenHelper,
        Vault $vaultHelper
    ) {
        parent::__construct($context, $iconsProvider, $data);
        $this->adyenHelper = $adyenHelper;
        $this->vaultHelper = $vaultHelper;
    }

    /**
     * Returns true if methodCode = adyen_cc OR (methodCode = adyen_hpp AND maskedCC exists in details. For googlepay)
     *
     * @param PaymentTokenInterface $token
     * @return boolean
     */
    public function canRender(PaymentTokenInterface $token): bool
    {
        $paymentMethodCode = $token->getPaymentMethodCode();
        $details = json_decode($token->getTokenDetails() ?: '{}', true);
        return $paymentMethodCode === AdyenCcConfigProvider::CODE ||
            ($this->vaultHelper->isAdyenPaymentCode($paymentMethodCode) && array_key_exists('maskedCC', $details));
    }

    /**
     * @return string
     */
    public function getNumberLast4Digits()
    {
        return !empty($this->getTokenDetails()['maskedCC']) ? $this->getTokenDetails()['maskedCC'] : "";
    }

    /**
     * @return string
     */
    public function getExpDate()
    {
        return $this->getTokenDetails()['expirationDate'];
    }

    /**
     * @return string
     */
    public function getIconUrl()
    {
        return $this->adyenHelper->getVariantIcon($this->getTokenDetails()['type'])['url'];
    }

    /**
     * @return int
     */
    public function getIconHeight()
    {
        return $this->adyenHelper->getVariantIcon($this->getTokenDetails()['type'])['height'];
    }

    /**
     * @return int
     */
    public function getIconWidth()
    {
        return $this->adyenHelper->getVariantIcon($this->getTokenDetails()['type'])['width'];
    }
}
