<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 * @deprecated This class is deprecated and will be removed in a future release.
 *              Use an alternative service or class for similar requests.
 */

namespace Adyen\Payment\Model\Api;

use Adyen\AdyenException;
use Adyen\Model\Checkout\PaymentDetailsRequest;
use Adyen\Model\Recurring\DisableRequest;
use Adyen\Model\Recurring\RecurringDetailsRequest;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\RecurringType;
use Exception;
use Magento\Framework\App\State;
use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Sales\Model\Order\Payment;

class PaymentRequest extends DataObject
{
    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

    /**
     * @var Data
     */
    protected Data $adyenHelper;

    /**
     * @var AdyenLogger
     */
    protected AdyenLogger $adyenLogger;

    /**
     * @var Config
     */
    protected Config $configHelper;

    /**
     * @var RecurringType
     */
    protected RecurringType $recurringType;

    /**
     * @var State
     */
    protected State $appState;

    /**
     * @param Context $context
     * @param EncryptorInterface $encryptor
     * @param Data $adyenHelper
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     * @param RecurringType $recurringType
     * @param array $data
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        Config $configHelper,
        RecurringType $recurringType,
        array $data = []
    ) {
        $this->encryptor = $encryptor;
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->recurringType = $recurringType;
        $this->appState = $context->getAppState();
        $this->configHelper = $configHelper;
    }

    /**
     * @deprecated This method is deprecated and will be removed in a future release.
     *
     * @param Payment $payment
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function authorise3d(Payment $payment): array
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
            $client = $this->adyenHelper->initializeAdyenClient($storeId);
            $service = $this->adyenHelper->initializePaymentsApi($client);
            $response = $service->paymentsDetails(new PaymentDetailsRequest($request));
        } catch (AdyenException $e) {
            throw new LocalizedException(__('3D secure failed'));
        }

        return $response->toArray();
    }

    /**
     * @deprecated This method is redundant and no more used with the current flow. Will be deleted in the future releases.
     * @param string $shopperReference
     * @param int $storeId
     * @return array
     * @throws Exception
     */
    public function getRecurringContractsForShopper(string $shopperReference, int $storeId): array
    {
        $recurringContracts = [];
        $recurringTypes = $this->recurringType->getAllowedRecurringTypesForListRecurringCall();

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
            } catch (Exception $exception) {
                // log exception
                $this->adyenLogger->error($exception);
                throw($exception);
            }
        }
        return $recurringContracts;
    }

    /**
     * @deprecated This method is a part of deprecated parent and will be removed in the future releases.
     * @param string $shopperReference
     * @param int $storeId
     * @param string $recurringType
     * @return mixed
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function listRecurringContractByType(string $shopperReference, int $storeId, string $recurringType): mixed
    {
        // rest call to get list of recurring details
        $contract = ['contract' => $recurringType];
        $request = [
            "merchantAccount" => $this->configHelper->getAdyenAbstractConfigData('merchant_account', $storeId),
            "shopperReference" => $this->adyenHelper->padShopperReference($shopperReference),
            "recurring" => $contract,
        ];

        // call lib
        $client = $this->adyenHelper->initializeAdyenClient($storeId);
        $service = $this->adyenHelper->initializeRecurringApi($client);
        $response = $service->listRecurringDetails(new RecurringDetailsRequest($request));

        return (array)$response->jsonSerialize();
    }

    /**
     * @deprecated This method is redundant and will be removed in the coming major release.
     * @param string $recurringDetailReference
     * @param string $shopperReference
     * @param int $storeId
     * @return bool
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function disableRecurringContract(
        string $recurringDetailReference,
        string $shopperReference,
        int $storeId
    ): bool {
        $merchantAccount = $this->configHelper->getAdyenAbstractConfigData("merchant_account", $storeId);
        $shopperReference = $this->adyenHelper->padShopperReference($shopperReference);
        $request = [
            "merchantAccount" => $merchantAccount,
            "shopperReference" => $shopperReference,
            "recurringDetailReference" => $recurringDetailReference
        ];

        // call lib
        $client = $this->adyenHelper->initializeAdyenClient($storeId);
        $service = $this->adyenHelper->initializeRecurringApi($client);

        try {
            $response = $service->disable(new DisableRequest($request));
            $result = (array) $response->jsonSerialize();
        } catch (Exception $e) {
            $this->adyenLogger->info($e->getMessage());
        }

        if (isset($result['response']) && $result['response'] == '[detail-successfully-disabled]') {
            return true;
        } else {
            throw new LocalizedException(__('Failed to disable this contract'));
        }
    }
}
