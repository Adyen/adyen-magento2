<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Asset\Source;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Payment\Helper\Data as MagentoDataHelper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class PaymentMethods extends AbstractHelper
{
    const ADYEN_HPP = 'adyen_hpp';
    const ADYEN_CC = 'adyen_cc';
    const ADYEN_ONE_CLICK = 'adyen_oneclick';

    const ADYEN_PREFIX = 'adyen_';

    const METHODS_WITH_BRAND_LOGO = [
        "giftcard"
    ];

    const METHODS_WITH_LOGO_FILE_MAPPING = [
        "scheme" => "card"
    ];

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var ScopeConfigInterface $config
     */
    protected $config;

    /**
     * @var Data
     */
    protected $adyenHelper;

    /**
     * @var MagentoDataHelper
     */
    private $dataHelper;

    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Source
     */
    protected $assetSource;

    /**
     * @var DesignInterface
     */
    protected $design;

    /**
     * @var ThemeProviderInterface
     */
    protected $themeProvider;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $quote;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /** @var Config */
    private $configHelper;

    /** @var SerializerInterface */
    private $serializer;

    public function __construct(
        Context $context,
        CartRepositoryInterface $quoteRepository,
        ScopeConfigInterface $config,
        Data $adyenHelper,
        ResolverInterface $localeResolver,
        AdyenLogger $adyenLogger,
        Repository $assetRepo,
        RequestInterface $request,
        Source $assetSource,
        DesignInterface $design,
        ThemeProviderInterface $themeProvider,
        ChargedCurrency $chargedCurrency,
        Config $configHelper,
        MagentoDataHelper $dataHelper,
        SerializerInterface $serializer
    ) {
        parent::__construct($context);
        $this->quoteRepository = $quoteRepository;
        $this->config = $config;
        $this->adyenHelper = $adyenHelper;
        $this->localeResolver = $localeResolver;
        $this->adyenLogger = $adyenLogger;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->assetSource = $assetSource;
        $this->design = $design;
        $this->themeProvider = $themeProvider;
        $this->chargedCurrency = $chargedCurrency;
        $this->configHelper = $configHelper;
        $this->dataHelper = $dataHelper;
        $this->serializer = $serializer;
    }

    /**
     * @param $quoteId
     * @param null $country
     * @return string|array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws AdyenException
     */
    public function getPaymentMethods($quoteId, $country = null)
    {
        // get quote from quoteId
        $quote = $this->quoteRepository->getActive($quoteId);
        // If quote cannot be found early return the empty paymentMethods array
        if (empty($quote)) {
            return [];
        }

        $this->setQuote($quote);

        return $this->fetchPaymentMethods($country);
    }

    /**
     * @param string $methodCode
     * @return bool
     */
    public function isAdyenPayment(string $methodCode): bool
    {
        return in_array($methodCode, $this->getAdyenPaymentMethods(), true);
    }

    /**
     * Returns an array of Adyen payment method codes
     *
     * @return string[]
     */
    public function getAdyenPaymentMethods() : array
    {
        $paymentMethods = $this->dataHelper->getPaymentMethodList();

        $filtered = array_filter(
            $paymentMethods,
            function ($key) {
                return strpos($key, self::ADYEN_PREFIX) === 0;
            },
            ARRAY_FILTER_USE_KEY
        );

        return array_keys($filtered);
    }

    /**
     * @param $country
     * @return string
     * @throws AdyenException
     * @throws LocalizedException
     * @throws \Exception
     */
    protected function fetchPaymentMethods($country): string
    {
        $quote = $this->getQuote();
        $store = $quote->getStore();

        $merchantAccount = $this->adyenHelper->getAdyenAbstractConfigData('merchant_account', $store->getId());
        if (!$merchantAccount) {
            return json_encode([]);
        }

        $paymentMethodRequest = $this->getPaymentMethodsRequest($merchantAccount, $store, $country, $quote);
        $responseData = $this->getPaymentMethodsResponse($paymentMethodRequest, $store);
        if (empty($responseData['paymentMethods'])) {
            return json_encode([]);
        }

        $paymentMethods = $responseData['paymentMethods'];
        $response['paymentMethodsResponse'] = $responseData;

        // Add extra details per payment method
        $paymentMethodsExtraDetails = [];
        $paymentMethodsExtraDetails = $this->showLogosPaymentMethods($paymentMethods, $paymentMethodsExtraDetails);
        $paymentMethodsExtraDetails = $this->addExtraConfigurationToPaymentMethods(
            $paymentMethods,
            $paymentMethodsExtraDetails
        );
        $response['paymentMethodsExtraDetails'] = $paymentMethodsExtraDetails;

        //TODO this should be the implemented with an interface
        return json_encode($response);
    }

    /**
     * @return float
     * @throws \Exception
     */
    protected function getCurrentPaymentAmount()
    {
        $total = $this->chargedCurrency->getQuoteAmountCurrency($this->getQuote())->getAmount();

        if (!is_numeric($total)) {
            throw new \Exception(
                sprintf(
                    'Cannot retrieve a valid grand total from quote ID: `%s`. Expected a numeric value.',
                    $this->getQuote()->getEntityId()
                )
            );
        }

        $total = (float)$total;

        if ($total >= 0) {
            return $total;
        }

        throw new \Exception(
            sprintf(
                'Cannot retrieve a valid grand total from quote ID: `%s`. Expected a float >= `0`, got `%f`.',
                $this->getQuote()->getEntityId(),
                $total
            )
        );
    }

    /**
     * @param $store
     * @return int|mixed|string
     */
    protected function getCurrentCountryCode($store, $country)
    {
        // if fixed countryCode is setup in config use this
        $countryCode = $this->adyenHelper->getAdyenHppConfigData('country_code', $store->getId());

        if ($countryCode != "") {
            return $countryCode;
        }

        if ($country != null) {
            return $country;
        }

        if ($country = $this->getQuote()->getBillingAddress()->getCountry()) {
            return $country;
        }

        $defaultCountry = $this->config->getValue(
            \Magento\Tax\Model\Config::CONFIG_XML_PATH_DEFAULT_COUNTRY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $store->getCode()
        );

        if ($defaultCountry) {
            return $defaultCountry;
        }

        return "";
    }

    /**
     * @param $requestParams
     * @param $store
     * @return array
     * @throws AdyenException
     */
    protected function getPaymentMethodsResponse($requestParams, $store)
    {
        // initialize the adyen client
        $client = $this->adyenHelper->initializeAdyenClient($store->getId());

        // initialize service
        $service = $this->adyenHelper->createAdyenCheckoutService($client);

        try {
            $responseData = $service->paymentMethods($requestParams);
        } catch (AdyenException $e) {
            $this->adyenLogger->error(
                "The Payment methods response is empty check your Adyen configuration in Magento."
            );
            // return empty result
            return [];
        }
        catch (\Adyen\ConnectionException $e) {
            $this->adyenLogger->error(
                "Connection to the endpoint failed. Check the Adyen Live endpoint prefix configuration."
            );
            return [];
        }

        return $responseData;
    }

    /**
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        return $this->quote;
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     */
    protected function setQuote(\Magento\Quote\Model\Quote $quote)
    {
        $this->quote = $quote;
    }

    /**
     * @return int
     */
    protected function getCurrentShopperReference()
    {
        return $this->getQuote()->getCustomerId();
    }

    /**
     * @param $merchantAccount
     * @param \Magento\Store\Model\Store $store
     * @param $country
     * @param \Magento\Quote\Model\Quote $quote
     * @return array
     * @throws \Exception
     */
    protected function getPaymentMethodsRequest(
        $merchantAccount,
        \Magento\Store\Model\Store $store,
        $country,
        \Magento\Quote\Model\Quote $quote
    ) {
        $currencyCode = $this->chargedCurrency->getQuoteAmountCurrency($quote)->getCurrencyCode();

        $paymentMethodRequest = [
            "channel" => "Web",
            "merchantAccount" => $merchantAccount,
            "countryCode" => $this->getCurrentCountryCode($store, $country),
            "shopperLocale" => $this->adyenHelper->getCurrentLocaleCode($store->getId()),
            "amount" => [
                "currency" => $currencyCode
            ]
        ];

        if (!empty($this->getCurrentShopperReference())) {
            $paymentMethodRequest["shopperReference"] = str_pad(
                $this->getCurrentShopperReference(),
                3,
                '0',
                STR_PAD_LEFT
            );
        }

        $amountValue = $this->adyenHelper->formatAmount($this->getCurrentPaymentAmount(), $currencyCode);

        if (!empty($amountValue)) {
            $paymentMethodRequest["amount"]["value"] = $amountValue;
        }

        $billingAddress = $quote->getBillingAddress();

        if (!empty($billingAddress) && !is_null($billingAddress->getTelephone())) {
            $paymentMethodRequest['telephoneNumber'] = trim($billingAddress->getTelephone());
        }

        return $paymentMethodRequest;
    }

    /**
     * @param $paymentMethods
     * @param array $paymentMethodsExtraDetails
     * @return array
     * @throws LocalizedException
     */
    protected function showLogosPaymentMethods($paymentMethods, array $paymentMethodsExtraDetails)
    {
        if (!$this->adyenHelper->showLogos()) {
            return $paymentMethodsExtraDetails;
        }
        // Explicitly setting theme
        $themeCode = "Magento/blank";

        $themeId = $this->design->getConfigurationDesignTheme(\Magento\Framework\App\Area::AREA_FRONTEND);
        if (!empty($themeId)) {
            $theme = $this->themeProvider->getThemeById($themeId);
            if ($theme && !empty($theme->getCode())) {
                $themeCode = $theme->getCode();
            }
        }

        $params = [];
        $params = array_merge(
            [
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                '_secure' => $this->request->isSecure(),
                'theme' => $themeCode
            ],
            $params
        );

        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodCode = in_array($paymentMethod['type'], self::METHODS_WITH_BRAND_LOGO)
                ? $paymentMethod['brand']
                : $paymentMethod['type'];

            $paymentMethodCode = !empty(self::METHODS_WITH_LOGO_FILE_MAPPING[$paymentMethod['type']])
                ? self::METHODS_WITH_LOGO_FILE_MAPPING[$paymentMethod['type']]
                : $paymentMethodCode;

            $asset = $this->assetRepo->createAsset(
                'Adyen_Payment::images/logos/' .
                $paymentMethodCode . '.png',
                $params
            );

            $placeholder = $this->assetSource->findSource($asset);

            if ($placeholder) {
                list($width, $height) = getimagesize($asset->getSourceFile());
                $icon = [
                    'url' => $asset->getUrl(),
                    'width' => $width,
                    'height' => $height
                ];
            } else {
                $icon = [
                    'url' => 'https://checkoutshopper-live.adyen.com/checkoutshopper/images/logos/medium/' . $paymentMethodCode . '.png',
                    'width' => 77,
                    'height' => 50
                ];
            }

            $paymentMethodsExtraDetails[$paymentMethodCode]['icon'] = $icon;

            //todo check if it is needed
            // check if payment method is an open invoice method
            $paymentMethodsExtraDetails[$paymentMethodCode]['isOpenInvoice'] =
                $this->adyenHelper->isPaymentMethodOpenInvoiceMethod($paymentMethodCode);
        }
        return $paymentMethodsExtraDetails;
    }

    protected function addExtraConfigurationToPaymentMethods($paymentMethods, array $paymentMethodsExtraDetails)
    {
        $quote = $this->getQuote();
        $currencyCode = $this->chargedCurrency->getQuoteAmountCurrency($quote)->getCurrencyCode();
        $amountValue = $this->adyenHelper->formatAmount($this->getCurrentPaymentAmount(), $currencyCode);

        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodCode = $paymentMethod['type'];

            $paymentMethodsExtraDetails[$paymentMethodCode]['configuration'] = [
                'amount' => [
                    'value' => $amountValue,
                    'currency' => $currencyCode
                ],
                'currency' => $currencyCode,
            ];
        }

        return $paymentMethodsExtraDetails;
    }

    /**
     * Checks if a payment is wallet payment method
     * @param $notificationPaymentMethod
     * @return bool
     */
    public function isWalletPaymentMethod($notificationPaymentMethod): bool
    {
        $walletPaymentMethods = [
            'googlepay',
            'paywithgoogle',
            'wechatpayWeb',
            'amazonpay',
            'applepay',
            'wechatpayQR',
            'alipay',
            'alipay_hk'
        ];
        return in_array($notificationPaymentMethod, $walletPaymentMethods);
    }

    /**
     * Check if the method of the passed payment is equal to the method passed in this function
     *
     * @param $payment
     * @param string $method
     * @return bool
     */
    public function checkPaymentMethod($payment, string $method): bool
    {
        return $payment->getMethod() === $method;
    }

    /**
     * Check if the passed payment method supports recurring functionality.
     *
     * Currently only SEPA is allowed on our Magento plugin.
     * Possible future payment methods:
     *
     * 'ach','amazonpay','applepay','directdebit_GB','bcmc','dana','dankort','eps','gcash','giropay','googlepay','paywithgoogle',
     * 'gopay_wallet','ideal','kakaopay','klarna','klarna_account','klarna_b2b','klarna_paynow','momo_wallet','paymaya_wallet',
     * 'paypal','trustly','twint','uatp','billdesk_upi','payu_IN_upi','vipps','yandex_money','zip'
     *
     * @param string $paymentMethod
     * @return bool
     */
    public function paymentMethodSupportsRecurring(string $paymentMethod): bool
    {
        $paymentMethodRecurring = [
            'sepadirectdebit',
        ];

        return in_array($paymentMethod, $paymentMethodRecurring);
    }

    /**
     * Check if order should be automatically captured
     *
     * @param Order $order
     * @param string $notificationPaymentMethod
     * @return bool
     */
    public function isAutoCapture(Order $order, string $notificationPaymentMethod): bool
    {
        // validate if payment methods allows manual capture
        if ($this->manualCaptureAllowed($notificationPaymentMethod)) {
            $captureMode = trim(
                $this->configHelper->getConfigData(
                    'capture_mode',
                    'adyen_abstract',
                    $order->getStoreId()
                )
            );
            $sepaFlow = trim(
                $this->configHelper->getConfigData(
                    'sepa_flow',
                    'adyen_abstract',
                    $order->getStoreId()
                )
            );
            $paymentCode = $order->getPayment()->getMethod();
            $captureModeOpenInvoice = $this->configHelper->getConfigData(
                'auto_capture_openinvoice',
                'adyen_abstract',
                $order->getStoreId()
            );
            $manualCapturePayPal = trim(
                $this->configHelper->getConfigData(
                    'paypal_capture_mode',
                    'adyen_abstract',
                    $order->getStoreId()
                )
            );

            /*
             * if you are using authcap the payment method is manual.
             * There will be a capture send to indicate if payment is successful
             */
            if ($notificationPaymentMethod == "sepadirectdebit") {
                if ($sepaFlow == "authcap") {
                    $this->adyenLogger->addAdyenNotificationCronjob(
                        'Manual Capture is applied for sepa because it is in authcap flow'
                    );
                    return false;
                } else {
                    // payment method ideal, cash adyen_boleto has direct capture
                    $this->adyenLogger->addAdyenNotificationCronjob(
                        'This payment method does not allow manual capture.(2) paymentCode:' .
                        $paymentCode . ' paymentMethod:' . $notificationPaymentMethod . ' sepaFLow:' . $sepaFlow
                    );
                    return true;
                }
            }

            if ($paymentCode == "adyen_pos_cloud") {
                $captureModePos = $this->adyenHelper->getAdyenPosCloudConfigData(
                    'capture_mode_pos',
                    $order->getStoreId()
                );
                if (strcmp($captureModePos, 'auto') === 0) {
                    $this->adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is POS Cloud and configured to be working as auto capture '
                    );
                    return true;
                } elseif (strcmp($captureModePos, 'manual') === 0) {
                    $this->adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is POS Cloud and configured to be working as manual capture '
                    );
                    return false;
                }
            }

            // if auto capture mode for openinvoice is turned on then use auto capture
            if ($captureModeOpenInvoice &&
                $this->adyenHelper->isPaymentMethodOpenInvoiceMethodValidForAutoCapture($notificationPaymentMethod)
            ) {
                $this->adyenLogger->addAdyenNotificationCronjob(
                    'This payment method is configured to be working as auto capture '
                );
                return true;
            }

            // if PayPal capture modues is different from the default use this one
            if (strcmp($notificationPaymentMethod, 'paypal') === 0) {
                if ($manualCapturePayPal) {
                    $this->adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is paypal and configured to work as manual capture'
                    );
                    return false;
                } else {
                    $this->adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is paypal and configured to work as auto capture'
                    );
                    return true;
                }
            }
            if (strcmp($captureMode, 'manual') === 0) {
                $this->adyenLogger->addAdyenNotificationCronjob(
                    'Capture mode for this payment is set to manual'
                );
                return false;
            }

            /*
             * online capture after delivery, use Magento backend to online invoice
             * (if the option auto capture mode for openinvoice is not set)
             */
            if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($notificationPaymentMethod)) {
                $this->adyenLogger->addAdyenNotificationCronjob(
                    'Capture mode for klarna is by default set to manual'
                );
                return false;
            }

            $this->adyenLogger->addAdyenNotificationCronjob('Capture mode is set to auto capture');
            return true;
        } else {
            // does not allow manual capture so is always immediate capture
            $this->adyenLogger->addAdyenNotificationCronjob(
                sprintf('Payment method %s, does not allow manual capture', $notificationPaymentMethod)
            );

            return true;
        }
    }

    /**
     * Validate if this payment methods allows manual capture
     * This is a default can be forced differently to overrule on acquirer level
     *
     * @param string $notificationPaymentMethod
     * @return bool
     */
    private function manualCaptureAllowed(string $notificationPaymentMethod): bool
    {
        $manualCaptureAllowed = false;
        // For all openinvoice methods manual capture is the default
        if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($notificationPaymentMethod)) {
            return true;
        }

        switch ($notificationPaymentMethod) {
            case 'cup':
            case 'cartebancaire':
            case 'visa':
            case 'visadankort':
            case 'mc':
            case 'uatp':
            case 'amex':
            case 'maestro':
            case 'maestrouk':
            case 'diners':
            case 'discover':
            case 'jcb':
            case 'laser':
            case 'paypal':
            case 'sepadirectdebit':
            case 'dankort':
            case 'elo':
            case 'hipercard':
            case 'mc_applepay':
            case 'visa_applepay':
            case 'amex_applepay':
            case 'discover_applepay':
            case 'maestro_applepay':
            case 'paywithgoogle':
            case 'svs':
            case 'givex':
            case 'valuelink':
            case 'twint':
                $manualCaptureAllowed = true;
                break;
            default:
                break;
        }

        return $manualCaptureAllowed;
    }

    /**
     * Compare the payment methods linked to the magento order and the adyen notification
     *
     * @param Order $order
     * @param Notification $notification
     * @return bool
     */
    public function compareOrderAndWebhookPaymentMethods(Order $order, Notification $notification): bool
    {
        // For cards, it can be 'VI', 'MI',... For alternatives, it can be 'ideal', 'directEbanking',...
        $orderPaymentMethod = $order->getPayment()->getCcType();
        $notificationPaymentMethod = $notification->getPaymentMethod();

        // Returns if the payment method is wallet like wechatpayWeb, amazonpay, applepay, paywithgoogle
        $isWalletPaymentMethod = $this->isWalletPaymentMethod($orderPaymentMethod);
        $isCardPaymentMethod = $order->getPayment()->getMethod() === 'adyen_cc' || $order->getPayment()->getMethod() === 'adyen_oneclick';

        // If it is a wallet method OR a card OR the methods match exactly, return true
        if ($isWalletPaymentMethod || $isCardPaymentMethod || strcmp($notificationPaymentMethod, $orderPaymentMethod) === 0) {
            return true;
        }

        return false;
    }

    /**
     * This function should be removed once we add classes for payment methods
     *
     * @param string $paymentMethod
     * @return bool
     */
    public function isBankTransfer(string $paymentMethod): bool
    {
        if (strlen($paymentMethod) >= 12 && substr($paymentMethod, 0, 12) == "bankTransfer") {
            $isBankTransfer = true;
        } else {
            $isBankTransfer = false;
        }
        return $isBankTransfer;
    }

    /**
     * @param Order $order
     * @param Notification $notification
     * @param $status
     * @return bool|mixed
     */
    public function getBoletoStatus(Order $order, Notification $notification, $status)
    {
        $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize(
            $notification->getAdditionalData()
        ) : "";

        $boletobancario = $additionalData['boletobancario'] ?? null;
        if ($boletobancario && is_array($boletobancario)) {
            // check if paid amount is the same as orginal amount
            $originalAmount = isset($boletobancario['originalAmount']) ? trim($boletobancario['originalAmount']) : "";
            $paidAmount = isset($boletobancario['paidAmount']) ? trim($boletobancario['paidAmount']) : "";

            if ($originalAmount != $paidAmount) {
                // not the full amount is paid. Check if it is underpaid or overpaid
                // strip the  BRL of the string
                $originalAmount = str_replace("BRL", "", $originalAmount);
                $originalAmount = floatval(trim($originalAmount));

                $paidAmount = str_replace("BRL", "", $paidAmount);
                $paidAmount = floatval(trim($paidAmount));

                if ($paidAmount > $originalAmount) {
                    $overpaidStatus = $this->configHelper->getConfigData(
                        'order_overpaid_status',
                        'adyen_boleto',
                        $order->getStoreId()
                    );
                    // check if there is selected a status if not fall back to the default
                    $status = (!empty($overpaidStatus)) ? $overpaidStatus : $status;
                } else {
                    $underpaidStatus = $this->configHelper->getConfigData(
                        'order_underpaid_status',
                        'adyen_boleto',
                        $order->getStoreId()
                    );
                    // check if there is selected a status if not fall back to the default
                    $status = (!empty($underpaidStatus)) ? $underpaidStatus : $status;
                }
            }
        }

        return $status;
    }
}
