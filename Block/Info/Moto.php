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

    /**
     * @var \Adyen\Payment\Helper\Config
     */
    private $adyenConfigHelper;

    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory $adyenOrderPaymentCollectionFactory,
        Template\Context $context,
        \Adyen\Payment\Helper\Config $adyenConfigHelper,
        array $data = []
    ) {
        parent::__construct($adyenHelper, $adyenOrderPaymentCollectionFactory, $context, $data);
        $this->adyenConfigHelper = $adyenConfigHelper;
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
        // TODO::Refactor this block after tokenization of the alternative payment methods.
        // This elseif block should be removed after the tokenization of the alternative payment methods (In progress: PW-6764). More general approach is required.
        // Also remove `sepadirectdebit` from translation files.
        elseif ($ccType == 'sepadirectdebit') {
            return __('sepadirectdebit');
        }
        else {
            return __('Unknown');
        }
    }

    /**
     *
     * Return related MOTO merchant account of the order
     *
     * @return string
     */
    public function getMotoMerchantAccount()
    {
        return $this->getInfo()->getAdditionalInformation('motoMerchantAccount');
    }

    public function getAdyenCustomerAreaLink()
    {
        $storeId = $this->getInfo()->getOrder()->getStoreId();
        $motoMerchantAccount = $this->getMotoMerchantAccount();


        try {
            $motoMerchantAccountProperties = $this->adyenConfigHelper->getMotoMerchantAccountProperties($motoMerchantAccount, $storeId);

            if ($this->_adyenHelper->isMotoDemoMode($motoMerchantAccountProperties)) {
                $url = 'https://ca-test.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=';
            }
            else {
                $url = 'https://ca-live.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=';
            }

            $url .= $this->getAdyenPspReference() . '&txType=Payment';
        }
        catch (AdyenException $e) {
            $url = '#';
        }

        return $url;
    }
}
