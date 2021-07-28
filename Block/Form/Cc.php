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

namespace Adyen\Payment\Block\Form;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Installments;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;

class Cc extends \Magento\Payment\Block\Form\Cc
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/cc.phtml';

    /**
     * @var \Adyen\Payment\Helper\Data
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
     * @var Installments
     */
    private $chargedCurrency;

    /**
     * @var \Magento\Backend\Model\Session\Quote
     */
    private $backendCheckoutSession;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * Cc constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Model\Config $paymentConfig,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Backend\Model\Session\Quote $backendCheckoutSession,
        Installments $installmentsHelper,
        ChargedCurrency $chargedCurrency,
        AdyenLogger $adyenLogger,
        Config $configHelper,
        Session $customerSession,
        array $data = []
    ) {
        parent::__construct($context, $paymentConfig);
        $this->adyenHelper = $adyenHelper;
        $this->appState = $context->getAppState();
        $this->checkoutSession = $checkoutSession;
        $this->backendCheckoutSession = $backendCheckoutSession;
        $this->installmentsHelper = $installmentsHelper;
        $this->chargedCurrency = $chargedCurrency;
        $this->adyenLogger = $adyenLogger;
        $this->configHelper = $configHelper;
        $this->customerSession = $customerSession;
    }

    /**
     * @return string
     * @deprecated this was being used to load a different version of the Web Components library in the
     * admin panel, but now the same frontend bundle is also loaded there. Will be removed in 8.0.0
     */
    public function getCheckoutCardComponentJs()
    {
        return $this->adyenHelper->getCheckoutCardComponentJs($this->checkoutSession->getQuote()->getStore()->getId());
    }

    /**
     * @return string
     * @throws \Adyen\AdyenException
     */
    public function getClientKey()
    {
        return $this->adyenHelper->getClientKey();
    }

    /**
     * @return string
     */
    public function getCheckoutEnvironment()
    {
        return $this->adyenHelper->getCheckoutEnvironment($this->checkoutSession->getQuote()->getStore()->getId());
    }

    /**
     * Retrieve has verification configuration
     *
     * @return bool
     */
    public function hasVerification()
    {
        // On Backend always use MOTO
        if ($this->appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
            return false;
        }
        return true;
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

        $availableTypes = $this->adyenHelper->getAdyenCcConfigData('cctypes');
        if ($availableTypes) {
            $availableTypes = explode(',', $availableTypes);
            foreach (array_keys($ccTypes) as $code) {
                if (in_array($code, $availableTypes)) {
                    $types[$ccTypes[$code]['code_alt']] = $code;
                }
            }
        }

        return $types;
    }

    /**
     * @return mixed
     */
    public function isVaultEnabled()
    {
        return $this->adyenHelper->isCreditCardVaultEnabled();
    }

    /**
     * @return string
     */
    public function getFormattedInstallments()
    {
        try {
            $quote = $this->_appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML ?
                $this->backendCheckoutSession->getQuote() :
                $this->checkoutSession->getQuote();

            $quoteAmountCurrency = $this->chargedCurrency->getQuoteAmountCurrency($quote);

            return $this->installmentsHelper->formatInstallmentsConfig(
                $this->adyenHelper->getAdyenCcConfigData('installments',
                    $this->_storeManager->getStore()->getId()
                ),
                $this->adyenHelper->getAdyenCcTypes(),
                $quoteAmountCurrency->getAmount()
            );
        } catch (\Throwable $e) {
            $this->adyenLogger->error(
                'There was an error fetching the installments config: ' . $e->getMessage()
            );
            return '{}';
        }
    }

    /**
     * @return bool
     */
    public function getHasHolderName()
    {
        return (bool)$this->configHelper->getHasHolderName();
    }

    /**
     * @return bool
     */
    public function getHolderNameRequired()
    {
        return $this->configHelper->getHolderNameRequired() && $this->configHelper->getHasHolderName();
    }

    /**
     * @return bool
     */
    public function getEnableStoreDetails()
    {
        $enableOneclick = (bool)$this->adyenHelper->getAdyenAbstractConfigData('enable_oneclick');
        $enableVault = $this->adyenHelper->isCreditCardVaultEnabled();
        $loggedIn = $this->customerSession->isLoggedIn();
        return ($enableOneclick || $enableVault) && $loggedIn;
    }

    /**
     * @return bool
     */
    public function getEnableRisk()
    {
        try {
            return $this->appState->getAreaCode() !== \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE;
        } catch (LocalizedException $exception) {
            // Suppress exception, assume that risk should be enabled
            return true;
        }
    }
}
