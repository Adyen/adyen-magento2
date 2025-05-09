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

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenDonationsInterface;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Util\Uuid;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class AdyenDonations implements AdyenDonationsInterface
{
    private CommandPoolInterface $commandPool;
    private Json $jsonSerializer;
    protected Data $dataHelper;
    private ChargedCurrency $chargedCurrency;
    private Config $config;
    private PaymentMethods $paymentMethodsHelper;
    private OrderRepository $orderRepository;

    private $donationTryCount;

    public function __construct(
        CommandPoolInterface $commandPool,
        Json $jsonSerializer,
        Data $dataHelper,
        ChargedCurrency $chargedCurrency,
        Config $config,
        PaymentMethods $paymentMethodsHelper,
        OrderRepository $orderRepository
    ) {
        $this->commandPool = $commandPool;
        $this->jsonSerializer = $jsonSerializer;
        $this->dataHelper = $dataHelper;
        $this->chargedCurrency = $chargedCurrency;
        $this->config = $config;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws InputException
     */
    public function donate(int $orderId, string $payload): void
    {
        $order = $this->orderRepository->get($orderId);

        $this->makeDonation($payload, $order);
    }

    public function makeDonation(string $payload, OrderInterface $order): void
    {
        $payload = $this->jsonSerializer->unserialize($payload);

        $paymentMethodInstance = $order->getPayment()->getMethodInstance();
        $donationToken = $order->getPayment()->getAdditionalInformation('donationToken');
        $donationCampaignId = $order->getPayment()->getAdditionalInformation('donationCampaignId');

        if (!$donationToken) {
            throw new LocalizedException(__('Donation failed!'));
        }
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);
        $currencyCode = $orderAmountCurrency->getCurrencyCode();
        if ($payload['amount']['currency'] !== $currencyCode) {
            throw new LocalizedException(__('Donation failed!'));
        }

        $payload['donationToken'] = $donationToken;
        $payload['donationOriginalPspReference'] = $order->getPayment()->getAdditionalInformation('pspReference');
        $payload['donationCampaignId'] = $donationCampaignId;

        // Override payment method object with payment method code
        if ($order->getPayment()->getMethod() === AdyenCcConfigProvider::CODE) {
            $payload['paymentMethod'] = 'scheme';
        } elseif ($this->paymentMethodsHelper->isAlternativePaymentMethod($paymentMethodInstance)) {
            $payload['paymentMethod'] = $this->paymentMethodsHelper->getAlternativePaymentMethodTxVariant(
                $paymentMethodInstance
            );
        } else {
            throw new LocalizedException(__('Donation failed!'));
        }

        $customerId = $order->getCustomerId();
        if ($customerId) {
            $payload['shopperReference'] = $this->dataHelper->padShopperReference($customerId);
        } else {
            $guestCustomerId = $order->getIncrementId() . Uuid::generateV4();
            $payload['shopperReference'] = $guestCustomerId;
        }

        try {
            $donationsCaptureCommand = $this->commandPool->get('capture');
            $donationsCaptureCommand->execute(['payment' => $payload]);

            // Remove donation token & DonationCampaignId after a successful donation.
            $this->removeDonationToken($order);
            $this->removeDonationCampaignId($order);
        }
        catch (LocalizedException $e) {
            $this->donationTryCount = $order->getPayment()->getAdditionalInformation('donationTryCount');

            if ($this->donationTryCount >= 5) {
                // Remove donation token and DonationCampaignId after 5 try and throw a exception.
                $this->removeDonationToken($order);
                $this->removeDonationCampaignId($order);
            }

            $this->incrementTryCount($order);
            throw new LocalizedException(__('Donation failed!'));
        }
    }

    private function incrementTryCount(Order $order): void
    {
        if (!$this->donationTryCount) {
            $order->getPayment()->setAdditionalInformation('donationTryCount', 1);
        }
        else {
            $this->donationTryCount += 1;
            $order->getPayment()->setAdditionalInformation('donationTryCount', $this->donationTryCount);
        }

        $order->save();
    }

    private function removeDonationToken(Order $order): void
    {
        $order->getPayment()->unsAdditionalInformation('donationToken');
        $order->save();
    }

    private function removeDonationCampaignId(Order $order): void
    {
        $order->getPayment()->unsAdditionalInformation('donationCampaignId');
        $order->save();
    }
}
