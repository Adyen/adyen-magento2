<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source;

use Adyen\Payment\Helper\Data;
use Magento\Payment\Model\Config;

/**
 * @codeCoverageIgnore
 */
class CcType extends \Magento\Payment\Model\Source\Cctype
{
    const ALLOWED_TYPES = ['VI', 'MC', 'AE', 'DI', 'JCB', 'UN', 'MI', 'DN', 'BCMC', 'HIPERCARD', 'ELO', 'TROY', 'DANKORT', 'CB', 'KCP'];

    /**
     * @var Data
     */
    private $_adyenHelper;

    /**
     * CcType constructor.
     *
     * @param Config $paymentConfig
     * @param Data $adyenHelper
     */
    public function __construct(
        Config $paymentConfig,
        Data $adyenHelper
    ) {
        parent::__construct($paymentConfig);
        $this->_adyenHelper = $adyenHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        /**
         * making filter by allowed cards
         */
        $allowed = self::ALLOWED_TYPES;
        $options = [];

        foreach ($this->_adyenHelper->getAdyenCcTypes() as $code => $name) {
            if (in_array($code, $allowed) || !count($allowed)) {
                $options[] = ['value' => $code, 'label' => $name['name']];
            }
        }

        return $options;
    }
}
