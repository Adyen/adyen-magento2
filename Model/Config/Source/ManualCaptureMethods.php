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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

declare(strict_types=1);

namespace Adyen\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ManualCaptureMethods implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'cup',               'label' => 'China Union Pay'],
            ['value' => 'cartebancaire',     'label' => 'Carte Bancaire'],
            ['value' => 'visa',              'label' => 'VISA'],
            ['value' => 'visadankort',       'label' => 'VISA Dankort'],
            ['value' => 'mc',                'label' => 'Mastercard'],
            ['value' => 'uatp',              'label' => 'Universal Air Travel Plan'],
            ['value' => 'amex',              'label' => 'American Express'],
            ['value' => 'maestro',           'label' => 'Maestro'],
            ['value' => 'maestrouk',         'label' => 'Maestro UK'],
            ['value' => 'diners',            'label' => 'Diners Club'],
            ['value' => 'discover',          'label' => 'Discover'],
            ['value' => 'jcb',               'label' => 'JCB'],
            ['value' => 'laser',             'label' => 'Laser'],
            ['value' => 'paypal',            'label' => 'PayPal'],
            ['value' => 'sepadirectdebit',   'label' => 'SEPA Direct Debit'],
            ['value' => 'dankort',           'label' => 'Dankort'],
            ['value' => 'elo',               'label' => 'Elo'],
            ['value' => 'hipercard',         'label' => 'Hipercard'],
            ['value' => 'mc_applepay',       'label' => 'Mastercard Apple Pay'],
            ['value' => 'visa_applepay',     'label' => 'VISA Apple Pay'],
            ['value' => 'amex_applepay',     'label' => 'American Express Apple Pay'],
            ['value' => 'discover_applepay', 'label' => 'Discover Apple Pay'],
            ['value' => 'maestro_applepay',  'label' => 'Maestro Apple Pay'],
            ['value' => 'paywithgoogle',     'label' => 'Google Pay'],
        ];
    }
}
