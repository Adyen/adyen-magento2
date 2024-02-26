<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

/**
 * Payment Config Converter
 */

namespace Adyen\Payment\Model\Config;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Magento\Framework\Config\ConverterInterface;

class Converter implements ConverterInterface
{
    /**
     * @param DOMDocument $source
     * @return array
     */
    public function convert($source)
    {
        $xpath = new DOMXPath($source);
        return [
            'adyen_credit_cards' => $this->convertCreditCards($xpath),
        ];
    }

    /**
     * Convert credit cards xml tree to array
     *
     * @param DOMXPath $xpath
     * @return array
     */
    protected function convertCreditCards(DOMXPath $xpath)
    {
        $config = [];
        /** @var DOMNode $type */
        foreach ($xpath->query('/payment/adyen_credit_cards/type') as $type) {
            $typeAttributes = $type->attributes;
            $ccId = $typeAttributes->getNamedItem('txVariant')->nodeValue;
            $config[$ccId] = $type->nodeValue;
        }

        return $config;
    }
}
