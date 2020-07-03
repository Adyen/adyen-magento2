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
 * Copyright (c) 2017 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\AdminMessage;

class CronMessage implements \Magento\Framework\Notification\MessageInterface
{
    protected $_authSession;
    protected $_cronCheck;
    protected $_dateChecked;
    protected $_adyenHelper;
    protected $_timezoneInterface;
    protected $backendHelper;

    public function __construct(
        \Magento\Backend\Model\Auth\Session $authSession,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezoneInterface,
        \Magento\Backend\Helper\Data $backendHelper
    ) {
        $this->_authSession = $authSession;
        $this->_cronCheck = $this->getSessionData("cronCheck");
        $this->_dateChecked = $this->getSessionData("dateChecked");
        $this->_adyenHelper = $adyenHelper;
        $this->_timezoneInterface = $timezoneInterface;
        $this->backendHelper = $backendHelper;
    }

    const MESSAGE_IDENTITY = 'Adyen Cronjob system message';

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
        if ($this->_authSession->isFirstPageAfterLogin()) {
            $this->_dateChecked = $this->_timezoneInterface->date();
            $this->_cronCheck = $this->_adyenHelper->getUnprocessedNotifications();
            $this->setSessionData("cronCheck", $this->_cronCheck);
            $this->setSessionData("dateChecked", $this->_dateChecked);
        }

        // Do not show any message if there are no unprocessed notifications
        if ($this->_cronCheck > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Retrieve system message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        $urlNotificationsOverview = $this->backendHelper->getUrl("adyen/notifications/overview");
        $message = __(
            'You have <a href="%1">%2 unprocessed notification(s)</a>. Please check your Cron',
            $urlNotificationsOverview,
            $this->_cronCheck
        );
        $urlMagento = "http://devdocs.magento.com/guides/v2.0/config-guide/cli/config-cli-subcommands-cron.html";
        $urlAdyen = "https://docs.adyen.com/developers/plugins/magento-2/set-up-the-plugin-in-magento#step2runcron";
        $message .= __(
            ' and visit <a href="%1">Magento DevDocs</a> and <a href="%2">Adyen Docs</a> on how to configure Cron.',
            $urlMagento,
            $urlAdyen
        );
        $message .= __('<i> Last  cron check was: %1</i> ', $this->_dateChecked->format('d/m/Y H:i:s'));
        return __($message);
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
}
