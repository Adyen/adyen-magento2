<?php
/**
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

use Adyen\Payment\Helper\Data;
use Magento\AdminNotification\Model\InboxFactory;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Notification\MessageInterface;
use Magento\Store\Model\StoreManagerInterface;

class APIKeyMessage implements MessageInterface
{
    /**
     * @var Data
     */
    protected $adyenHelper;

    /**
     * @var InboxFactory
     */
    protected $inboxFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var Session
     */
    protected $authSession;

    /**
     * @var RequestInterface
     */
    protected $request;

    const MESSAGE_IDENTITY = 'Adyen API Key Control message';

    /**
     * APIKeyMessage constructor.
     *
     * @param Data $adyenHelper
     * @param InboxFactory $inboxFactory
     * @param StoreManagerInterface $storeManagerInterface
     * @param Session $authSession
     */
    public function __construct(
        Data $adyenHelper,
        InboxFactory $inboxFactory,
        StoreManagerInterface $storeManagerInterface,
        Session $authSession,
        RequestInterface $request
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->inboxFactory = $inboxFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->authSession = $authSession;
        $this->request = $request;
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
        $isApiKeyMissing = empty($this->adyenHelper->getAPIKey());

        // Only execute the query the first time you access the Admin page
        if ($this->authSession->isFirstPageAfterLogin() && $isApiKeyMissing) {
            try {
                $title = 'Adyen extension requires the API KEY!';

                $messageData[] = [
                    'severity' => $this->getSeverity(),
                    'date_added' => date('Y-m-d'),
                    'title' => $title,
                    'description' => $this->getText(),
                    'url' => 'https://docs.adyen.com/developers/plugins/magento-2/' .
                        'set-up-adyen-customer-area#step1generateanapikey'
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
        } elseif ($this->request->getModuleName() === 'mui' && $isApiKeyMissing) {
            /*
             * If the message has already been added to `$persistedMessage` array
             * in AdminNotification/Model/ResourceModel/System/Message/Collection/Synchronized
             * allow the UI validation and return true.
             */
            return true;
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
