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
use Magento\Framework\Escaper;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Notification\MessageInterface;

class VersionMessage implements MessageInterface
{
    private const GITHUB_LATEST_RELEASE_API_URL = 'https://api.github.com/repos/adyen/adyen-magento2/releases/latest';
    private const GITHUB_RELEASES_URL_PREFIX = 'https://github.com/Adyen/adyen-magento2/releases/';
    private const CONNECT_TIMEOUT_SECONDS = 1;
    private const REQUEST_TIMEOUT_SECONDS = 3;
    private const SESSION_KEY = 'AdyenGithubVersion';

    protected Session $_authSession;
    protected Data $_adyenHelper;
    protected InboxFactory $_inboxFactory;
    protected RequestInterface $request;
    protected PlatformInfo $_platformInfo;
    private Escaper $escaper;
    private CurlFactory $curlFactory;

    public function __construct(
        Session $authSession,
        Data $adyenHelper,
        InboxFactory $inboxFactory,
        RequestInterface $request,
        PlatformInfo $platformInfo,
        Escaper $escaper,
        CurlFactory $curlFactory
    ) {
        $this->_authSession = $authSession;
        $this->_adyenHelper = $adyenHelper;
        $this->_inboxFactory = $inboxFactory;
        $this->request = $request;
        $this->_platformInfo = $platformInfo;
        $this->escaper = $escaper;
        $this->curlFactory = $curlFactory;
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
                if ($githubContent === null) {
                    return false;
                }

                $this->setSessionData(self::SESSION_KEY, $githubContent);
                $title = "Adyen extension version " . $githubContent['tag_name'] . " available!";
                $versionData[] = [
                    'severity' => self::SEVERITY_NOTICE,
                    'date_added' => $githubContent['published_at'] ?? null,
                    'title' => $title,
                    'description' => $githubContent['body'] ?? '',
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
                if (version_compare($this->normalizeVersion($githubContent['tag_name'] ?? ''), $this->normalizeVersion($this->_platformInfo->getModuleVersion()), '>')) {
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
        $githubContent = $this->getSessionData(self::SESSION_KEY);
        $currentVersion = $this->escaper->escapeHtml((string) $this->_platformInfo->getModuleVersion());

        if (!is_array($githubContent) || !$this->isValidRelease($githubContent)) {
            return __(
                'A new Adyen extension version is now available. You are running the %1 version. We advise to update your extension.',
                $currentVersion
            );
        }

        return __(
            'A new Adyen extension version is now available: <a href="%1" target="_blank" rel="noopener noreferrer">%2!</a> You are running the %3 version. We advise to update your extension.',
            $this->escaper->escapeUrl($githubContent['html_url']),
            $this->escaper->escapeHtml($githubContent['tag_name']),
            $currentVersion
        );
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
        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout(self::REQUEST_TIMEOUT_SECONDS);
            $curl->setOption(CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
            $curl->setOption(CURLOPT_USERAGENT, 'magento');
            $curl->get(self::GITHUB_LATEST_RELEASE_API_URL);

            if ($curl->getStatus() !== 200) {
                return null;
            }

            $content = json_decode($curl->getBody(), true);
        } catch (\Exception $e) {
            return null;
        }

        if (!is_array($content) || !$this->isValidRelease($content)) {
            return null;
        }

        return $content;
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
        $githubContent = $this->getSessionData(self::SESSION_KEY);
        return version_compare($this->normalizeVersion($githubContent['tag_name'] ?? ''), $this->normalizeVersion($this->_platformInfo->getModuleVersion()), '>');
    }

    private function normalizeVersion(?string $version): string
    {
        return ltrim($version ?? '', 'vV');
    }

    private function isValidRelease(array $release): bool
    {
        $tagName = $release['tag_name'] ?? null;
        $htmlUrl = $release['html_url'] ?? null;

        return is_string($tagName)
            && preg_match('/^v?\d+\.\d+\.\d+$/', $tagName) === 1
            && is_string($htmlUrl)
            && str_starts_with($htmlUrl, self::GITHUB_RELEASES_URL_PREFIX);
    }
}
