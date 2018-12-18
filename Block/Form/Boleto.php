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

namespace Adyen\Payment\Block\Form;

class Boleto extends \Magento\Payment\Block\Form
{

    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/boleto.phtml';

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * Boleto constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Adyen\Payment\Helper\Data $adyenHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_adyenHelper = $adyenHelper;
    }

    /**
     * @return array
     */
    public function getBoletoTypes()
    {
        $boletoTypes = $this->_adyenHelper->getBoletoTypes();
        $types = [];
        foreach ($boletoTypes as $boletoType) {
            $types[$boletoType['value']] = $boletoType['label'];
        }
        return $types;
    }
}
