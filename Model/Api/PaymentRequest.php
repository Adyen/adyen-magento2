<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\RecurringType;
use Magento\Framework\App\State;
use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Sales\Model\Order\Payment;

class PaymentRequest extends DataObject
{
    protected EncryptorInterface $_encryptor;

    protected Data $_adyenHelper;

    protected AdyenLogger $_adyenLogger;

    protected RecurringType $_recurringType;

    protected State $_appState;

    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        RecurringType $recurringType,
        array $data = []
    ) {
        $this->_encryptor = $encryptor;
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
        $this->_recurringType = $recurringType;
        $this->_appState = $context->getAppState();
    }

    public function authorise3d(Payment $payment): mixed
    {
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        $md = $payment->getAdditionalInformation('md');
        $paResponse = $payment->getAdditionalInformation('paResponse');
        $redirectResult = $payment->getAdditionalInformation('redirectResult');
        $paymentData = $payment->getAdditionalInformation('paymentData');

        $payment->unsAdditionalInformation('redirectResult');
        $payment->unsAdditionalInformation('paymentData');
        $payment->unsAdditionalInformation('paRequest');
        $payment->unsAdditionalInformation('md');

        $details = [];
        if (!empty($md) && !empty($paResponse)) {
            $details["MD"] = $md;
            $details["PaRes"] = $paResponse;
        }

        if (!empty($redirectResult)) {
            $details["redirectResult"] = $redirectResult;
        }

        $request = [
            "paymentData" => $paymentData,
            "details" => $details
        ];

        try {
            $client = $this->_adyenHelper->initializeAdyenClient($storeId);
            $service = $this->_adyenHelper->createAdyenCheckoutService($client);
            $result = $service->paymentsDetails($request);
        } catch (AdyenException $e) {
            throw new LocalizedException(__('3D secure failed'));
        }

        return $result;
    }

    public function getRecurringContractsForShopper(string $shopperReference, int $storeId): array
    {
        $recurringContracts = [];
        $recurringTypes = $this->_recurringType->getAllowedRecurringTypesForListRecurringCall();

        foreach ($recurringTypes as $recurringType) {
            try {
                // merge ONECLICK and RECURRING into one record with recurringType ONECLICK,RECURRING
                $listRecurringContractByType =
                    $this->listRecurringContractByType($shopperReference, $storeId, $recurringType);

                if (isset($listRecurringContractByType['details'])) {
                    foreach ($listRecurringContractByType['details'] as $recurringContractDetails) {
                        if (isset($recurringContractDetails['RecurringDetail'])) {
                            $recurringContract = $recurringContractDetails['RecurringDetail'];

                            if (isset($recurringContract['recurringDetailReference'])) {
                                $recurringDetailReference = $recurringContract['recurringDetailReference'];
                                // check if recurring reference is already in array
                                if (isset($recurringContracts[$recurringDetailReference])) {
                                    /*
                                     * recurring reference already exists so recurringType is possible
                                     * for ONECLICK and RECURRING
                                     */
                                    $recurringContracts[$recurringDetailReference]['recurring_type'] =
                                        "ONECLICK,RECURRING";
                                } else {
                                    $recurringContracts[$recurringDetailReference] = $recurringContract;
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $exception) {
                // log exception
                $this->_adyenLogger->error($exception);
                throw($exception);
            }
        }
        return $recurringContracts;
    }

    public function listRecurringContractByType(string $shopperReference, int $storeId, string $recurringType): mixed
    {
        // rest call to get list of recurring details
        $contract = ['contract' => $recurringType];
        $request = [
            "merchantAccount" => $this->_adyenHelper->getAdyenAbstractConfigData('merchant_account', $storeId),
            "shopperReference" => $this->_adyenHelper->padShopperReference($shopperReference),
            "recurring" => $contract,
        ];

        // call lib
        $client = $this->_adyenHelper->initializeAdyenClient($storeId);
        $service = $this->_adyenHelper->createAdyenRecurringService($client);
        return $service->listRecurringDetails($request);
    }

    public function disableRecurringContract(
        string $recurringDetailReference,
        string $shopperReference,
        int $storeId
    ): bool {
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account", $storeId);
        $shopperReference = $this->_adyenHelper->padShopperReference($shopperReference);
        $request = [
            "merchantAccount" => $merchantAccount,
            "shopperReference" => $shopperReference,
            "recurringDetailReference" => $recurringDetailReference
        ];

        // call lib
        $client = $this->_adyenHelper->initializeAdyenClient($storeId);
        $service = $this->_adyenHelper->createAdyenRecurringService($client);

        try {
            $result = $service->disable($request);
        } catch (\Exception $e) {
            $this->_adyenLogger->info($e->getMessage());
        }

        if (isset($result['response']) && $result['response'] == '[detail-successfully-disabled]') {
            return true;
        } else {
            throw new LocalizedException(__('Failed to disable this contract'));
        }
    }
}
