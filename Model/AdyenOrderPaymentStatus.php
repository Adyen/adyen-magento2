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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;

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
    )
    {
        $this->orderRepository = $orderRepository;
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * {@inheritDoc}
     * @throws MagentoNoSuchEntityException
     * @throws AdyenException
     * @throws LocalizedException
     */
    public function getOrderPaymentStatus($orderId)
    {
        try {
            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();

            if ($payment->getMethod() === AdyenCcConfigProvider::CODE) {
                $additionalInformation = $payment->getAdditionalInformation();
                return $this->adyenHelper->buildThreeDS2ProcessResponseJson(
                    $additionalInformation['threeDSType'],
                    $additionalInformation['threeDS2Token']
                );
            }
        } catch (NoSuchEntityException $e) {
            $this->adyenLogger->error("Exception: " . $e->getMessage());
            throw new LocalizedException(__('This order no longer exists.'));
        }

        $this->adyenLogger->error("Problem in method getOrderPaymentStatus. Payment method is {$payment->getMethod()}");
        throw new LocalizedException(__('An unexpected error occurred.'));
    }
}
