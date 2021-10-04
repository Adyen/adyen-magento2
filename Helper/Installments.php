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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Magento\Framework\Serialize\SerializerInterface;

class Installments
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        SerializerInterface $serializer
    ) {
        $this->serializer = $serializer;
    }

    /**
     * @param string $installmentsConfig
     * @param array $ccAvailableTypesByAlt
     * @param float $quoteAmount
     * @return string
     * @see \Adyen\Payment\Model\Config\Backend\Installments::beforeSave()
     *
     * Converts the array stored by the Installments backend model into the format required by the generic component
     */
    public function formatInstallmentsConfig($installmentsConfig, $ccAvailableTypesByAlt, $quoteAmount)
    {
        $installmentsArray = $this->serializer->unserialize($installmentsConfig);
        if (empty($installmentsArray)) {
            return '{}';
        }

        $formattedConfig = $values = array();
        foreach ($installmentsArray as $card => $cardInstallments) {
            $values[] = 1; // Always allow payment in full amount if installments are available

            foreach ($cardInstallments as $minimumAmount => $installment) {
                if ($quoteAmount >= $minimumAmount) {
                    $values[] = (int)$installment;
                }
            }

            $formattedConfig[$ccAvailableTypesByAlt[$card]['code_alt']] = [
                'values' => array_unique($values)
            ];

            $values = array();
        }
        return json_encode($formattedConfig);
    }
}
