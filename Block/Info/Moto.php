<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Info;

use Adyen\AdyenException;
use Magento\Framework\View\Element\Template;

class Moto extends AbstractInfo
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::info/adyen_moto.phtml';

    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory $adyenOrderPaymentCollectionFactory,
        Template\Context $context,
        \Adyen\Payment\Helper\Config $adyenConfigHelper,
        array $data = []
    ) {
        parent::__construct($adyenConfigHelper, $adyenHelper, $adyenOrderPaymentCollectionFactory, $context, $data);
    }

    /**
     * Return credit card type
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCcTypeName()
    {
        $types = $this->_adyenHelper->getAdyenCcTypes();
        $ccType = $this->getInfo()->getCcType();

        if (isset($types[$ccType])) {
            return $types[$ccType]['name'];
        }
        else {
            return __('Unknown');
        }
    }

    public function getPspReferenceBlock()
    {
        $storeId = $this->getInfo()->getOrder()->getStoreId();
        $motoMerchantAccount = $this->getInfo()->getAdditionalInformation('motoMerchantAccount');
        $pspReference = $this->getAdyenPspReference();

        if (!empty($pspReference) && !empty($motoMerchantAccount)) {
            try {
                $motoMerchantAccountProperties = $this->_configHelper->getMotoMerchantAccountProperties($motoMerchantAccount, $storeId);

                if ($this->_configHelper->isMotoDemoMode($motoMerchantAccountProperties)) {
                    $url = 'https://ca-test.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=';
                }
                else {
                    $url = 'https://ca-live.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=';
                }

                $url .= $pspReference . '&txType=Payment';
                $html = "<a href='$url' target='_blank'>$pspReference</a>";
            }
            catch (AdyenException $e) {
                $html = "<span>$pspReference</span>";
            }
        }
        else {
            $html = null;
        }

        return $html;
    }
}
