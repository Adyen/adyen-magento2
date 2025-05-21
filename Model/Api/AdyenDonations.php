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
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\Sales\OrderRepository;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Sales\Model\Order;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;

class AdyenDonations implements AdyenDonationsInterface
{
    private CommandPoolInterface $commandPool;
    private Json $jsonSerializer;
    protected Data $dataHelper;
    private OrderRepository $orderRepository;
    private PaymentDataObjectFactoryInterface $paymentDataObjectFactory;

    private $donationTryCount;

    public function __construct(
        CommandPoolInterface $commandPool,
        Json $jsonSerializer,
        Data $dataHelper,
        OrderRepository $orderRepository,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
    ) {
        $this->commandPool = $commandPool;
        $this->jsonSerializer = $jsonSerializer;
        $this->dataHelper = $dataHelper;
        $this->orderRepository = $orderRepository;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
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

    /**
     * @throws LocalizedException
     */
    public function makeDonation(string $payload, Order $order): void
    {
        try {
            $payload = $this->jsonSerializer->unserialize($payload);
            $payment = $order->getPayment();
            $payment->setAdditionalInformation('donationPayload', $payload);
            $paymentDO = $this->paymentDataObjectFactory->create($payment);

            $donationsCaptureCommand = $this->commandPool->get('capture');
            $donationsCaptureCommand->execute(['payment' => $paymentDO]);

            $this->removeDonationToken($order);
            $this->removeDonationCampaignId($order);
        } catch (LocalizedException $e) {
            $this->donationTryCount = $order->getPayment()->getAdditionalInformation('donationTryCount');

            if ($this->donationTryCount >= 5) {
                $this->removeDonationToken($order);
                $this->removeDonationCampaignId($order);
            }

            $this->incrementTryCount($order);
            throw new LocalizedException(__('Donation failed!'));
        }
    }

    private function incrementTryCount(Order $order): void
    {
        $payment = $order->getPayment();

        if (!$this->donationTryCount) {
            $payment->setAdditionalInformation('donationTryCount', 1);
        }
        else {
            $this->donationTryCount += 1;
            $payment->setAdditionalInformation('donationTryCount', $this->donationTryCount);
        }

        $this->orderRepository->save($order);
    }

    private function removeDonationToken(Order $order): void
    {
        $payment = $order->getPayment();
        $payment->unsAdditionalInformation('donationToken');
        $this->orderRepository->save($order);
    }

    private function removeDonationCampaignId(Order $order): void
    {
        $order->getPayment()->unsAdditionalInformation('donationCampaignId');
        $order->save();
    }
}