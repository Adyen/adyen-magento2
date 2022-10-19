<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Form;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ConnectedTerminals;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PointOfSale;
use Magento\Backend\Model\Session\Quote;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Block\Form;

class PosCloud extends Form
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/pos_cloud.phtml';

    /**
     * @var ConnectedTerminals
     */
    protected $connectedTerminalsHelper;

    /**
     * @var Data
     */
    protected $adyenHelper;

    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var Quote
     */
    protected $backendSession;

    /**
     * @var PointOfSale
     */
    protected $posHelper;

    /**
     * @param Template\Context $context
     * @param ConnectedTerminals $connectedTerminalsHelper
     * @param SerializerInterface $serializer
     * @param Data $adyenHelper
     * @param Quote $backendSession
     * @param PointOfSale $posHelper
     * @param Config $configHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ConnectedTerminals $connectedTerminalsHelper,
        SerializerInterface $serializer,
        Data $adyenHelper,
        Quote $backendSession,
        PointOfSale $posHelper,
        Config $configHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->connectedTerminalsHelper = $connectedTerminalsHelper;
        $this->serializer = $serializer;
        $this->adyenHelper = $adyenHelper;
        $this->backendSession = $backendSession;
        $this->posHelper = $posHelper;
        $this->configHelper = $configHelper;
    }

    /**
     * @return array|mixed
     * @throws \Adyen\AdyenException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConnectedTerminals()
    {
        $connectedTerminals = $this->connectedTerminalsHelper->getConnectedTerminals();

        if (!empty($connectedTerminals['uniqueTerminalIds'])) {
            return $connectedTerminals['uniqueTerminalIds'];
        }

        return [];
    }

    public function getFormattedInstallments(): array
    {
        $serialisedInstallments = $this->configHelper->getAdyenPosCloudConfigData('installments');
        $formattedInstallments = [];

        if (isset($serialisedInstallments)) {
            $installments = $this->serializer->unserialize($serialisedInstallments);

            $amount = $this->backendSession->getQuote()->getGrandTotal();
            $currencyCode = $this->backendSession->getQuote()->getCurrency()->getQuoteCurrencyCode();
            $precision = $this->adyenHelper->decimalNumbers($currencyCode);

            $formattedInstallments = $this->posHelper->getFormattedInstallments(
                $installments,
                $amount,
                $currencyCode,
                $precision
            );
        }

        return $formattedInstallments;
    }

    public function hasInstallment()
    {
        return $this->configHelper->getAdyenPosCloudConfigData('enable_installments');
    }

    public function hasFundingSource(): bool
    {
        if ($this->backendSession->getQuote()->getBillingAddress() === null) {
            return false;
        }

        $countryId = $this->backendSession->getQuote()->getBillingAddress()->getCountryId();
        $currencyCode = $this->backendSession->getQuote()->getQuoteCurrencyCode();

        $allowedCurrenciesByCountry = [
            'BR' => 'BRL',
            'MX' => 'MXN'
        ];

        return isset($allowedCurrenciesByCountry[$countryId]) &&
            $currencyCode === $allowedCurrenciesByCountry[$countryId];
    }

    public function getFundingSourceOptions(): array
    {
        return [
            'credit' => 'Credit Card',
            'debit' => 'Debit Card'
        ];
    }
}
