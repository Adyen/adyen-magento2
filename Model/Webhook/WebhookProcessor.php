<?php

namespace Adyen\Payment\Model\Webhook;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;

class WebhookProcessor
{
    public function __construct(
        private AcceptorFactory $acceptorFactory,
        private SerializerInterface $serializer,
        private AdyenLogger $adyenLogger
    ) {}

    /**
     * @param string $rawBody
     * @return string
     * @throws LocalizedException
     */
    public function process(string $rawBody): string
    {
        try {
            $data = $this->serializer->unserialize($rawBody);

            if (!is_array($data)) {
                throw new LocalizedException(__('Invalid webhook payload format.'));
            }

            $acceptor = $this->acceptorFactory->createFromPayload($data);
            return $acceptor->handle($data);
        } catch (\Throwable $e) {
            $this->adyenLogger->addAdyenNotification("Webhook processing error: " . $e->getMessage());
            throw new LocalizedException(__('Webhook processing failed: %1', $e->getMessage()));
        }
    }
}
