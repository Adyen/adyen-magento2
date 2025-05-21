<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Exception\FileUploadException;
use Adyen\Payment\Model\TransportBuilder;
use Adyen\Payment\Helper\PlatformInfo;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class SupportFormHelper
{
    const MAX_ATTACHMENT_SIZE = 7000000;
    const MAX_TOTAL_SIZE = 10000000;

    // Support form types
    const CONFIGURATION_SETTINGS_FORM = 'configuration_settings';
    const ORDER_PROCESSING_FORM = 'order_processing';
    const OTHER_TOPICS_FORM = 'other_topics';

    /**
     * @var int
     */
    private $attachmentSize = 0;
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var PlatformInfo
     */
    private $platformInfo;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;
    /**
     * @var TransportBuilder
     */
    private $transportBuilder;
    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var Filesystem\Directory\WriteFactory
     */
    private $writeFactory;
    /**
     * @var Filesystem\Io\File
     */
    private $fileUtil;
    /**
     * @var Session
     */
    private $authSession;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param TransportBuilder $transportBuilder
     * @param MessageManagerInterface $messageManager
     * @param Config $config
     * @param PlatformInfo $platformInfo
     * @param StoreManagerInterface $storeManager
     * @param Filesystem\Io\File $fileUtil
     * @param Filesystem $filesystem
     * @param Filesystem\Directory\WriteFactory $writeFactory
     * @param ProductMetadataInterface $productMetadata
     * @param Session $authSession
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        TransportBuilder $transportBuilder,
        MessageManagerInterface $messageManager,
        Config $config,
        PlatformInfo $platformInfo,
        StoreManagerInterface $storeManager,
        Filesystem\Io\File $fileUtil,
        Filesystem $filesystem,
        Filesystem\Directory\WriteFactory $writeFactory,
        ProductMetadataInterface $productMetadata,
        Session $authSession,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->platformInfo = $platformInfo;
        $this->productMetadata = $productMetadata;
        $this->filesystem = $filesystem;
        $this->writeFactory = $writeFactory;
        $this->fileUtil = $fileUtil;
        $this->authSession = $authSession;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param array $formData
     * @param string $template
     *
     * @return void
     * @throws LocalizedException
     * @throws MailException
     * @throws FileUploadException
     */
    public function handleSubmit(array $formData, string $template): void
    {
        $storeId = $this->getStoreId();

        if (intval($formData['sendConfigurationValues'])) {
            $configurationData = $this->getConfigData();
            $templateVars = array_merge($configurationData, $formData);
        } else {
            $templateVars = $formData;
        }

        $templateOptions = [
            'area' => Area::AREA_ADMINHTML,
            'store' => $storeId
        ];

        $to = $this->config->getSupportMailAddress($storeId);

        /*
         * fromEmail address is different from the adminEmail,
         * since that value should be configured in mail sender.
         */
        $fromEmail = $this->getStoreGeneralEmail();
        $from = ['email' => $fromEmail, 'name' => $this->getAdminName()];

        $templateVars['emailSubject'] = sprintf(
            "Support Form Adobe Commerce - %s",
            $templateVars['subject']
        );

        $transportBuilder = $this->transportBuilder->setTemplateIdentifier($template)
            ->setTemplateOptions($templateOptions)
            ->setTemplateVars($templateVars)
            ->setFromByScope($from)
            ->setReplyTo($templateVars['email'])
            ->addTo($to);

        if (isset($formData['attachments']) && is_array($formData['attachments'])) {
            foreach ($formData['attachments'] as $file) {
                if (!empty($file['name'])) {
                    list($path, $filename) = $this->uploadAttachment($file);
                    $transportBuilder->setAttachment(file_get_contents($path), $filename);
                }
            }
        }

        $transport = $transportBuilder->getTransport();

        $transport->sendMessage();
        $this->messageManager->addSuccess(__('Form successfully submitted'));
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfigData(): array
    {
        $storeId = $this->getStoreId();
        $magentoEdition = $this->productMetadata->getEdition();
        $magentoVersion = $this->productMetadata->getVersion();
        $notificationUsername = $this->config->getNotificationsUsername($storeId) ? 'Yes' : 'No';
        $notificationPassword = $this->config->getNotificationsPassword($storeId) ? 'Yes' : 'No';
        $merchantAccount = $this->config->getMerchantAccount($storeId);
        $environmentMode = $this->config->isDemoMode($storeId) ? 'Test' : 'Live';
        $moduleVersion = $this->platformInfo->getModuleVersion();
        $isAlternativePaymentMethodsEnabled = $this->config->isAlternativePaymentMethodsEnabled($storeId);
        $isMotoEnabled = $this->config->isMotoPaymentMethodEnabled($storeId);
        $configurationMode = $this->config->getConfigurationMode($storeId);
        $isAdyenCcEnabled = $this->config->getConfigData('active', 'adyen_cc', $storeId, true);
        $isCardTokenizationEnabled = $this->config->getConfigData(
            'active',
            'adyen_oneclick',
            $storeId,
            true
        );
        $cardTokenizationMode = $this->config->getConfigData('card_type', 'adyen_oneclick', $storeId);
        $isStoreAlternativePaymentMethodsEnabled = $this->config->getConfigData(
            'active',
            'adyen_hpp_vault',
            $storeId,
            true
        );
        $alternativePaymentMethodTokenizationType = $this->config->getConfigData(
            'token_type',
            'adyen_hpp',
            $storeId
        );
        $isBoletoEnabled = $this->config->getConfigData('active', 'adyen_boleto', $storeId, true);
        $boletoDeliveryDays = $this->config->getConfigData('delivery_days', 'adyen_boleto', $storeId);
        $isPayByLinkEnabled = $this->config->getConfigData(
            'active',
            'adyen_pay_by_link',
            $storeId,
            true
        );
        $useManualCaptureForPaypal = $this->config->getConfigData(
            'paypal_capture_mode',
            'adyen_abstract',
            $storeId,
            true
        );
        $captureOpenInvoice = $this->config->getConfigData(
            'capture_for_openinvoice',
            'adyen_abstract',
            $storeId,
            true
        );
        $sendOrderConfirmationForSepaAndBankTransfer = $this->config->getConfigData(
            'send_email_bank_sepa_on_pending',
            'adyen_abstract',
            $storeId,
            true
        );
        $sepaPaymentFlow = $this->config->getConfigData(
            'sepa_flow',
            'adyen_abstract',
            $storeId
        );
        $captureDelay = $this->config->getConfigData(
            'capture_mode',
            'adyen_abstract',
            $storeId
        );
        $orderStatusCreation = $this->config->getConfigData(
            'order_status',
            'adyen_abstract',
            $storeId
        );
        $orderStatusPaymentAuthorisation = $this->config->getConfigData(
            'payment_pre_authorized',
            'adyen_abstract',
            $storeId
        );
        $orderStatusPaymentConfirmed = $this->config->getConfigData(
            'payment_authorized',
            'adyen_abstract',
            $storeId
        );
        $orderStatusCancellation = $this->config->getConfigData(
            'payment_cancelled',
            'adyen_abstract',
            $storeId
        );
        $orderStatusPaymentCaptureVirtualProducts = $this->config->getConfigData(
            'payment_authorized_virtual',
            'adyen_abstract',
            $storeId
        );
        $orderStatusPendingBankTransferSepa = $this->config->getConfigData(
            'pending_status',
            'adyen_abstract',
            $storeId
        );
        $manualReviewStatus = $this->config->getConfigData(
            'fraud_manual_review_status',
            'adyen_abstract',
            $storeId
        );
        $manualReviewAcceptedStatus = $this->config->getConfigData(
            'fraud_manual_review_accept_status',
            'adyen_abstract',
            $storeId
        );
        $ignoreRefundNotification = $this->config->getConfigData(
            'ignore_refund_notification',
            'adyen_abstract',
            $storeId,
            true
        );
        $refundStrategy = $this->config->getConfigData(
            'partial_payments_refund_strategy',
            'adyen_abstract',
            $storeId
        );
        $sendAdditionalRiskData = $this->config->getConfigData(
            'send_additional_risk_data',
            'adyen_abstract',
            $storeId,
            true
        );
        $sendLevel23Data = $this->config->getConfigData(
            'send_level23_data',
            'adyen_abstract',
            $storeId,
            true
        );
        $headlessPaymentOriginUrl = $this->config->getConfigData(
            'payment_origin_url',
            'adyen_abstract',
            $storeId
        );
        $headlessPaymentReturnUrl = $this->config->getConfigData(
            'payment_return_url',
            'adyen_abstract',
            $storeId
        );
        $customSuccessRedirectPath = $this->config->getConfigData(
            'custom_success_redirect_path',
            'adyen_abstract',
            $storeId
        );
        $isTestApiKeyConfigured = (bool) $this->config->getApiKey('test', $storeId);
        $isLiveApiKeyConfigured = (bool) $this->config->getApiKey('live', $storeId);
        $isTestClientKeyConfigured = (bool) $this->config->getClientKey('test', $storeId);
        $isLiveClientKeyConfigured = (bool) $this->config->getClientKey('live', $storeId);
        $isHmacKeyConfigured = (bool) $this->config->getNotificationsHmacKey($storeId);
        $isPosTestApiKeyConfigured = (bool) $this->config->getConfigData(
            'api_key_test',
            'adyen_pos_cloud',
            $storeId
        );
        $isPosLiveApiKeyConfigured = (bool) $this->config->getConfigData(
            'api_key_live',
            'adyen_pos_cloud',
            $storeId
        );
        $posMerchantAccount = $this->config->getConfigData(
            'pos_merchant_account',
            'adyen_pos_cloud',
            $storeId
        );

        return [
            'magentoEdition' => $magentoEdition,
            'magentoVersion' => $magentoVersion,
            'storeId' => $storeId,
            'pluginVersion' => $moduleVersion,
            'merchantAccount' => $merchantAccount,
            'environmentMode' => $environmentMode,
            'paymentMethodsEnabled' => $isAlternativePaymentMethodsEnabled ? 'Yes' : 'No',
            'notificationUsername' => $notificationUsername,
            'notificationPassword' => $notificationPassword,
            'motoEnabled' => $isMotoEnabled ? 'Yes' : 'No',
            'configurationMode' => $configurationMode,
            'isAdyenCcEnabled' => $isAdyenCcEnabled ? 'Yes' : 'No',
            'isCardTokenizationEnabled' => $isCardTokenizationEnabled ? 'Yes' : 'No',
            'cardTokenizationMode' => $cardTokenizationMode,
            'isStoreAlternativePaymentMethodsEnabled' => $isStoreAlternativePaymentMethodsEnabled ? 'Yes' : 'No',
            'alternativePaymentMethodTokenizationType' => $alternativePaymentMethodTokenizationType,
            'isBoletoEnabled' => $isBoletoEnabled ? 'Yes' : 'No',
            'boletoDeliveryDays' => $boletoDeliveryDays,
            'isPayByLinkEnabled' => $isPayByLinkEnabled ? 'Yes' : 'No',
            'useManualCaptureForPaypal' => $useManualCaptureForPaypal ? 'Yes' : 'No',
            'captureOpenInvoice' => $captureOpenInvoice,
            'sendOrderConfirmationForSepaAndBankTransfer' =>
                $sendOrderConfirmationForSepaAndBankTransfer ? 'Yes' : 'No',
            'sepaPaymentFlow' => $sepaPaymentFlow,
            'captureDelay' => $captureDelay,
            'orderStatusCreation' => $orderStatusCreation,
            'orderStatusPaymentAuthorisation' => $orderStatusPaymentAuthorisation,
            'orderStatusPaymentConfirmed' => $orderStatusPaymentConfirmed,
            'orderStatusCancellation' => $orderStatusCancellation,
            'orderStatusPaymentCaptureVirtualProducts' => $orderStatusPaymentCaptureVirtualProducts,
            'orderStatusPendingBankTransferSepa' => $orderStatusPendingBankTransferSepa,
            'manualReviewStatus' => $manualReviewStatus,
            'manualReviewAcceptedStatus' => $manualReviewAcceptedStatus,
            'ignoreRefundNotification' => $ignoreRefundNotification ? 'Yes' : 'No',
            'refundStrategy' => $refundStrategy,
            'sendAdditionalRiskData' => $sendAdditionalRiskData ? 'Yes' : 'No',
            'sendLevel23Data' => $sendLevel23Data ? 'Yes' : 'No',
            'headlessPaymentOriginUrl' => $headlessPaymentOriginUrl,
            'headlessPaymentReturnUrl' => $headlessPaymentReturnUrl,
            'customSuccessRedirectPath' => $customSuccessRedirectPath,
            'isTestApiKeyConfigured' => $isTestApiKeyConfigured ? 'Yes' : 'No',
            'isLiveApiKeyConfigured' => $isLiveApiKeyConfigured ? 'Yes' : 'No',
            'isTestClientKeyConfigured' => $isTestClientKeyConfigured ? 'Yes' : 'No',
            'isLiveClientKeyConfigured' => $isLiveClientKeyConfigured ? 'Yes' : 'No',
            'isHmacKeyConfigured' => $isHmacKeyConfigured ? 'Yes' : 'No',
            'isPosTestApiKeyConfigured' => $isPosTestApiKeyConfigured ? 'Yes' : 'No',
            'isPosLiveApiKeyConfigured' => $isPosLiveApiKeyConfigured ? 'Yes' : 'No',
            'posMerchantAccount' => $posMerchantAccount
        ];
    }

    /**
     * @return int
     * @throws NoSuchEntityException
     */
    public function getStoreId(): int
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * Get the stores general contact email address
     *
     * @return string
     */
    public function getStoreGeneralEmail(): string
    {
        return $this->scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get the email from the current admin user
     *
     * @return string
     */
    public function getAdminEmail(): string
    {
        return $this->authSession->getUser()->getEmail();
    }

    /**
     * @return string
     */
    public function getAdminName(): string
    {
        return $this->authSession->getUser()->getName();
    }

    /**
     * @param $file
     * @return array
     * @throws FileUploadException
     * @throws FileSystemException
     * @throws ValidatorException
     */
    private function uploadAttachment($file): array
    {
        $this->attachmentSize += $file['size'];
        if ($file['size'] > self::MAX_ATTACHMENT_SIZE || $this->attachmentSize > self::MAX_TOTAL_SIZE) {
            throw new FileUploadException(
                __('Invalid file size. Each file must 7MB or less and total upload size must be 10MB or less.')
            );
        }

        $fileInfo = $this->fileUtil->getPathInfo($file['name']);
        $allowedTypes = ['zip', 'txt', 'log', 'rar', 'jpeg', 'jpg', 'pdf', 'png'];
        if (!in_array($fileInfo['extension'], $allowedTypes)) {
            throw new FileUploadException(__('Invalid file type. Allowed types: ' . join(', ', $allowedTypes)));
        }

        $uploadDir = $this->writeFactory
            ->create($this->filesystem->getDirectoryRead(DirectoryList::TMP)->getAbsolutePath());

        $targetPath = $uploadDir->getDriver()->getRealPathSafety($uploadDir->getAbsolutePath($file['name']));

        // copy to target
        $result = $uploadDir->getDriver()->copy($file['tmp_name'], $targetPath);

        if (!$result) {
            throw new FileUploadException(__('Unable to upload attachment.'));
        }

        return [$targetPath, $file['name']];
    }

    /**
     * @param $request
     * @param $requiredFields
     * @return string
     */
    public function requiredFieldsMissing($request, $requiredFields): string
    {
       $requiredFieldsMissing = [];
        foreach ($requiredFields as $field) {
            if (empty($request[$field])) {
                $requiredFieldsMissing[] = $field;
            }
        }
        return implode(', ', $requiredFieldsMissing);
    }

    /**
     * @param string $supportFormType
     * @return array|null
     */
    public function getSupportTopicsByFormType(string $supportFormType): ?array
    {
        $supportTopics = [
            'configuration_settings' => [
                'required_settings' => 'Required settings',
                'card_payments' => 'Card payments',
                'card_tokenization' => 'Card tokenization',
                'alt_payment_methods' => 'Alternative payment methods',
                'pos_integration' => 'POS integration with cloud',
                'pay_by_link' => 'Pay By Link',
                'adyen_giving' => 'Adyen Giving',
                'advanced_settings' => 'Advanced settings',
            ],
            'order_processing' => [
                'payment_status' => 'Payment status',
                'failed_transaction' => 'Failed transaction',
                'offer' => 'Offer',
                'webhooks' => 'Notification &amp; webhooks',
            ],
            'other_topics' => []
        ];

        return $supportTopics[$supportFormType] ?? null;
    }

    /**
     * @param string $supportFormType
     * @return string[]|null
     */
    public function getIssuesTopicsByFormType(string $supportFormType): ?array
    {
        $issuesTopics = [
            'configuration_settings' =>  [
                'invalid_origin' => 'Invalid Origin',
                'headless_state_data_actions' => 'Headless state data actions',
                'refund' => 'Refund',
                'other' => 'Other'
            ]
        ];

        return $issuesTopics[$supportFormType] ?? null;
    }
}
