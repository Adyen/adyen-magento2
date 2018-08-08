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

namespace Adyen\Payment\Plugin;

use Magento\Vault\Api\Data\PaymentTokenInterface;

class PaymentVaultDeleteToken
{
    /**
     * @var \Adyen\Payment\Model\Api\PaymentRequest
     */
    protected $_paymentRequest;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * PaymentVaultDeleteToken constructor.
     * @param \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest
     */
    public function __construct(
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_paymentRequest = $paymentRequest;
        $this->_storeManager = $storeManager;
    }

    public function beforeDelete(\Magento\Vault\Api\PaymentTokenRepositoryInterface $subject, PaymentTokenInterface $paymentToken)
    {
        if (strpos($paymentToken->getPaymentMethodCode(), 'adyen_') !== 0) {
            return [$paymentToken];
        }

        try {
            $this->_paymentRequest->disableRecurringContract(
                $paymentToken->getGatewayToken(),
                $paymentToken->getCustomerId(),
                $this->_storeManager->getStore()->getStoreId()
            );
        } catch(\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Failed to disable this contract'));
        }
    }
}
