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
	protected $_adyenHelper;

	/**
	 * @var \Magento\AdminNotification\Model\InboxFactory
	 */
	protected $_inboxFactory;

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
	 */
	public function __construct(
		\Adyen\Payment\Helper\Data $adyenHelper,
		\Magento\AdminNotification\Model\InboxFactory $inboxFactory,
		\Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
		\Magento\Backend\Model\Auth\Session $authSession
	) {
		$this->_adyenHelper = $adyenHelper;
		$this->_inboxFactory = $inboxFactory;
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
		if ($this->authSession->isFirstPageAfterLogin() && empty($this->_adyenHelper->getAPIKey())) {
			try {
				$title = "Adyen extension requires the API KEY!";

				$messageData[] = array(
					'severity' => $this->getSeverity(),
					'date_added' => date("Y-m-d"),
					'title' => $title,
					'description' => $this->getText(),
					'url' => "https://docs.adyen.com/developers/plug-ins-and-partners/magento-2/set-up-the-plugin-in-magento#step3configuretheplugininmagento",
				);

				/*
				 * The parse function checks if the $versionData message exists in the inbox,
				 * otherwise it will create it and add it to the inbox.
				 */
				$this->_inboxFactory->create()->parse(array_reverse($messageData));
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
	 * @return \Magento\Framework\Phrase
	 */
	public function getText()
	{
		if (!empty($this->_adyenHelper->getWsUsername())) {
			$message = "Please provide API-KEY for the webservice user " . $this->_adyenHelper->getWsUsername() . "  for default/store " . $this->storeManagerInterface->getStore()->getName();
		}else{
			$message = "Please provide API-KEY for default/store " . $this->storeManagerInterface->getStore()->getName();
		}

		return $message;
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
