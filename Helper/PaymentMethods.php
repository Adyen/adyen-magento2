<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\ConnectionException;
use Adyen\Payment\Helper\Util\PaymentMethodUtil;
use Adyen\Model\Checkout\PaymentMethodsRequest;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Ui\Adminhtml\AdyenMotoConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Magento\Framework\App\Area;
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
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Adyen\Payment\Helper\Data as AdyenDataHelper;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class PaymentMethods extends AbstractHelper
{
    const ADYEN_HPP = 'adyen_hpp';
    const ADYEN_CC = 'adyen_cc';
    const ADYEN_ONE_CLICK = 'adyen_oneclick';
    const ADYEN_PAY_BY_LINK = 'adyen_pay_by_link';
    const ADYEN_PAYPAL = 'adyen_paypal';
    const ADYEN_PREFIX = 'adyen_';
    const ADYEN_CC_VAULT = 'adyen_cc_vault';
    const METHODS_WITH_BRAND_LOGO = [
        "giftcard"
    ];
    const METHODS_WITH_LOGO_FILE_MAPPING = [
        "scheme" => "card"
    ];

    const FUNDING_SOURCE_DEBIT = 'debit';
    const FUNDING_SOURCE_CREDIT = 'credit';

    const ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS = 'adyen-alternative-payment-method';
    const CONFIG_FIELD_REQUIRES_LINE_ITEMS = 'requires_line_items';
    const CONFIG_FIELD_IS_OPEN_INVOICE = 'is_open_invoice';

    const VALID_CHANNELS = ["iOS", "Android", "Web"];

    /*
     * Following payment methods should be enabled with their own configuration path.
     */
    const EXCLUDED_PAYMENT_METHODS = [
        AdyenPayByLinkConfigProvider::CODE,
        AdyenPosCloudConfigProvider::CODE,
        AdyenMotoConfigProvider::CODE
    ];

    /**
     * @var CartRepositoryInterface
     */
    protected CartRepositoryInterface $quoteRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $config;

    /**
     * @var Data
     */
    protected Data $adyenHelper;

    /**
     * @var MagentoDataHelper
     */
    private MagentoDataHelper $dataHelper;

    /**
     * @var ResolverInterface
     */
    protected ResolverInterface $localeResolver;

    /**
     * @var AdyenLogger
     */
    protected AdyenLogger $adyenLogger;

    /**
     * @var Data
     */
    protected Data $adyenDataHelper;

    /**
     * @var Repository
     */
    protected Repository $assetRepo;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @var Source
     */
    protected Source $assetSource;

    /**
     * @var DesignInterface
     */
    protected DesignInterface $design;

    /**
     * @var ThemeProviderInterface
     */
    protected ThemeProviderInterface $themeProvider;

    /**
     * @var CartInterface
     */
    protected CartInterface $quote;

    /**
     * @var ChargedCurrency
     */
    private ChargedCurrency $chargedCurrency;

    /**
     * @var Config
     */
    private Config $configHelper;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private PaymentTokenRepositoryInterface $paymentTokenRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @param Context $context
     * @param CartRepositoryInterface $quoteRepository
     * @param ScopeConfigInterface $config
     * @param Data $adyenHelper
     * @param ResolverInterface $localeResolver
     * @param AdyenLogger $adyenLogger
     * @param Repository $assetRepo
     * @param RequestInterface $request
     * @param Source $assetSource
     * @param DesignInterface $design
     * @param ThemeProviderInterface $themeProvider
     * @param ChargedCurrency $chargedCurrency
     * @param Config $configHelper
     * @param MagentoDataHelper $dataHelper
     * @param SerializerInterface $serializer
     * @param Data $adyenDataHelper
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
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
        SerializerInterface $serializer,
        AdyenDataHelper $adyenDataHelper,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
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
        $this->adyenDataHelper = $adyenDataHelper;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @param int $quoteId
     * @param string|null $country
     * @param string|null $shopperLocale
     * @param string|null $channel
     * @return string
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getPaymentMethods(
        int $quoteId,
        ?string $country = null,
        ?string $shopperLocale = null,
        ?string $channel = null
    ): string
    {
        // get quote from quoteId
        $quote = $this->quoteRepository->getActive($quoteId);
        // If quote cannot be found early return the empty paymentMethods array
        if (empty($quote)) {
            return '';
        }

        $this->setQuote($quote);

        return $this->fetchPaymentMethods($country, $shopperLocale, $channel);
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
     * @return array
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
     * @param bool|null $isActive
     * @param string $scope
     * @param int $scopeId
     * @return array
     */
    public function togglePaymentMethodsActivation(
        ?bool $isActive = null,
        string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        int $scopeId = 0
    ): array {
        $enabledPaymentMethods = [];

        if (is_null($isActive)) {
            $isActive = $this->configHelper->getIsPaymentMethodsActive();
        }

        foreach ($this->getAdyenPaymentMethods() as $paymentMethod) {
            if (in_array($paymentMethod, self::EXCLUDED_PAYMENT_METHODS)) {
                continue;
            }

            $value = $isActive ? '1': '0';
            $field = 'active';
            $this->configHelper->setConfigData($value, $field, $paymentMethod, $scope, $scopeId);
            $enabledPaymentMethods[] = $paymentMethod;
        }

        return $enabledPaymentMethods;
    }

    /**
     * Remove activation config
     * @param string $scope
     * @param int $scopeId
     * @return void
     */
    public function removePaymentMethodsActivation(string $scope, int $scopeId): void
    {
        foreach ($this->getAdyenPaymentMethods() as $paymentMethod)
        {
            if (in_array($paymentMethod, self::EXCLUDED_PAYMENT_METHODS)) {
                continue;
            }

            $this->configHelper->removeConfigData('active', $paymentMethod, $scope, $scopeId);
        }
    }

    /**
     * @param string|null $country
     * @param string|null $shopperLocale
     * @param string|null $channel
     * @return string
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function fetchPaymentMethods(
        ?string $country = null,
        ?string $shopperLocale = null,
        ?string $channel = null
    ): string
    {
        $quote = $this->getQuote();
        $store = $quote->getStore();

        $merchantAccount = $this->configHelper->getAdyenAbstractConfigData('merchant_account', $store->getId());
        if (!$merchantAccount) {
            return json_encode([]);
        }

        $requestData = $this->getPaymentMethodsRequest(
            $merchantAccount,
            $store,
            $quote,
            $shopperLocale,
            $country,
            $channel
        );
        $responseData = $this->getPaymentMethodsResponse($requestData, $store);
        if (empty($responseData['paymentMethods'])) {
            return json_encode([]);
        }

        $paymentMethods = $responseData['paymentMethods'];

        $allowMultistoreTokens = $this->configHelper->getAllowMultistoreTokens($store->getId());
        $customerId = $quote->getCustomerId();
        $responseData = $this->filterStoredPaymentMethods($allowMultistoreTokens, $responseData, $customerId);

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
     * @param $allowMultistoreTokens
     * @param $responseData
     * @param $customerId
     * @return mixed
     */
    protected function filterStoredPaymentMethods($allowMultistoreTokens, $responseData, $customerId): mixed
    {
        if (!$allowMultistoreTokens && isset($responseData['storedPaymentMethods'])) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('customer_id', $customerId)
                ->create();

            $paymentTokens = $this->paymentTokenRepository->getList($searchCriteria)->getItems();

            $gatewayTokens = array_map(function ($paymentToken) {
                return $paymentToken->getGatewayToken();
            }, $paymentTokens);

            $storedPaymentMethods = $responseData['storedPaymentMethods'];
            $responseData['storedPaymentMethods'] = array_filter(
                $storedPaymentMethods,
                function ($method) use ($gatewayTokens) {
                return in_array($method['id'], $gatewayTokens);
            });
        }

        return $responseData;
    }

    /**
     * @return float
     * @throws AdyenException
     */
    protected function getCurrentPaymentAmount(): float
    {
        $total = $this->chargedCurrency->getQuoteAmountCurrency($this->getQuote())->getAmount();

        if (!is_numeric($total)) {
            $exceptionMessage =
                sprintf(
                    'Cannot retrieve a valid grand total from quote ID: `%s`. Expected a numeric value.',
                    $this->getQuote()->getEntityId()
            );
            throw new AdyenException($exceptionMessage);
        }

        $total = (float)$total;

        if ($total >= 0) {
            return $total;
        }
        $exceptionMessage =
            sprintf(
                'Cannot retrieve a valid grand total from quote ID: `%s`. Expected a float >= `0`, got `%f`.',
                $this->getQuote()->getEntityId(),
                $total
            );
        throw new AdyenException($exceptionMessage);
    }

    /**
     * @param Store $store
     * @return string
     */
    protected function getCurrentCountryCode(Store $store): string
    {
        $quote = $this->getQuote();
        $billingAddressCountry = $quote->getBillingAddress()->getCountryId();

        // If customer is guest, billing address country might not be set yet
        if (isset($billingAddressCountry)) {
            return $billingAddressCountry;
        }

        $defaultCountry = $this->config->getValue(
            \Magento\Tax\Model\Config::CONFIG_XML_PATH_DEFAULT_COUNTRY,
            ScopeInterface::SCOPE_STORES,
            $store->getCode()
        );

        if ($defaultCountry) {
            return $defaultCountry;
        }

        return "";
    }

    /**
     * @param array $requestParams
     * @param Store $store
     * @return array
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    protected function getPaymentMethodsResponse(array $requestParams, Store $store): array
    {
        // initialize the adyen client
        $client = $this->adyenHelper->initializeAdyenClient($store->getId());

        // initialize service
        $service =$this->adyenHelper->initializePaymentsApi($client);

        try {
            $this->adyenHelper->logRequest($requestParams, Client::API_CHECKOUT_VERSION, '/paymentMethods');
            $response = $service->paymentMethods(new PaymentMethodsRequest($requestParams));
            $responseData = $response->toArray();
        } catch (AdyenException $e) {
            $this->adyenLogger->error(
                "The Payment methods response is empty check your Adyen configuration in Magento."
            );
            // return empty result
            return [];
        }
        catch (ConnectionException $e) {
            $this->adyenLogger->error(
                "Connection to the endpoint failed. Check the Adyen Live endpoint prefix configuration."
            );
            return [];
        }
        $this->adyenHelper->logResponse($responseData);

        return $responseData;
    }

    /**
     * @return CartInterface
     */
    protected function getQuote(): CartInterface
    {
        return $this->quote;
    }

    /**
     * @param CartInterface $quote
     * @return void
     */
    protected function setQuote(CartInterface $quote): void
    {
        $this->quote = $quote;
    }

    /**
     * @return string|null
     */
    protected function getCurrentShopperReference(): ?string
    {
        $customerId = $this->getQuote()->getCustomerId();
        return $customerId ? (string)$customerId : null;
    }

    /**
     * @param $merchantAccount
     * @param Store $store
     * @param Quote $quote
     * @param string|null $shopperLocale
     * @param string|null $country
     * @param string|null $channel
     * @return array
     * @throws AdyenException
     */
    protected function getPaymentMethodsRequest(
        $merchantAccount,
        Store $store,
        Quote $quote,
        ?string $shopperLocale = null,
        ?string $country = null,
        ?string $channel = null
    ): array {
        $currencyCode = $this->chargedCurrency->getQuoteAmountCurrency($quote)->getCurrencyCode();

        $channel = in_array($channel, self::VALID_CHANNELS, true) ? $channel : "Web";

        $paymentMethodRequest = [
            "channel" => $channel ?? "Web",
            "merchantAccount" => $merchantAccount,
            "countryCode" => $country ?? $this->getCurrentCountryCode($store),
            "shopperLocale" => $shopperLocale ?? $this->adyenHelper->getCurrentLocaleCode($store->getId()),
            "amount" => [
                "currency" => $currencyCode
            ]
        ];

        if (!empty($this->getCurrentShopperReference())) {
            $paymentMethodRequest["shopperReference"] =
                $this->adyenDataHelper->padShopperReference($this->getCurrentShopperReference());
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
     * @param array $paymentMethods
     * @param array $paymentMethodsExtraDetails
     * @return array
     * @throws LocalizedException
     */
    protected function showLogosPaymentMethods(array $paymentMethods, array $paymentMethodsExtraDetails): array
    {
        if (!$this->adyenHelper->showLogos()) {
            return $paymentMethodsExtraDetails;
        }
        // Explicitly setting theme
        $themeCode = "Magento/blank";

        $themeId = $this->design->getConfigurationDesignTheme(Area::AREA_FRONTEND);
        if (!empty($themeId)) {
            $theme = $this->themeProvider->getThemeById($themeId);
            if ($theme && !empty($theme->getCode())) {
                $themeCode = $theme->getCode();
            }
        }

        $params = [];
        $params = array_merge(
            [
                'area' => Area::AREA_FRONTEND,
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

            $icon = $this->buildPaymentMethodIcon($paymentMethodCode, $params);

            $paymentMethodsExtraDetails[$paymentMethodCode]['icon'] = $icon;

            // TODO::This field is not relevant anymore and can be removed during cleaning-up deprecated methods on V10.
            // check if payment method is an open invoice method
            $paymentMethodsExtraDetails[$paymentMethodCode]['isOpenInvoice'] =
                $this->adyenHelper->isPaymentMethodOpenInvoiceMethod($paymentMethodCode);
        }
        return $paymentMethodsExtraDetails;
    }

    /**
     * @param array $paymentMethods
     * @param array $paymentMethodsExtraDetails
     * @return array
     * @throws AdyenException
     */
    protected function addExtraConfigurationToPaymentMethods(
        array $paymentMethods,
        array $paymentMethodsExtraDetails
    ): array {
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
     * @param MethodInterface $paymentMethodInstance
     * @return bool
     */
    public function isWalletPaymentMethod(MethodInterface $paymentMethodInstance): bool
    {
        return boolval($paymentMethodInstance->getConfigData('is_wallet'));
    }

    /**
     * @param MethodInterface $paymentMethodInstance
     * @return bool
     */
    public function isAlternativePaymentMethod(MethodInterface $paymentMethodInstance): bool
    {
        return $paymentMethodInstance->getConfigData('group') === self::ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS;
    }

    /**
     * @param MethodInterface $paymentMethodInstance
     * @return string
     * @throws AdyenException
     */
    public function getAlternativePaymentMethodTxVariant(MethodInterface $paymentMethodInstance): string
    {
        if (!$this->isAlternativePaymentMethod($paymentMethodInstance)) {
            throw new AdyenException('Given payment method is not an Adyen alternative payment method!');
        }

        return str_replace('adyen_', '', $paymentMethodInstance->getCode());
    }

    /**
     * @param MethodInterface $paymentMethodInstance
     * @return bool
     */
    public function paymentMethodSupportsRecurring(MethodInterface $paymentMethodInstance): bool
    {
        return boolval($paymentMethodInstance->getConfigData('supports_recurring'));
    }

    /**
     * @param Payment $payment
     * @param string $method
     * @return bool
     */
    public function checkPaymentMethod(Order\Payment $payment, string $method): bool
    {
        return $payment->getMethod() === $method;
    }

    /**
     * @return array
     */
    public function getCcAvailableTypes(): array
    {
        $types = [];
        $ccTypes = $this->adyenHelper->getAdyenCcTypes();
        $availableTypes = $this->configHelper->getAdyenCcConfigData('cctypes');
        if ($availableTypes) {
            $availableTypes = explode(',', (string) $availableTypes);
            foreach (array_keys($ccTypes) as $code) {
                if (in_array($code, $availableTypes)) {
                    $types[$code] = $ccTypes[$code]['name'];
                }
            }
        }

        return $types;
    }

    /**
     * @return array
     */
    public function getCcAvailableTypesByAlt(): array
    {
        $types = [];
        $ccTypes = $this->adyenHelper->getAdyenCcTypes();
        $availableTypes = $this->configHelper->getAdyenCcConfigData('cctypes');
        if ($availableTypes) {
            $availableTypes = explode(',', (string) $availableTypes);
            foreach (array_keys($ccTypes) as $code) {
                if (in_array($code, $availableTypes)) {
                    $types[$ccTypes[$code]['code_alt']] = $code;
                }
            }
        }

        return $types;
    }

    /**
     * Checks whether if the capture mode is auto on an order with the given notification `paymentMethod`.
     * Note that, only a `notificationPaymentMethod` related to the order should be provided.
     *
     * @param Order $order Order object
     * @param string $notificationPaymentMethod `paymentMethod` provided on the webhook of the given order
     * @return bool
     */
    public function isAutoCapture(Order $order, string $notificationPaymentMethod): bool
    {
        // TODO::Add a validation checking `$notificationPaymentMethod` belongs to the correct order (webhook) or not.

        $payment = $order->getPayment();
        $paymentMethodInstance = $payment->getMethodInstance();

        // validate if payment methods allows manual capture
        if (PaymentMethodUtil::isManualCaptureSupported($notificationPaymentMethod)) {
            $captureMode = trim(
                (string) $this->configHelper->getConfigData(
                    'capture_mode',
                    'adyen_abstract',
                    $order->getStoreId()
                )
            );
            $sepaFlow = trim(
                (string) $this->configHelper->getConfigData(
                    'sepa_flow',
                    'adyen_abstract',
                    $order->getStoreId()
                )
            );
            $paymentCode = $order->getPayment()->getMethod();
            $autoCaptureOpenInvoice = $this->configHelper->getAutoCaptureOpenInvoice($order->getStoreId());
            $manualCapturePayPal = trim(
                (string) $this->configHelper->getConfigData(
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
                    $this->adyenLogger->addAdyenNotification(
                        'Manual Capture is applied for sepa because it is in authcap flow',
                        array_merge(
                            $this->adyenLogger->getOrderContext($order),
                            ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                        )
                    );
                    return false;
                } else {
                    // payment method ideal, cash adyen_boleto has direct capture
                    $this->adyenLogger->addAdyenNotification(
                        'This payment method does not allow manual capture.(2) paymentCode:' .
                        $paymentCode . ' paymentMethod:' . $notificationPaymentMethod . ' sepaFLow:' . $sepaFlow,
                        array_merge(
                            $this->adyenLogger->getOrderContext($order),
                            ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                        )
                    );
                    return true;
                }
            }

            if ($paymentCode == "adyen_pos_cloud") {
                $captureModePos = $this->configHelper->getAdyenPosCloudConfigData(
                    'capture_mode_pos',
                    $order->getStoreId()
                );
                if (strcmp((string) $captureModePos, 'auto') === 0) {
                    $this->adyenLogger->addAdyenNotification(
                        'This payment method is POS Cloud and configured to be working as auto capture ',
                        array_merge(
                            $this->adyenLogger->getOrderContext($order),
                            ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                        )
                    );
                    return true;
                } elseif (strcmp((string) $captureModePos, 'manual') === 0) {
                    $this->adyenLogger->addAdyenNotification(
                        'This payment method is POS Cloud and configured to be working as manual capture ',
                        array_merge(
                            $this->adyenLogger->getOrderContext($order),
                            ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                        )
                    );
                    return false;
                }
            }

            // if auto capture mode for openinvoice is turned on then use auto capture
            if ($autoCaptureOpenInvoice && $this->isOpenInvoice($paymentMethodInstance)) {
                $this->adyenLogger->addAdyenNotification(
                    'This payment method is configured to be working as auto capture ',
                    array_merge(
                        $this->adyenLogger->getOrderContext($order),
                        ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                    )
                );
                return true;
            }

            // if PayPal capture modues is different from the default use this one
            if (strcmp($notificationPaymentMethod, 'paypal') === 0) {
                if ($manualCapturePayPal) {
                    $this->adyenLogger->addAdyenNotification(
                        'This payment method is paypal and configured to work as manual capture',
                        array_merge(
                            $this->adyenLogger->getOrderContext($order),
                            ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                        )
                    );
                    return false;
                } else {
                    $this->adyenLogger->addAdyenNotification(
                        'This payment method is paypal and configured to work as auto capture',
                        array_merge(
                            $this->adyenLogger->getOrderContext($order),
                            ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                        )
                    );
                    return true;
                }
            }
            if (strcmp($captureMode, 'manual') === 0) {
                $this->adyenLogger->addAdyenNotification(
                    'Capture mode for this payment is set to manual',
                    array_merge(
                        $this->adyenLogger->getOrderContext($order),
                        [
                            'paymentMethod' => $notificationPaymentMethod,
                            'pspReference' => $order->getPayment()->getData('adyen_psp_reference')
                        ]
                    )
                );
                return false;
            }

            /*
             * online capture after delivery, use Magento backend to online invoice
             * (if the option auto capture mode for openinvoice is not set)
             */
            if ($this->isOpenInvoice($paymentMethodInstance)) {
                $this->adyenLogger->addAdyenNotification(
                    'Capture mode for klarna is by default set to manual',
                    array_merge(
                        $this->adyenLogger->getOrderContext($order),
                        ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                    )
                );
                return false;
            }

            $this->adyenLogger->addAdyenNotification(
                'Capture mode is set to auto capture',
                array_merge(
                    $this->adyenLogger->getOrderContext($order),
                    ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                )
            );
            return true;
        } else {
            // does not allow manual capture so is always immediate capture
            $this->adyenLogger->addAdyenNotification(
                sprintf('Payment method %s, does not allow manual capture', $notificationPaymentMethod),
                array_merge(
                    $this->adyenLogger->getOrderContext($order),
                    ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                )
            );

            return true;
        }
    }

    /**
     * @param Order $order
     * @param Notification $notification
     * @return bool
     * @throws AdyenException
     * @throws LocalizedException
     */
    public function compareOrderAndWebhookPaymentMethods(Order $order, Notification $notification): bool
    {
        $paymentMethodInstance = $order->getPayment()->getMethodInstance();

        if ($this->isAlternativePaymentMethod($paymentMethodInstance)) {
            $orderPaymentMethod = $this->getAlternativePaymentMethodTxVariant($paymentMethodInstance);
        } else {
            $orderPaymentMethod = $order->getPayment()->getCcType();
        }

        $notificationPaymentMethod = $notification->getPaymentMethod();

        // Returns if the payment method is wallet like wechatpayWeb, amazonpay, applepay, paywithgoogle
        $isWalletPaymentMethod = $this->isWalletPaymentMethod($paymentMethodInstance);
        $isCardPaymentMethod = $order->getPayment()->getMethod() === self::ADYEN_CC || $order->getPayment()->getMethod() === self::ADYEN_ONE_CLICK;

        // If it is a wallet method OR a card OR the methods match exactly, return true
        if ($isWalletPaymentMethod || $isCardPaymentMethod || strcmp($notificationPaymentMethod, $orderPaymentMethod) === 0) {
            return true;
        }

        return false;
    }

    /**
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
     * @param string $status
     * @return string|null
     */
    public function getBoletoStatus(Order $order, Notification $notification, string $status): ?string
    {
        $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize(
            $notification->getAdditionalData()
        ) : "";

        $boletobancario = $additionalData['boletobancario'] ?? null;
        if ($boletobancario && is_array($boletobancario)) {
            // check if paid amount is the same as orginal amount
            $originalAmount =
                isset($boletobancario['originalAmount']) ?
                    trim((string) $boletobancario['originalAmount']) :
                    "";
            $paidAmount = isset($boletobancario['paidAmount']) ? trim((string) $boletobancario['paidAmount']) : "";

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

    /**
     * @param string $paymentMethodCode
     * @param array $params
     * @return array
     * @throws LocalizedException
     */
    public function buildPaymentMethodIcon(string $paymentMethodCode, array $params): array
    {
        $svgAsset = $this->assetRepo->createAsset("Adyen_Payment::images/logos/$paymentMethodCode.svg", $params);
        $pngAsset = $this->assetRepo->createAsset("Adyen_Payment::images/logos/$paymentMethodCode.png", $params);

        if ($this->assetSource->findSource($svgAsset)) {
            $asset = $svgAsset;
        } elseif ($this->assetSource->findSource($pngAsset)) {
            $asset = $pngAsset;
        }

        if (isset($asset)) {
            $url = $asset->getUrl();
        } else {
            $url = "https://checkoutshopper-live.adyen.com/checkoutshopper/images/logos/$paymentMethodCode.svg";
        }

        return ['url' => $url, 'width' => 77, 'height' => 50];
    }

    /**
     * Checks whether if the payment method is open invoice or not based on `is_open_invoice` configuration field.
     *
     * @param MethodInterface $paymentMethodInstance
     * @return bool
     */
    public function isOpenInvoice(MethodInterface $paymentMethodInstance): bool
    {
        return boolval($paymentMethodInstance->getConfigData(self::CONFIG_FIELD_IS_OPEN_INVOICE));
    }

    /**
     * Checks the requirement of line items for the given payment method
     *
     * @param MethodInterface $paymentMethodInstance
     * @return bool
     */
    public function getRequiresLineItems(MethodInterface $paymentMethodInstance): bool
    {
        $isOpenInvoice = $this->isOpenInvoice($paymentMethodInstance);
        $requiresLineItemsConfig = boolval($paymentMethodInstance->getConfigData(self::CONFIG_FIELD_REQUIRES_LINE_ITEMS));

        return $isOpenInvoice || $requiresLineItemsConfig;
    }
}
