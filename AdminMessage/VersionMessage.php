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
use Adyen\Payment\Helper\PlatformInfo;
use Magento\AdminNotification\Model\InboxFactory;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Notification\MessageInterface;

class VersionMessage implements MessageInterface
{
    protected Session $_authSession;
    protected Data $_adyenHelper;
    protected InboxFactory $_inboxFactory;
    protected RequestInterface $request;
    protected PlatformInfo $_platformInfo;

    public function __construct(
        Session $authSession,
        Data $adyenHelper,
        InboxFactory $inboxFactory,
        RequestInterface $request,
        PlatformInfo $platformInfo
    ) {
        $this->_authSession = $authSession;
        $this->_adyenHelper = $adyenHelper;
        $this->_inboxFactory = $inboxFactory;
        $this->request = $request;
        $this->_platformInfo = $platformInfo;
    }

    const MESSAGE_IDENTITY = 'Adyen Version Control message';

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
        try {
            if ($this->_authSession->isFirstPageAfterLogin()) {
                $githubContent = $this->getDecodedContentFromGithub();
                $this->setSessionData("AdyenGithubVersion", $githubContent);
                $title = "Adyen extension version " . $githubContent['tag_name'] . " available!";
                $versionData[] = [
                    'severity' => self::SEVERITY_NOTICE,
                    'date_added' => $githubContent['published_at'],
                    'title' => $title,
                    'description' => $githubContent['body'],
                    'url' => $githubContent['html_url'],
                    'is_read' => !$this->isNewVersionAvailable()
                ];

                /*
                 * The parse function checks if the $versionData message exists in the inbox,
                 * otherwise it will create it and add it to the inbox.
                 */
                $this->_inboxFactory->create()->parse(array_reverse($versionData));

                /*
                 * This will compare the currently installed version with the latest available one.
                 * A message will appear after the login if the two are not matching.
                 */
                if ($this->_platformInfo->getModuleVersion() != $githubContent['tag_name']) {
                    return true;
                }
            } elseif ($this->request->getModuleName() === 'mui' && $this->isNewVersionAvailable()) {
                /*
                 * If the message has already been added to `$persistedMessage` array
                 * in AdminNotification/Model/ResourceModel/System/Message/Collection/Synchronized
                 * allow the UI validation and return true.
                 */
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Retrieve system message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        $githubContent = $this->getSessionData("AdyenGithubVersion");
        $message = __("A new Adyen extension version is now available: ");
        $message .= __(
            "<a href= \"" . $githubContent['html_url'] . "\" target='_blank'> " . $githubContent['tag_name'] . "!</a>"
        );
        $message .= __(
            " You are running the " . $this->_platformInfo->getModuleVersion(
            ) . " version. We advise to update your extension."
        );
        return __($message);
    }

    /**
     * Retrieve system message severity
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }

    public function getDecodedContentFromGithub()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/adyen/adyen-magento2/releases/latest');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'magento');
        $content = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($content, true);
        return $json;
    }

    /**
     * Set the current value for the backend session
     */
    public function setSessionData($key, $value)
    {
        return $this->_authSession->setData($key, $value);
    }

    /**
     * Retrieve the session value
     */
    public function getSessionData($key, $remove = false)
    {
        return $this->_authSession->getData($key, $remove);
    }

    private function isNewVersionAvailable()
    {
        $githubContent = $this->getSessionData("AdyenGithubVersion");

        if (isset($githubContent)) {
            return $this->_platformInfo->getModuleVersion() !== $githubContent['tag_name'];
        }
    }
}
