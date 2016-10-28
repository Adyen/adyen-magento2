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

class Sepa extends \Magento\Payment\Block\Form
{

    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/sepa.phtml';

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * Sepa constructor.
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
     * @return mixed
     */
    public function getCountries()
    {
        return $this->_adyenHelper->getSepaCountries();
    }
}