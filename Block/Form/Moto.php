<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Form;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Installments;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Backend\Model\Session\Quote;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template\Context;

class Moto extends \Magento\Payment\Block\Form\Cc
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/moto.phtml';

    /**
     * @var Data
     */
    protected $adyenHelper;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $appState;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var Installments
     */
    private $installmentsHelper;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var Quote
     */
    private $backendSession;

    /**
     * @param Context $context
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param Data $adyenHelper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param Installments $installmentsHelper
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     */
    public function __construct(
        Context $context,
        \Magento\Payment\Model\Config $paymentConfig,
        Data $adyenHelper,
        Session $checkoutSession,
        Installments $installmentsHelper,
        AdyenLogger $adyenLogger,
        Config $configHelper,
        Quote $backendSession
    ) {
        parent::__construct($context, $paymentConfig);
        $this->adyenHelper = $adyenHelper;
        $this->appState = $context->getAppState();
        $this->checkoutSession = $checkoutSession;
        $this->installmentsHelper = $installmentsHelper;
        $this->adyenLogger = $adyenLogger;
        $this->configHelper = $configHelper;
        $this->backendSession = $backendSession;
    }

    /**
     * @return string
     */
    public function getCheckoutEnvironment()
    {
        return $this->adyenHelper->getCheckoutEnvironment($this->checkoutSession->getQuote()->getStore()->getId());
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->adyenHelper->getStoreLocale($this->checkoutSession->getQuote()->getStore()->getId());
    }

    /**
     * Retrieve available credit card type codes by alt code
     *
     * @return array
     */
    public function getCcAvailableTypesByAlt()
    {
        $types = [];
        $ccTypes = $this->adyenHelper->getAdyenCcTypes();
        $availableTypes = explode(',', (string) $this->configHelper->getAdyenCcConfigData('cctypes'));

        foreach ($ccTypes as $code => $ccType) {
            if (in_array($code, $availableTypes)) {
                $types[$ccType['code_alt']] = $code;
            }
        }

        return $types;
    }

    /**
     * @return string
     */
    public function getFormattedInstallments()
    {
        try {
            $quoteData = $this->backendSession->getQuote()->getData();
            $amount = $quoteData['grand_total'];

            return $this->installmentsHelper->formatInstallmentsConfig(
                $this->configHelper->getAdyenCcConfigData('installments',
                    $this->_storeManager->getStore()->getId()
                ),
                $this->adyenHelper->getAdyenCcTypes(),
                $amount
            );
        } catch (\Throwable $e) {
            $this->adyenLogger->error(
                'There was an error fetching the installments config: ' . $e->getMessage()
            );
            return '{}';
        }
    }

    /**
     * @return string
     */
    public function getAmount()
    {
        try {
            $quoteData = $this->backendSession->getQuote()->getData();
            $grandTotal = $quoteData['grand_total'];
            $currenyCode = $this->backendSession->getQuote()->getCurrency()->getQuoteCurrencyCode();
            $value = $this->adyenHelper->formatAmount($grandTotal, $currenyCode);

            $amount = array("value" => $value, "currency" => $currenyCode);

            return json_encode($amount);
        } catch (\Throwable $e) {
            $this->adyenLogger->error(
                'There was an error fetching the amount for checkout component config: ' . $e->getMessage()
            );

            return '{}';
        }
    }

    /**
     * Returns the country code required for checkout component
     *
     * @return string
     */
    public function getCountryId(): string
    {
        return $this->backendSession->getQuote()->getBillingAddress()->getCountryId();
    }

    /**
     * @return array
     */
    public function getMotoMerchantAccounts() : array
    {
        $storeId = $this->backendSession->getStoreId();

        return $this->configHelper->getMotoMerchantAccounts($storeId);
    }
}
