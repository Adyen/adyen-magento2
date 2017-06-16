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

namespace Adyen\Payment\Model\Api;

use Magento\Framework\DataObject;

class PaymentRequest extends DataObject
{

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * @var \Adyen\Payment\Model\RecurringType
     */
    protected $_recurringType;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_appState;

    /**
     * PaymentRequest constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Model\RecurringType $recurringType
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Model\RecurringType $recurringType,
        array $data = []
    ) {
        $this->_encryptor = $encryptor;
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
        $this->_recurringType = $recurringType;
        $this->_appState = $context->getAppState();
    }

    private function createClient($storeId) {
        // initialize client
        $webserviceUsername = $this->_adyenHelper->getWsUsername($storeId);
        $webservicePassword = $this->_adyenHelper->getWsPassword($storeId);

        $client = new \Adyen\Client();
        $client->setApplicationName("Magento 2 plugin");
        $client->setUsername($webserviceUsername);
        $client->setPassword($webservicePassword);

        if ($this->_adyenHelper->isDemoMode($storeId)) {
            $client->setEnvironment(\Adyen\Environment::TEST);
        } else {
            $client->setEnvironment(\Adyen\Environment::LIVE);
        }

        // assign magento log
        $client->setLogger($this->_adyenLogger);

        return $client;
    }

    /**
     * @param $payment
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorise3d($payment)
    {
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account", $storeId);
        $shopperIp = $order->getRemoteIp();

        $md = $payment->getAdditionalInformation('md');
        $paResponse = $payment->getAdditionalInformation('paResponse');

        $browserInfo = ['userAgent' => $_SERVER['HTTP_USER_AGENT'], 'acceptHeader' => $_SERVER['HTTP_ACCEPT']];
        $request = [
            "merchantAccount" => $merchantAccount,
            "browserInfo" => $browserInfo,
            "md" => $md,
            "paResponse" => $paResponse,
            "shopperIP" => $shopperIp
        ];

        try {
            $client = $this->createClient($storeId);
            $service = new \Adyen\Service\Payment($client);
            $result = $service->authorise3D($request);
        } catch(\Adyen\AdyenException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('3D secure failed'));
        }

        return $result;
    }

    /**
     * @param $shopperReference
     * @param $storeId
     * @return array
     * @throws \Exception
     */
    public function getRecurringContractsForShopper($shopperReference, $storeId)
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
                $this->_adyenLogger->addError($exception);
                throw($exception);
            }
        }
        return $recurringContracts;
    }

    /**
     * @param $shopperReference
     * @param $storeId
     * @param $recurringType
     * @return mixed
     */
    public function listRecurringContractByType($shopperReference, $storeId, $recurringType)
    {
        // rest call to get list of recurring details
        $contract = ['contract' => $recurringType];
        $request = [
            "merchantAccount"    => $this->_adyenHelper->getAdyenAbstractConfigData('merchant_account', $storeId),
            "shopperReference"   => $shopperReference,
            "recurring" => $contract,
        ];

        // call lib
        $client = $this->createClient($storeId);
        $service = new \Adyen\Service\Recurring($client);
        $result = $service->listRecurringDetails($request);

        return $result;
    }

    /**
     * Disable a recurring contract
     *
     * @param $recurringDetailReference
     * @param $shopperReference
     * @param $storeId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function disableRecurringContract($recurringDetailReference, $shopperReference, $storeId)
    {
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account", $storeId);

        $request = [
            "merchantAccount" => $merchantAccount,
            "shopperReference" => $shopperReference,
            "recurringDetailReference" => $recurringDetailReference
        ];

        // call lib
        $client = $this->createClient($storeId);
        $service = new \Adyen\Service\Recurring($client);

        try {
            $result = $service->disable($request);
        } catch(\Exception $e) {
            $this->_adyenLogger->info($e->getMessage());
        }

        if (isset($result['response']) && $result['response'] == '[detail-successfully-disabled]') {
            return true;
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('Failed to disable this contract'));
        }
    }
}