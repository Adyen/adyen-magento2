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

use Adyen\Payment\Helper\BaseUrlHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class ReturnUrlDataBuilder implements BuilderInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var BaseUrlHelper
     */
    private $baseUrlHelper;

    /**
     * CheckoutDataBuilder constructor.
     *
     * @param BaseUrlHelper $baseUrlHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        BaseUrlHelper $baseUrlHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->baseUrlHelper = $baseUrlHelper;
        $this->storeManager = $storeManager;
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

        $returnUrl = rtrim(
                $this->baseUrlHelper->getStoreBaseUrl($this->storeManager->getStore()->getId()), '/'
            ) . '/adyen/process/result?merchantReference=' . $order->getIncrementId();

        $requestBody['body']['returnUrl'] = $returnUrl;

        return $requestBody;
    }
}
