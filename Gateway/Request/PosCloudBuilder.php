<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen B.V.
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

    /**
     * In case of older implementation (using AdyenInitiateTerminalApi::initiate) initiate call was already done so we pass its result.
     * Otherwise, we will do the initiate call here, using initiatePosPayment() so we pass parameters required for the initiate call
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();

        $request['body'] = $this->buildPosRequest(
            $payment->getAdditionalInformation('terminal_id'),
            $payment->getAdditionalInformation('funding_source'),
            $order,
            $payment->getAdditionalInformation('number_of_installments'),
        );

        return $request;
    }


    /**
     * Build request required for the /sync call
     *
     * @param string $terminalId
     * @param string $fundingSource
     * @param Order $order
     * @param string|null $numberOfInstallments
     * @return array
     * @throws LocalizedException
     */
    private function buildPosRequest(
        string $terminalId,
        string $fundingSource,
        Order $order,
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

        if ($fundingSource === PaymentMethods::FUNDING_SOURCE_CREDIT) {
            if (isset($numberOfInstallments)) {
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
        } elseif ($fundingSource === PaymentMethods::FUNDING_SOURCE_DEBIT) {
            $request['SaleToPOIRequest']['PaymentRequest']['PaymentTransaction']['TransactionConditions'] = [
                "DebitPreferredFlag" => true
            ];

            $request['SaleToPOIRequest']['PaymentData'] = [
                'PaymentType' => $transactionType,
            ];
        }

        return $this->pointOfSale->addSaleToAcquirerData($request, $order);
    }
}
