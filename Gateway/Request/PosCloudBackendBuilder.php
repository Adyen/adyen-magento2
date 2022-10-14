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

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PointOfSale;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PosCloudBackendBuilder implements BuilderInterface
{
    /**
     * @var PointOfSale
     */
    private $pointOfSale;

    /** @var ChargedCurrency */
    private $chargedCurrency;

    /**
     * @param PointOfSale $pointOfSale
     */
    public function __construct(
        PointOfSale $pointOfSale,
        ChargedCurrency $chargedCurrency
    ) {
        $this->pointOfSale = $pointOfSale;
        $this->chargedCurrency = $chargedCurrency;
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
        $numberOfInstallments = $payment->getAdditionalInformation('number_of_installments');
        $fundingSource = $payment->getAdditionalInformation('funding_source');

        $adyenAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($orderInstance);

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
                            'Currency' => $adyenAmountCurrency->getCurrencyCode(),
                            'RequestedAmount' => doubleval($adyenAmountCurrency->getAmount())
                        ]
                    ]
                ]
            ]
        ];

        if (is_null($fundingSource) || $fundingSource === PaymentMethods::FUNDING_SOURCE_CREDIT) {
            if (isset($numberOfInstallments)) {
                $paymentServiceRequest['SaleToPOIRequest']['PaymentRequest']['PaymentData'] = [
                    "PaymentType" => "Instalment",
                    "Instalment" => [
                        "InstalmentType" => "EqualInstalments",
                        "SequenceNumber" => 1,
                        "Period" => 1,
                        "PeriodUnit" => "Monthly",
                        "TotalNbOfPayments" => intval($numberOfInstallments)
                    ]
                ];
            } else {
                $paymentServiceRequest['SaleToPOIRequest']['PaymentData'] = [
                    'PaymentType' => $transactionType,
                ];
            }

            $paymentServiceRequest['SaleToPOIRequest']['PaymentRequest']['PaymentTransaction']['TransactionConditions'] = [
                "DebitPreferredFlag" => false
            ];
        } elseif ($fundingSource === PaymentMethods::FUNDING_SOURCE_DEBIT) {
            $paymentServiceRequest['SaleToPOIRequest']['PaymentRequest']['PaymentTransaction']['TransactionConditions'] = [
                "DebitPreferredFlag" => true
            ];

            $paymentServiceRequest['SaleToPOIRequest']['PaymentData'] = [
                'PaymentType' => $transactionType,
            ];
        }

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
