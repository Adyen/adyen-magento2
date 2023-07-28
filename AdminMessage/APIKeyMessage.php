<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\AdminMessage;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Magento\AdminNotification\Model\InboxFactory;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Notification\MessageInterface;
use Magento\Store\Model\StoreManagerInterface;

class APIKeyMessage implements MessageInterface
{
    protected Data $adyenHelper;
    protected Config $configHelper;
    protected InboxFactory $inboxFactory;
    protected StoreManagerInterface $storeManagerInterface;
    protected Session $authSession;
    protected RequestInterface $request;

    const MESSAGE_IDENTITY = 'Adyen API Key Control message';

    public function __construct(
        Data                  $adyenHelper,
        Config                $configHelper,
        InboxFactory          $inboxFactory,
        StoreManagerInterface $storeManagerInterface,
        Session               $authSession,
        RequestInterface      $request
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->configHelper = $configHelper;
        $this->inboxFactory = $inboxFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->authSession = $authSession;
        $this->request = $request;

    }

    public function getIdentity(): string
    {
        return self::MESSAGE_IDENTITY;
    }

    public function isDisplayed(): bool
    {
        $mode = $this->configHelper->isDemoMode() ? 'test' : 'live';
        $isApiKeyMissing = empty($this->configHelper->getAPIKey($mode));
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

    public function getText(): string
    {
        return 'Please provide API-KEY for the webservice user ' .
            $this->configHelper->getNotificationsUsername() . ' for default/store ' .
            $this->storeManagerInterface->getStore()->getName();
    }

    public function getSeverity(): int
    {
        return self::SEVERITY_CRITICAL;
    }
}
