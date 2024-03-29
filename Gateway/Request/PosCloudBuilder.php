<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PointOfSale;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class PosCloudBuilder implements BuilderInterface
{
    private ChargedCurrency $chargedCurrency;
    private PointOfSale $pointOfSale;

    public function __construct(ChargedCurrency $chargedCurrency, PointOfSale $pointOfSale)
    {
        $this->chargedCurrency = $chargedCurrency;
        $this->pointOfSale = $pointOfSale;
    }

    public function build(array $buildSubject): array
    {
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();

        $request['body'] = $this->buildPosRequest(
            $order,
            $payment->getAdditionalInformation('terminal_id'),
            $payment->getAdditionalInformation('funding_source'),
            $payment->getAdditionalInformation('number_of_installments'),
        );

        return $request;
    }

    private function buildPosRequest(
        Order $order,
        string $terminalId,
        ?string $fundingSource,
        ?string $numberOfInstallments
    ): array {
        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LocalizedException(
                __('Terminal API initiate request was not a valid JSON')
            );
        }

        $poiId = $terminalId;
        $transactionType = \Adyen\TransactionType::NORMAL;
        $amountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order);

        $serviceID = date("dHis");
        $timeStamper = date("Y-m-d") . "T" . date("H:i:s+00:00");

        $request = [
            'SaleToPOIRequest' =>
                [
                    'MessageHeader' =>
                        [
                            'MessageType' => 'Request',
                            'MessageClass' => 'Service',
                            'MessageCategory' => 'Payment',
                            'SaleID' => 'Magento2Cloud',
                            'POIID' => $poiId,
                            'ProtocolVersion' => '3.0',
                            'ServiceID' => $serviceID
                        ],
                    'PaymentRequest' =>
                        [
                            'SaleData' =>
                                [
                                    'TokenRequestedType' => 'Customer',
                                    'SaleTransactionID' =>
                                        [
                                            'TransactionID' => $order->getIncrementId(),
                                            'TimeStamp' => $timeStamper
                                        ]
                                ],
                            'PaymentTransaction' =>
                                [
                                    'AmountsReq' =>
                                        [
                                            'Currency' => $amountCurrency->getCurrencyCode(),
                                            'RequestedAmount' => doubleval($amountCurrency->getAmount())
                                        ]
                                ]
                        ]
                ]
        ];

        if ($fundingSource === PaymentMethods::FUNDING_SOURCE_DEBIT) {
            $request['SaleToPOIRequest']['PaymentRequest']['PaymentTransaction']['TransactionConditions'] = [
                "DebitPreferredFlag" => true
            ];

            $request['SaleToPOIRequest']['PaymentData'] = [
                'PaymentType' => $transactionType,
            ];
        } else {
            if (isset($numberOfInstallments) && !empty($numberOfInstallments)) {
                $request['SaleToPOIRequest']['PaymentRequest']['PaymentData'] = [
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
                $request['SaleToPOIRequest']['PaymentData'] = [
                    'PaymentType' => $transactionType,
                ];
            }

            $request['SaleToPOIRequest']['PaymentRequest']['PaymentTransaction']['TransactionConditions'] = [
                "DebitPreferredFlag" => false
            ];
        }

        return $this->pointOfSale->addSaleToAcquirerData($request, $order);
    }
}
