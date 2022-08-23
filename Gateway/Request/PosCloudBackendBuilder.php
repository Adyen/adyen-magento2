<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\PointOfSale;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PosCloudBackendBuilder implements BuilderInterface
{
    /**
     * @var PointOfSale
     */
    private PointOfSale $pointOfSale;

    /**
     * @param PointOfSale $pointOfSale
     */
    public function __construct(
        PointOfSale $pointOfSale
    ) {
        $this->pointOfSale = $pointOfSale;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function build(array $buildSubject)
    {
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        $payment = $paymentDataObject->getPayment();
        $orderInstance = $payment->getMethodInstance()->getInfoInstance()->getOrder();

        $terminalId = $payment->getAdditionalInformation('terminal_id');

        $currency = $paymentDataObject->getOrder()->getCurrencyCode();
        $amount = $paymentDataObject->getOrder()->getGrandTotalAmount();
        $reference = $paymentDataObject->getOrder()->getOrderIncrementId();

        $transactionType = \Adyen\TransactionType::NORMAL;
        $serviceId = date("dHis");
        $initiateDate = date("U");
        $timeStamper = date("Y-m-d") . "T" . date("H:i:s+00:00");

        $paymentServiceRequest = [
            'SaleToPOIRequest' => [
                'MessageHeader' => [
                    'MessageType' => 'Request',
                    'MessageClass' => 'Service',
                    'MessageCategory' => 'Payment',
                    'SaleID' => 'Magento2Cloud',
                    'POIID' => $terminalId,
                    'ProtocolVersion' => '3.0',
                    'ServiceID' => $serviceId
                ],
                'PaymentRequest' => [
                    'SaleData' => [
                        'TokenRequestedType' => 'Customer',
                        'SaleTransactionID' => [
                            'TransactionID' => $reference,
                            'TimeStamp' => $timeStamper
                        ]
                    ],
                    'PaymentTransaction' => [
                        'AmountsReq' => [
                            'Currency' => $currency,
                            'RequestedAmount' => $amount
                        ]
                    ]
                ],
                'PaymentData' => [
                    'PaymentType' => $transactionType
                ]
            ]
        ];

        $paymentServiceRequest = $this->pointOfSale->addSaleToAcquirerData($paymentServiceRequest, null, $orderInstance);

        $transactionStatusRequest = [
            'SaleToPOIRequest' => [
                'MessageHeader' => [
                    'ProtocolVersion' => '3.0',
                    'MessageClass' => 'Service',
                    'MessageCategory' => 'TransactionStatus',
                    'MessageType' => 'Request',
                    'SaleID' => 'Magento2CloudStatus',
                    'POIID' => $terminalId
                ],
                'TransactionStatusRequest' => [
                    'MessageReference' => [
                        'MessageCategory' => 'Payment',
                        'SaleID' => 'Magento2Cloud',
                        'ServiceID' => $serviceId
                    ],
                    'DocumentQualifier' => [
                        "CashierReceipt",
                        "CustomerReceipt"
                    ],
                    'ReceiptReprintFlag' => true
                ]
            ]
        ];

        $payment->setMethod(AdyenPosCloudConfigProvider::CODE);
        $payment->setAdditionalInformation('serviceID', $serviceId);
        $payment->setAdditionalInformation('initiateDate', $initiateDate);

        $request['body'] = [
            'paymentServiceRequest' => $paymentServiceRequest,
            'transactionStatusRequest' => $transactionStatusRequest,
            'initiateDate' => $initiateDate
        ];

        return $request;
    }
}
