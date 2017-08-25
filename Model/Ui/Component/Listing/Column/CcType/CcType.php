<?php

namespace Adyen\Payment\Model\Ui\Component\Listing\Column\CcType;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

/**
 * Class Options
 */
class CcType implements OptionSourceInterface
{
    
    public function toOptionArray() {
        return [
            [
                'label' => 'Visa',
                'value' => 'VI'
            ],
            [
                'label' => 'Mastercard',
                'value' => 'MC'
            ],
            [
                'label' => 'American Express',
                'value' => 'AE'
            ],
            [
                'label' => 'iDEAL',
                'value' => 'ideal'
            ],
            [
                'label' => 'Int. Bank Transfer',
                'value' => 'bankTransfer_IBAN'
            ],
            [
                'label' => 'ELV',
                'value' => 'elv'
            ],
            [
                'label' => 'PayPal',
                'value' => 'paypal'
            ],
            [
                'label' => 'QIWI Wallet',
                'value' => 'qiwiwallet'
            ],
            [
                'label' => 'Maestro UK',
                'value' => 'maestrouk'
            ],
            [
                'label' => 'Sofort',
                'value' => 'directEbanking'
            ],
            [
                'label' => 'Boleto',
                'value' => 'boleto'
            ],
            [
                'label' => 'SEPA',
                'value' => 'sepa'
            ],
        ];
    }

}