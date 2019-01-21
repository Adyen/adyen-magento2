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
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use \Magento\Payment\Gateway\Helper\SubjectReader;

class VaultDataBuilder implements BuilderInterface
{

    /**
     * Recurring variable
     * @var string
     */
    private static $enableRecurring = 'enableRecurring';

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        // vault is enabled and shopper provided consent to store card this logic is triggered
        $request = [];
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $payment = $paymentDO->getPayment();
        $data = $payment->getAdditionalInformation();

        if (!empty($data[VaultConfigProvider::IS_ACTIVE_CODE]) &&
            $data[VaultConfigProvider::IS_ACTIVE_CODE] === true
        ) {
            // store it only as oneclick otherwise we store oneclick tokens (maestro+bcmc) that will fail
            $request[self::$enableRecurring] = true;
        } else {
            // explicity turn this off as merchants have recurring on by default
            $request[self::$enableRecurring] = false;
        }
        return $request;
    }
}