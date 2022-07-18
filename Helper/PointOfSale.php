<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\ApplicationInfo;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Quote\Model\Quote;

class PointOfSale
{
    /** @var Data */
    private $dataHelper;

    /** @var ProductMetadataInterface */
    private $productMetadata;

    public function __construct(
        Data $dataHelper,
        ProductMetadataInterface $productMetadata
    ) {
        $this->dataHelper = $dataHelper;
        $this->productMetadata = $productMetadata;
    }

    /**
     * Add SaleToAcquirerData to store recurring transactions and able to track platform and version
     * When upgrading to new version of library we can use the client methods
     *
     * @param $request
     * @param Quote $quote
     * @return mixed
     */
    public function addSaleToAcquirerData($request, Quote $quote)
    {
        $customerId = $this->getCustomerId($quote);
        $storeId = $quote->getStoreId();

        $saleToAcquirerData = [];

        // If customer exists add it into the request to store request
        if (!empty($customerId)) {
            $shopperEmail = $quote->getCustomerEmail();
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
    public function getCustomerId(Quote $quote): string
    {
        return $quote->getCustomerId();
    }
}