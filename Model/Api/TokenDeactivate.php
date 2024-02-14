<?php
namespace Adyen\Payment\Model\Api;

use Adyen\Client;
use Adyen\Payment\Api\TokenDeactivateInterface;
use Adyen\Service\ResourceModel\Checkout\Recurring;
use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;

class TokenDeactivate implements TokenDeactivateInterface
{
    protected $paymentTokenRepository;
    protected $recurringService;
    protected $paymentTokenManagement;

    public function __construct(
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        Recurring $recurringService,
        PaymentTokenManagement $paymentTokenManagement
    ) {
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->recurringService = $recurringService;
        $this->paymentTokenManagement = $paymentTokenManagement;
    }

    public function deactivateToken(string $paymentToken, string $paymentMethodCode): string
    {
        try {
            $paymentToken = $this->paymentTokenManagement->getByGatewayToken($paymentToken, $paymentMethodCode, 5);

            if (!$paymentToken instanceof PaymentTokenInterface) {
                throw new LocalizedException(__('Invalid token.'));
            }

            $paymentTokenId = $paymentToken->getEntityId();
            $paymentTokenRepository = $this->paymentTokenRepository->getById($paymentTokenId);
            $paymentTokenRepository->delete($paymentToken);

            return 'true';
        } catch (\Exception $e) {
            throw new LocalizedException(__('Error deactivating token: ' . $e->getMessage()));
        }
    }
}
