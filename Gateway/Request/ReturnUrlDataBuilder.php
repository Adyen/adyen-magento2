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
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\Data;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class ReturnUrlDataBuilder implements BuilderInterface
{
    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * CheckoutDataBuilder constructor.
     *
     * @param Data $adyenHelper
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $url
     */
    public function __construct(
        Data $adyenHelper,
        StoreManagerInterface $storeManager,
        UrlInterface $url
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
        $this->url = $url;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        /** @var Order $order */
        $order = $payment->getOrder();

        $pwaOrigin = $this->adyenHelper->getAdyenAbstractConfigData(
            "payment_origin_url",
            $this->storeManager->getStore()->getId()
        );

        if ($pwaOrigin) {
            $returnUrl = rtrim($pwaOrigin, '/') . '/adyen/process/result?merchantReference=' . $order->getIncrementId();
        } else {
            $this->url->setQueryParam('merchantReference', $order->getIncrementId());
            $returnUrl = $this->url->getUrl("adyen/process/result");
        }

        $requestBody['body']['returnUrl'] = $returnUrl;

        return $requestBody;
    }
}
