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

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

class Cctypes extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * All possible credit card types
     *
     * @var array
     */
    protected $ccTypes = [];

    /**
     * @var \Adyen\Payment\Model\Config\Source\CcType
     */
    protected $ccTypeSource;

    /**
     * Cctypes constructor.
     *
     * @param \Magento\Framework\View\Element\Context $context
     * @param \Adyen\Payment\Model\Config\Source\CcType $ccTypeSource
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Adyen\Payment\Model\Config\Source\CcType $ccTypeSource,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->ccTypeSource = $ccTypeSource;
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            foreach ($this->_getCcTypes() as $country) {
                if (isset($country['value']) && $country['value'] && isset($country['label']) && $country['label']) {
                    $this->addOption($country['value'], $country['label']);
                }
            }
        }
        $this->setClass('cc-type-select');
        $this->setExtraParams('multiple="multiple"');
        return parent::_toHtml();
    }

    /**
     * All possible credit card types
     *
     * @return array
     */
    protected function _getCcTypes()
    {
        if (!$this->ccTypes) {
            $this->ccTypes = $this->ccTypeSource->toOptionArray();
        }
        return $this->ccTypes;
    }

    /**
     * Sets name for input element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value . '[]');
    }
}
