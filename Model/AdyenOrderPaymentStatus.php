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
 * Adyen Payment Module
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenGooglePayConfigProvider;
use Adyen\Payment\Model\Ui\AdyenHppConfigProvider;
use Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider;

class AdyenOrderPaymentStatus implements \Adyen\Payment\Api\AdyenOrderPaymentStatusInterface
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * AdyenOrderPaymentStatus constructor.
     *
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->orderRepository = $orderRepository;
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @param string $orderId
     * @return bool|string
     */
    public function getOrderPaymentStatus($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        $payment = $order->getPayment();

        if ($payment->getMethod() === AdyenCcConfigProvider::CODE ||
            $payment->getMethod() === AdyenOneclickConfigProvider::CODE
        ) {
            $additionalInformation = $payment->getAdditionalInformation();

            $type = null;
            if (!empty($additionalInformation['threeDSType'])) {
                $type = $additionalInformation['threeDSType'];
            }

            $token = null;
            if (!empty($additionalInformation['threeDS2Token'])) {
                $token = $additionalInformation['threeDS2Token'];
            }

            return $this->adyenHelper->buildThreeDS2ProcessResponseJson($type, $token);
        }


        /**
         * If payment method result is Pending and action is provided provide component action back to checkout
         */
        if ($payment->getMethod() === AdyenHppConfigProvider::CODE) {
            $additionalInformation = $payment->getAdditionalInformation();
            if (
                !empty($additionalInformation['action']) &&
                $additionalInformation['resultCode'] == 'Pending'
            ) {
                return json_encode(['action' => $additionalInformation['action']]);
            }
        }
        /**
         * If payment method is google pay
         */
        if ($payment->getMethod() === AdyenGooglePayConfigProvider::CODE) {
            $additionalInformation = $payment->getAdditionalInformation();
            $type = null;
            if (!empty($additionalInformation['threeDSType'])) {
                $type = $additionalInformation['threeDSType'];
            }
            $token = null;
            if (!empty($additionalInformation['token'])) {
                $token = $additionalInformation['token'];
            }

            return $this->adyenHelper->buildThreeDS2ProcessResponseJson($type, $token);
        }

        return true;
    }
}
