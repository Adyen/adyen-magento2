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
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\AdyenOrderPaymentDetailsInterface;

class AdyenOrderPaymentDetails implements AdyenOrderPaymentDetailsInterface
{

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * AdyenThreeDS2Process constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->orderFactory = $orderFactory;
        $this->adyenLogger = $adyenLogger;
    }

    public function getDetails($orderId, $payload)
    {
        // Decode payload from frontend
        $payload = json_decode($payload, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment details failed because the request was not a valid JSON')
            );
        }

        // Create order by order id
        $order = $this->orderFactory->create()->load($orderId);
        $payment = $order->getPayment();
        if (empty($payment) || !$this->isAdyen($payment->getMethod())) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment details are not available for this order')
            );
        }

        $additionalInformation = $payment->getAdditionalInformation();
        if (!empty($additionalInformation['paymentData']) && empty($payload['paymentData'])) {
            $payload['paymentData'] = $additionalInformation['paymentData'];
        }
        // ToDo check that Order belongs to the customer or can be queried

        try {
            $client = $this->adyenHelper->initializeAdyenClient($order->getStoreId());
            $service = $this->adyenHelper->createAdyenCheckoutService($client);
            $result = $service->paymentsDetails($payload);
            return json_encode($result);
        } catch (\Adyen\AdyenException $e) {
            $this->adyenLogger->error("Payment details failed" . $e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__('Payment details failed'));
        }
    }

    private function isAdyen($method)
    {
        return strpos($method, 'adyen') !== false;
    }
}
