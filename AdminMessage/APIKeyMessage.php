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
 * Adyen Payment Module
 *
 * Copyright (c) 2018 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\AdminMessage;

class APIKeyMessage implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * @var \Magento\AdminNotification\Model\InboxFactory
     */
    protected $inboxFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    protected $authSession;

    const MESSAGE_IDENTITY = 'Adyen API Key Control message';

    /**
     * APIKeyMessage constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\AdminNotification\Model\InboxFactory $inboxFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \Magento\Backend\Model\Auth\Session $authSession
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\AdminNotification\Model\InboxFactory $inboxFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Backend\Model\Auth\Session $authSession
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->inboxFactory = $inboxFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->authSession = $authSession;
    }

    /**
     * Retrieve unique system message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    /**
     * Check whether the system message should be shown
     *
     * @return bool
     */
    public function isDisplayed()
    {
        // Only execute the query the first time you access the Admin page
        if ($this->authSession->isFirstPageAfterLogin()
            && !empty($this->adyenHelper->getWsUsername())
            && empty($this->adyenHelper->getAPIKey())
        ) {
            try {
                $title = 'Adyen extension requires the API KEY!';

                $messageData[] = [
                    'severity' => $this->getSeverity(),
                    'date_added' => date('Y-m-d'),
                    'title' => $title,
                    'description' => $this->getText(),
                    'url' => 'https://docs.adyen.com/developers/plugins/magento-2/' .
                        'set-up-adyen-customer-area#step1generateanapikey',
                ];

                /*
                 * The parse function checks if the $versionData message exists in the inbox,
                 * otherwise it will create it and add it to the inbox.
                 */
                $this->inboxFactory->create()->parse($messageData);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Retrieve system message text
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getText()
    {
        return 'Please provide API-KEY for the webservice user ' .
            $this->adyenHelper->getWsUsername() . ' for default/store ' .
            $this->storeManagerInterface->getStore()->getName();
    }

    /**
     * Retrieve system message severity
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_CRITICAL;
    }
}
