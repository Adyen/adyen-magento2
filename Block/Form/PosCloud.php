<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Form;

use Adyen\Payment\Helper\ConnectedTerminals;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Block\Form;

class PosCloud extends Form
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/pos_cloud.phtml';

    /**
     * @var ConnectedTerminals
     */
    protected $connectedTerminalsHelper;

    /**
     * @param Template\Context $context
     * @param ConnectedTerminals $connectedTerminalsHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ConnectedTerminals $connectedTerminalsHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->connectedTerminalsHelper = $connectedTerminalsHelper;
    }

    /**
     * @return array|mixed
     * @throws \Adyen\AdyenException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConnectedTerminals()
    {
        $connectedTerminals = $this->connectedTerminalsHelper->getConnectedTerminals();

        if (!empty($connectedTerminals['uniqueTerminalIds'])) {
            return $connectedTerminals['uniqueTerminalIds'];
        }

        return [];
    }
}
