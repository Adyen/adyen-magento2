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
namespace Adyen\Payment\Observer;

use Magento\Framework\DataObject;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

abstract class AdyenAbstractDataAssignObserver extends AbstractDataAssignObserver
{
    const BRAND_CODE = 'brand_code';
    const STATE_DATA = 'state_data';
    const BROWSER_INFO = 'browserInfo';
    const PAYMENT_METHOD = 'paymentMethod';
    const RISK_DATA = 'riskData';
    const STORE_PAYMENT_METHOD = 'storePaymentMethod';
    const CC_TYPE = 'cc_type';
    const NUMBER_OF_INSTALLMENTS = 'number_of_installments';
    const COMBO_CARD_TYPE = 'combo_card_type';
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    public $adyenHelper;

    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper

    ) {
        $this->adyenHelper = $adyenHelper;
    }
    /**
     * @param DataObject $data
     * @return array
     */
    protected function getValidatedAdditionalData(DataObject $data)
    {
        // Get additional data array
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return [];
        }

        $this->adyenHelper->adyenLogger->addAdyenDebug("----Data" . $additionalData);
        // Get a validated additional data array
        $additionalData = $this->getArrayOnlyWithApprovedKeys($additionalData, $this->approvedAdditionalDataKeys);

        // json decode state data
        $stateData = [];
        if (!empty($additionalData[self::STATE_DATA])) {
            $stateData = json_decode($additionalData[self::STATE_DATA], true);
        }

        // Get validated state data array
        if (!empty($stateData)) {
            $stateData = $this->getArrayOnlyWithApprovedKeys($stateData, $this->approvedStateDataKeys);
        }

        // Replace state data with the decoded and validated state data
        $additionalData[self::STATE_DATA] = $stateData;

        return $additionalData;
    }

    /**
     * Returns an array with only the approved keys
     *
     * @param array $array
     * @param array $approvedKeys
     * @return array
     */
    private function getArrayOnlyWithApprovedKeys($array, $approvedKeys)
    {
        $result = [];

        foreach ($approvedKeys as $approvedKey) {
            if (isset($array[$approvedKey])) {
                $result[$approvedKey] = $array[$approvedKey];
            }
        }

        return $result;
    }
}
