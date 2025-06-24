<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\GiftcardPayment;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order;

class MerchantRiskIndicatorDataBuilder implements BuilderInterface
{
    const ADDRESS_INDICATOR_SHIP_TO_BILLING_ADDRESS = 'shipToBillingAddress';
    const ADDRESS_INDICATOR_SHIP_TO_NEW_ADDRESS = 'shipToNewAddress';
    const ADDRESS_INDICATOR_OTHER = 'other';
    const DELIVERY_TIMEFRAME_ELECTRONIC_DELIVERY = 'electronicDelivery';

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param ChargedCurrency $chargeCurrency
     * @param GiftcardPayment $giftcardPaymentHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ChargedCurrency $chargeCurrency,
        private readonly GiftcardPayment $giftcardPaymentHelper,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();

        /** @var Order $order */
        $order = $payment->getOrder();
        $quote = $this->cartRepository->get($order->getQuoteId());
        $isVirtual = $order->getIsVirtual();

        try {
            if ($isVirtual) {
                $merchantRiskIndicatorFields['deliveryAddressIndicator'] = self::ADDRESS_INDICATOR_OTHER;
                $merchantRiskIndicatorFields['deliveryEmailAddress'] = $order->getCustomerEmail();
                $merchantRiskIndicatorFields['deliveryTimeframe'] = self::DELIVERY_TIMEFRAME_ELECTRONIC_DELIVERY;
            } else {
                $shippingAddress = $quote->getShippingAddress();
                $addressMatch = $shippingAddress->getSameAsBilling();

                $merchantRiskIndicatorFields['addressMatch'] = boolval($addressMatch);
                $merchantRiskIndicatorFields['deliveryAddressIndicator'] = $addressMatch ?
                    self::ADDRESS_INDICATOR_SHIP_TO_BILLING_ADDRESS :
                    self::ADDRESS_INDICATOR_SHIP_TO_NEW_ADDRESS;
            }

            $merchantRiskIndicatorFields['reorderItems'] = !empty($order->getRelationParentId());

            // Build giftcard related risk indicators
            $merchantRiskIndicatorFields = array_merge(
                $merchantRiskIndicatorFields,
                $this->buildGiftcardRiskIndicatorFields($quote)
            );
        } catch (Exception $e) {
            $message = __(
                "An error occurred while building the merchantRiskIndicator field: %1",
                $e->getMessage()
            );
            $this->adyenLogger->error($message);

            $merchantRiskIndicatorFields = [];
        }

        if (!empty($merchantRiskIndicatorFields)) {
            $response = [
                'body' => [
                    'merchantRiskIndicator' => $merchantRiskIndicatorFields,
                ]
            ];
        }

        return $response ?? [];
    }

    /**
     * @param CartInterface $quote
     * @return array
     */
    private function buildGiftcardRiskIndicatorFields(CartInterface $quote): array
    {
        $quoteAmountCurrency = $this->chargeCurrency->getQuoteAmountCurrency($quote);

        $savedGiftcards = json_decode(
            $this->giftcardPaymentHelper->fetchRedeemedGiftcards($quote->getId()),
            true
        );

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() === JSON_ERROR_NONE && !empty($savedGiftcards['redeemedGiftcards'])) {
            $giftcardRiskIndicatorFields['giftCardAmount'] = [
                'currency' => $quoteAmountCurrency->getCurrencyCode(),
                'value' => $this->giftcardPaymentHelper->getQuoteGiftcardDiscount($quote)
            ];
            $giftcardRiskIndicatorFields['giftCardCurr'] = $quoteAmountCurrency->getCurrencyCode();
            $giftcardRiskIndicatorFields['giftCardCount'] = count($savedGiftcards['redeemedGiftcards']);
        }

        return $giftcardRiskIndicatorFields ?? [];
    }
}
