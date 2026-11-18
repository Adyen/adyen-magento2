<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\StateData as StateDataResourceModel;
use Adyen\Payment\Model\ResourceModel\StateData\Collection as StateDataCollection;
use Adyen\Payment\Model\StateData as AdyenStateData;
use Adyen\Payment\Model\StateDataFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

class StateData
{
    const CLEANUP_RESULT_CODES = array(
        "Authorised"
    );

    private StateDataCollection $stateDataCollection;
    private StateDataFactory $stateDataFactory;
    private StateDataResourceModel $stateDataResourceModel;
    private CheckoutStateDataValidator $checkoutStateDataValidator;
    private AdyenLogger $adyenLogger;
    private OrdersApi $ordersApiHelper;
    private CartRepositoryInterface $quoteRepository;
    private Data $adyenHelper;

    /**
     * Temporary (per request) storage of state data
     */
    private array $stateData = [];

    public function __construct(
        StateDataCollection $stateDataCollection,
        StateDataFactory $stateDataFactory,
        StateDataResourceModel $stateDataResourceModel,
        CheckoutStateDataValidator $checkoutStateDataValidator,
        AdyenLogger $adyenLogger,
        OrdersApi $ordersApiHelper,
        CartRepositoryInterface $quoteRepository,
        Data $adyenHelper
    ) {
        $this->stateDataCollection = $stateDataCollection;
        $this->stateDataFactory = $stateDataFactory;
        $this->stateDataResourceModel = $stateDataResourceModel;
        $this->checkoutStateDataValidator = $checkoutStateDataValidator;
        $this->adyenLogger = $adyenLogger;
        $this->ordersApiHelper = $ordersApiHelper;
        $this->quoteRepository = $quoteRepository;
        $this->adyenHelper = $adyenHelper;
    }

    public function cleanQuoteStateData(int $quoteId, string $resultCode): void
    {
        if (in_array($resultCode, self::CLEANUP_RESULT_CODES)) {
            $rows = $this->stateDataCollection->getStateDataRowsWithQuoteId($quoteId)->getItems();
            foreach ($rows as $row) {
                $this->removeStateData($row->getData('entity_id'));
            }
        }
    }

    public function setStateData(array $stateData, int $quoteId): void
    {
        $this->stateData[$quoteId] = $stateData;
    }

    public function getStateData(int $quoteId): array
    {
        return $this->stateData[$quoteId] ?? [];
    }

    /**
     * Returns the payment method type from state data
     */
    public function getPaymentMethodVariant(int $quoteId): string
    {
        $stateDataByQuoteId = $this->stateData[$quoteId];
        return $stateDataByQuoteId['paymentMethod']['type'];
    }

    /**
     * @param string $stateData
     * @param int $quoteId
     * @return AdyenStateData
     * @throws LocalizedException
     * @throws AlreadyExistsException
     */
    public function saveStateData(string $stateData, int $quoteId): AdyenStateData
    {
        // Decode payload from frontend
        $stateData = json_decode($stateData, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LocalizedException(__('State data call failed because the request was not a valid JSON'));
        }

        $stateData = $this->checkoutStateDataValidator->getValidatedAdditionalData($stateData);

        if (($stateData['paymentMethod']['type'] ?? null) === 'giftcard') {
            $giftcard = is_array($stateData['giftcard'] ?? null) ? $stateData['giftcard'] : [];
            $giftcard['balance'] = $this->getGiftcardBalance($stateData['paymentMethod'], $quoteId);
            $stateData['giftcard'] = $giftcard;
        }

        $stateData = json_encode($stateData);

        $stateDataObj = $this->stateDataFactory->create();
        $stateDataObj->setQuoteId($quoteId)->setStateData((string)$stateData);
        $this->stateDataResourceModel->save($stateDataObj);

        return $stateDataObj;
    }

    /**
     * Returns the consumable gift card balance from a server side balance check
     *
     * @throws LocalizedException
     */
    private function getGiftcardBalance(array $paymentMethod, int $quoteId): array
    {
        $quote = $this->quoteRepository->get($quoteId);
        $currency = $quote->getQuoteCurrencyCode();

        try {
            $response = $this->ordersApiHelper->getGiftcardBalance([
                'paymentMethod' => $paymentMethod,
                'amount' => [
                    'currency' => $currency,
                    'value' => $this->adyenHelper->formatAmount($quote->getGrandTotal(), $currency)
                ]
            ], (int) $quote->getStoreId());
        } catch (AdyenException $e) {
            throw new LocalizedException(__('The gift card balance could not be verified.'), $e);
        }

        $balance = $response->getBalance();
        $transactionLimit = $response->getTransactionLimit();

        if ($transactionLimit?->getValue() && $transactionLimit->getValue() <= (int) $balance?->getValue()) {
            $balance = $transactionLimit;
        }

        if (($balance?->getValue() ?? 0) <= 0) {
            throw new LocalizedException(__('The gift card has no available balance.'));
        }

        return ['currency' => $balance->getCurrency(), 'value' => $balance->getValue()];
    }

    /**
     * @throws NoSuchEntityException
     */
    public function removeStateData(int $stateDataId, ?int $quoteId = null): bool
    {
        $stateDataCollection = $this->stateDataCollection->addFieldToFilter('entity_id', $stateDataId);

        if (isset($quoteId)) {
            $stateDataCollection->addFieldToFilter('quote_id', $quoteId);
        }

        $stateDataCollection->getSelect();
        $stateDataObj = $stateDataCollection->getFirstItem();

        if (empty($stateDataObj->getData())) {
            throw new NoSuchEntityException();
        } else {
            try {
                $this->stateDataResourceModel->delete($stateDataObj);
            } catch (\Exception $e) {
                $this->adyenLogger->error('An error occurred while deleting state data: ' . $e->getMessage());
                return false;
            }

            return true;
        }
    }

    public function getStoredPaymentMethodIdFromStateData(array $stateData): ?string
    {
        return $stateData['paymentMethod']['storedPaymentMethodId'] ?? null;
    }
}
