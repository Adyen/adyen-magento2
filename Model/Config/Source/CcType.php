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

namespace Adyen\Payment\Model\Config\Source;

/**
 * @codeCoverageIgnore
 */
class CcType extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $_adyenHelper;

    /**
     * CcType constructor.
     *
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Payment\Model\Config $paymentConfig,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        parent::__construct($paymentConfig);
        $this->_adyenHelper = $adyenHelper;
    }

    /**
     * Allowed credit card types
     *
     * @return string[]
     */
    public function getAllowedTypes()
    {
        return ['VI', 'MC', 'AE', 'DI', 'JCB', 'UN', 'MI', 'DN', 'BCMC', 'HIPERCARD', 'ELO', 'TROY', 'DANKORT'];
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        /**
         * making filter by allowed cards
         */
        $allowed = $this->getAllowedTypes();
        $options = [];

        foreach ($this->_adyenHelper->getAdyenCcTypes() as $code => $name) {
            if (in_array($code, $allowed) || !count($allowed)) {
                $options[] = ['value' => $code, 'label' => $name['name']];
            }
        }

        return $options;
    }
}
