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

namespace Adyen\Payment\Helper;

use Adyen\Payment\Exception\PointOfSaleException;
use Adyen\Payment\Model\ApplicationInfo;
use Adyen\Payment\Helper\Quote as QuoteHelper;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\Order;

class PointOfSale
{
    /** @var Data  */
    private $dataHelper;

    /** @var ProductMetadataInterface  */
    private $productMetadata;

    /** @var QuoteHelper */
    private $quoteHelper;

    /** @var ChargedCurrency */
    private $chargedCurrency;

    /** @var QuoteManagement */
    private $quoteManagement;

    public function __construct(
        Data $dataHelper,
        ProductMetadataInterface $productMetadata,
        QuoteHelper $quoteHelper,
        ChargedCurrency $chargedCurrency,
        QuoteManagement $quoteManagement
    ) {
        $this->dataHelper = $dataHelper;
        $this->productMetadata = $productMetadata;
        $this->quoteHelper = $quoteHelper;
        $this->chargedCurrency = $chargedCurrency;
        $this->quoteManagement = $quoteManagement;

    }

    /**
     * Add SaleToAcquirerData to store recurring transactions and able to track platform and version
     * When upgrading to new version of library we can use the client methods
     *
     * @param $request
     * @param Quote|null $quote
     * @param Order|null $order
     * @return array
     */
    public function addSaleToAcquirerData($request, Quote $quote = null, Order $order = null) : array
    {
        // If order is created from admin backend, use Order instead of Quote
        if (isset($order) && is_null($quote)) {
            $customerId = $order->getCustomerId();
            $storeId = $order->getStoreId();
            $shopperEmail = $order->getCustomerEmail();
        }
        else {
            $customerId = $this->getCustomerId($quote);
            $storeId = $quote->getStoreId();
            $shopperEmail = $quote->getCustomerEmail();
        }

        $saleToAcquirerData = [];

        // If customer exists add it into the request to store request
        if (!empty($customerId)) {
            $recurringContract = $this->dataHelper->getAdyenPosCloudConfigData('recurring_type', $storeId);

            if (!empty($recurringContract) && !empty($shopperEmail)) {
                $saleToAcquirerData['shopperEmail'] = $shopperEmail;
                $saleToAcquirerData['shopperReference'] = str_pad((string)$customerId, 3, '0', STR_PAD_LEFT);
                $saleToAcquirerData['recurringContract'] = $recurringContract;
            }
        }

        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::MERCHANT_APPLICATION]
        [ApplicationInfo::VERSION] = $this->dataHelper->getModuleVersion();
        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::MERCHANT_APPLICATION]
        [ApplicationInfo::NAME] = $this->dataHelper->getModuleName();
        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::EXTERNAL_PLATFORM]
        [ApplicationInfo::VERSION] = $this->productMetadata->getVersion();
        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::EXTERNAL_PLATFORM]
        [ApplicationInfo::NAME] = $this->productMetadata->getName();
        $saleToAcquirerDataBase64 = base64_encode(json_encode($saleToAcquirerData));
        $request['SaleToPOIRequest']['PaymentRequest']['SaleData']['SaleToAcquirerData'] = $saleToAcquirerDataBase64;

        return $request;
    }

    /**
     * This getter makes it possible to overwrite the customer id from other plugins
     * Use this function to get the customer id so we can keep using this plugin in the UCD
     */
    public function getCustomerId(Quote $quote): ?string
    {
        return $quote->getCustomerId();
    }

    /**
     * @throws NoSuchEntityException
     * @throws PointOfSaleException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function manuallyPlacePosOrder(string $reservedIncrementId, int $notificationAmount)
    {
        $quote = $this->quoteHelper->getQuoteByReservedOrderIncrementId($reservedIncrementId);
        $quoteAmountCurrency = $this->chargedCurrency->getQuoteAmountCurrency($quote);
        $quoteAmount = $quoteAmountCurrency->getAmount();
        $quoteCurrency = $quoteAmountCurrency->getCurrencyCode();
        $formattedQuoteAmount = $this->dataHelper->formatAmount($quoteAmount, $quoteCurrency);
        if ($formattedQuoteAmount !== $notificationAmount) {
            throw new PointOfSaleException(__(
                sprintf('Quote amount %s does not match notification amount %s', $formattedQuoteAmount, $notificationAmount)
            ));
        } elseif (is_null($quote->getCustomerId())) {
            $quote->setCheckoutMethod(CartManagementInterface::METHOD_GUEST);
        }

        if (is_null($quote->getCustomerEmail()) && !is_null($quote->getBillingAddress()->getEmail())) {
            $quote->setCustomerEmail($quote->getBillingAddress()->getEmail());
        }

        // Disable Cart Locking
        //$this->cartRepositoryPlugin->setDisabled();

        /* $this->logger->info(
            'Received Adyen Notification for non-existing Order {increment_id}' .
            ', the Order has been created based on Quote #{quote_id}',
            [
                'increment_id' => $reservedIncrementId,
                'quote_id' => $quote->getId(),
            ]
        );*/

        // Place Order
        return $this->quoteManagement->placeOrder($quote->getId());

    }
}
