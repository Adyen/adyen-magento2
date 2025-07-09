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

namespace Adyen\Payment\Gateway\Request;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Requests;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;

class DonationDataBuilder implements BuilderInterface
{
    /**
     * @var Requests
     */
    private $adyenRequestsHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Requests $adyenRequestsHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Requests $adyenRequestsHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->adyenRequestsHelper = $adyenRequestsHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     * @throws AdyenException
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        $payment = $paymentDataObject->getPayment();
        return [
            'body' => $this->adyenRequestsHelper->buildDonationData(
                    $payment,
                    $this->storeManager->getStore()->getId()
                )
        ];
    }
}
