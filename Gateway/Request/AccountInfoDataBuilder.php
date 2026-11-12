<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Model\Checkout\AccountInfo;
use Adyen\Payment\Logger\AdyenLogger;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\DateTime as MagentoDateTime;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;

class AccountInfoDataBuilder implements BuilderInterface
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param CartRepositoryInterface $cartRepository
     * @param MagentoDateTime $dateTime
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly MagentoDateTime $dateTime,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        /** @var Order $order */
        $order = $paymentDataObject->getPayment()->getOrder();
        $customerId = $order->getCustomerId();

        try {
            $accountAgeIndicator = empty($customerId) ?
                AccountInfo::ACCOUNT_AGE_INDICATOR_NOT_APPLICABLE :
                $this->getAccountAgeIndicator((int) $customerId, $order);
        } catch (Exception $e) {
            $message = __(
                "An error occurred while building the accountInfo field: %1",
                $e->getMessage()
            );
            $this->adyenLogger->error($message);

            return [];
        }

        return [
            'body' => [
                'accountInfo' => [
                    'accountAgeIndicator' => $accountAgeIndicator
                ]
            ]
        ];
    }

    /**
     * @param int $customerId
     * @param Order $order
     * @return string
     * @throws Exception
     */
    private function getAccountAgeIndicator(int $customerId, Order $order): string
    {
        $customer = $this->customerRepository->getById($customerId);
        $accountCreatedAt = $this->getUtcTimestamp($customer->getCreatedAt());

        $quote = $this->cartRepository->get($order->getQuoteId());
        $quoteCreatedAt = $quote->getCreatedAt();
        if (!empty($quoteCreatedAt) && $accountCreatedAt >= $this->getUtcTimestamp($quoteCreatedAt)) {
            return AccountInfo::ACCOUNT_AGE_INDICATOR_THIS_TRANSACTION;
        }

        $accountAgeInDays = ($this->dateTime->gmtTimestamp() - $accountCreatedAt) / self::SECONDS_PER_DAY;

        if ($accountAgeInDays < 30) {
            return AccountInfo::ACCOUNT_AGE_INDICATOR_LESS_THAN30_DAYS;
        } elseif ($accountAgeInDays <= 60) {
            return AccountInfo::ACCOUNT_AGE_INDICATOR_FROM30_TO60_DAYS;
        } else {
            return AccountInfo::ACCOUNT_AGE_INDICATOR_MORE_THAN60_DAYS;
        }
    }

    /**
     * @param string|null $date
     * @return int
     * @throws Exception
     */
    private function getUtcTimestamp(?string $date): int
    {
        if (empty($date)) {
            throw new Exception('Date value is empty.');
        }

        return (new DateTime($date, new DateTimeZone('UTC')))->getTimestamp();
    }
}
