<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\ApplicationInfo;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class PointOfSale
{
    private Data $dataHelper;
    private ProductMetadataInterface $productMetadata;
    private Vault $vaultHelper;
    private PlatformInfo $platformInfo;

    public function __construct(
        Data $dataHelper,
        ProductMetadataInterface $productMetadata,
        Vault $vaultHelper,
        PlatformInfo $platformInfo
    ) {
        $this->dataHelper = $dataHelper;
        $this->productMetadata = $productMetadata;
        $this->vaultHelper = $vaultHelper;
        $this->platformInfo = $platformInfo;
    }

    public function addSaleToAcquirerData(array $request, Order $order) : array
    {
        $customerId = $order->getCustomerId();
        $storeId = $order->getStoreId();
        $shopperEmail = $order->getCustomerEmail();

        $saleToAcquirerData = [];

        // If customer exists add it into the request to store request
        if (!empty($customerId)) {
            $posRecurringEnabled = $this->vaultHelper->getPaymentMethodRecurringActive(
                AdyenPosCloudConfigProvider::CODE,
                $storeId
            );
            $recurringProcessingModel = $this->vaultHelper->getPaymentMethodRecurringProcessingModel(
                AdyenPosCloudConfigProvider::CODE,
                $storeId
            );

            if (!empty($posRecurringEnabled) && !empty($shopperEmail)) {
                $saleToAcquirerData['shopperEmail'] = $shopperEmail;
                $saleToAcquirerData['shopperReference'] = $this->dataHelper->padShopperReference($customerId);
                $saleToAcquirerData['recurringProcessingModel'] = $recurringProcessingModel;
            }
        }

        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::MERCHANT_APPLICATION]
        [ApplicationInfo::VERSION] = $this->platformInfo->getModuleVersion();
        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::MERCHANT_APPLICATION]
        [ApplicationInfo::NAME] = $this->platformInfo->getModuleName();
        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::EXTERNAL_PLATFORM]
        [ApplicationInfo::VERSION] = $this->productMetadata->getVersion();
        $saleToAcquirerData[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::EXTERNAL_PLATFORM]
        [ApplicationInfo::NAME] = $this->productMetadata->getName();
        $saleToAcquirerDataBase64 = base64_encode(json_encode($saleToAcquirerData));
        $request['SaleToPOIRequest']['PaymentRequest']['SaleData']['SaleToAcquirerData'] = $saleToAcquirerDataBase64;

        return $request;
    }

    public function getCustomerId(Quote $quote): ?string
    {
        return $quote->getCustomerId();
    }

    public function getFormattedInstallments(
        array $installments,
        float $amount,
        string $currencyCode,
        int $precision
    ): array {
        $formattedInstallments = [];

        foreach ($installments as $minAmount => $installmentsAmounts) {
            foreach ($installmentsAmounts as $installment) {
                if ($amount >= $minAmount) {
                    $dividedAmount = number_format($amount / $installment, $precision);
                    $formattedInstallments[$installment] =
                        sprintf("%s x %s %s", $installment, $dividedAmount, $currencyCode);
                }
            }
        }

        return $formattedInstallments;
    }
}
