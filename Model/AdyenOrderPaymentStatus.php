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

            if ($additionalInformation['resultCode'] === 'RedirectShopper') {
                if (!empty($additionalInformation['3dSuccess'])) {
                    return json_encode([
                        'threeDS2' => false,
                        'type' => '3dSuccess'
                    ]);
                }
                return json_encode([
                    'threeDS2' => false,
                    'type' => $type,
                    'action' => [
                        'type' => 'redirect',
                        'method' => $additionalInformation['redirectMethod'],
                        'url' => $additionalInformation['redirectUrl'],
                        'paymentData' => $additionalInformation['paymentData'],
                        'paymentMethodType' => 'scheme',
                        'data' => [
                            'MD' => $additionalInformation['md'],
                            'PaReq' => $additionalInformation['paRequest'],
                            'TermUrl' => $additionalInformation['termUrl'],
                        ]
                    ],
                    'details' => [
                        ["key" => "MD", "type"  => "text"],
                        ["key" => "PaRes", "type" => "text"]
                    ],
                ]);
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
            } else if ($additionalInformation['resultCode'] === 'RedirectShopper') {
                return json_encode([
                    'action' => [
                        'type' => 'redirect',
                        'method' => $additionalInformation['redirectMethod'],
                        'url' => $additionalInformation['redirectUrl'],
                        'paymentData' => $additionalInformation['paymentData'],
                        'paymentMethodType' => $additionalInformation['brand_code']
                    ],
                    'details' => $additionalInformation['details']
                ]);
            }
        }

        return true;
    }
}
