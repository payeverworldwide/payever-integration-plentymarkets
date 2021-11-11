<?php

namespace Payever\Helper;

use Payever\Methods\InstantPaymentMethod;
use Payever\Methods\OpenbankPaymentMethod;
use Payever\Methods\PayexcreditcardPaymentMethod;
use Payever\Methods\PayexfakturaPaymentMethod;
use Payever\Methods\PaymillcreditcardPaymentMethod;
use Payever\Methods\PaymilldirectdebitPaymentMethod;
use Payever\Methods\PaypalPaymentMethod;
use Payever\Methods\SantanderfactoringdePaymentMethod;
use Payever\Methods\SantanderinstatPaymentMethod;
use Payever\Methods\SantanderinstdkPaymentMethod;
use Payever\Methods\SantanderinstnoPaymentMethod;
use Payever\Methods\SantanderinstsePaymentMethod;
use Payever\Methods\SantanderinvoicedePaymentMethod;
use Payever\Methods\SantanderinvoicenoPaymentMethod;
use Payever\Methods\SantanderPaymentMethod;
use Payever\Methods\SofortPaymentMethod;
use Payever\Methods\StripeDirectDebitPaymentMethod;
use Payever\Methods\StripePaymentMethod;
use Payever\Methods\SwedbankCreditCardPaymentMethod;
use Payever\Methods\SwedbankInvoicePaymentMethod;
use Payever\Repositories\PayeverConfigRepository;
use Payever\Services\Lock\StorageLock;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Helper\Services\WebstoreHelper;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderType;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Translation\Translator;
use Plenty\Plugin\Log\Loggable;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.UnusedPrivateField)
 */
class PayeverHelper
{
    use Loggable;

    const PLUGIN_KEY = 'plenty_payever';

    const STATUS_NEW = 'STATUS_NEW';
    const STATUS_IN_PROCESS = 'STATUS_IN_PROCESS';
    const STATUS_ACCEPTED = 'STATUS_ACCEPTED';
    const STATUS_PAID = 'STATUS_PAID';
    const STATUS_DECLINED = 'STATUS_DECLINED';
    const STATUS_REFUNDED = 'STATUS_REFUNDED';
    const STATUS_FAILED = 'STATUS_FAILED';
    const STATUS_CANCELLED = 'STATUS_CANCELLED';

    const COMMAND_TIMESTAMP_KEY = 'command_timestamp';
    const SANDBOX_URL_CONFIG_KEY = 'sandbox_url';
    const LIVE_URL_CONFIG_KEY = 'live_url';

    const PLENTY_ORDER_SUCCESS = 5;
    const PLENTY_ORDER_PROCESSING = 3;
    const PLENTY_ORDER_INPROCESS = 3.3;
    const PLENTY_ORDER_CANCELLED = 8;
    const PLENTY_ORDER_RETURN = 9;

    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var WebstoreHelper
     */
    private $webStoreHelper;

    /**
     * @var PayeverConfigRepository
     */
    private $payeverConfigRepository;

    /**
     * @var StorageLock
     */
    private $storageLock;

    /**
     * @var \Plenty\Plugin\Translation\Translator
     */
    private $translator;

    /**
     * @var string[][]
     */
    private $methodsMetaData = [
        'STRIPE' => [
            'class' => StripePaymentMethod::class,
            'name' => 'Stripe Credit Card',
        ],
        'STRIPE_DIRECTDEBIT' => [
            'class' => StripeDirectDebitPaymentMethod::class,
            'name' => 'Stripe Direct Debit',
        ],
        'PAYMILL_DIRECTDEBIT' => [
            'class' => PaymilldirectdebitPaymentMethod::class,
            'name' => 'Paymill Direct Debit',
        ],
        'PAYPAL' => [
            'class' => PaypalPaymentMethod::class,
            'name' => 'PayPal (payever)',
        ],
        'PAYMILL_CREDITCARD' => [
            'class' => PaymillcreditcardPaymentMethod::class,
            'name' => 'Paymill Credit Card',
        ],
        'SOFORT' => [
            'class' => SofortPaymentMethod::class,
            'name' => 'Sofort',
        ],
        'SANTANDER_INSTALLMENT_DK' => [
            'class' => SantanderinstdkPaymentMethod::class,
            'name' => 'Santander Ratenkauf Denmark',
        ],
        'SANTANDER_INSTALLMENT_NO' => [
            'class' => SantanderinstnoPaymentMethod::class,
            'name' => 'Santander Ratenkauf Nordics',
        ],
        'SANTANDER_INSTALLMENT_SE' => [
            'class' => SantanderinstsePaymentMethod::class,
            'name' => 'Santander Ratenkauf Sweden',
        ],
        'SANTANDER_INSTALLMENT_AT' => [
            'class' => SantanderinstatPaymentMethod::class,
            'name' => 'Santander Ratenkauf Austria',
        ],
        'SANTANDER_INSTALLMENT' => [
            'class' => SantanderPaymentMethod::class,
            'name' => 'Santander Installment',
        ],
        'SANTANDER_INVOICE_NO' => [
            'class' => SantanderinvoicenoPaymentMethod::class,
            'name' => 'Santander Invoice Nordics',
        ],
        'SANTANDER_INVOICE_DE' => [
            'class' => SantanderinvoicedePaymentMethod::class,
            'name' => 'Santander Invoice Germany',
        ],
        'SANTANDER_FACTORING_DE' => [
            'class' => SantanderfactoringdePaymentMethod::class,
            'name' => 'Santander Factoring',
        ],
        'PAYEX_FAKTURA' => [
            'class' => PayexfakturaPaymentMethod::class,
            'name' => 'PayEx Invoice',
        ],
        'PAYEX_CREDITCARD' => [
            'class' => PayexcreditcardPaymentMethod::class,
            'name' => 'PayEx Credit Card',
        ],
        'INSTANT_PAYMENT' => [
            'class' => InstantPaymentMethod::class,
            'name' => 'Direct bank transfer',
        ],
        'SWEDBANK_CREDITCARD' => [
            'class' => SwedbankCreditCardPaymentMethod::class,
            'name' => 'Swedbank Credit Card',
        ],
        'SWEDBANK_INVOICE' => [
            'class' => SwedbankInvoicePaymentMethod::class,
            'name' => 'Swedbank Invoice',
        ],
        'OPENBANK' => [
            'class' => OpenbankPaymentMethod::class,
            'name' => 'Openbank',
        ]
    ];

    /**
     * @var string[]
     */
    private $urlMap = [
        'success' => '/payment/payever/checkoutSuccess?payment_id=--PAYMENT-ID--',
        'finish' => '/payment/payever/checkoutFinish',
        'notice' => '/payment/payever/checkoutNotice?payment_id=--PAYMENT-ID--',
        'cancel' => '/payment/payever/checkoutCancel?payment_id=--PAYMENT-ID--',
        'failure' => '/payment/payever/checkoutFailure?payment_id=--PAYMENT-ID--',
        'iframe' => '/payment/payever/checkoutIframe?method=',
        'command_endpoint' => '/payment/payever/executeCommand',
    ];

    /**
     * Methods we should hide if shipping and billing addresses is different
     *
     * @var array
     */
    private $hideOnDifferentAddressMethods = [
        'santander_invoice_de',
        'santander_factoring_de',
        'payex_faktura',
    ];

    /**
     * PayeverHelper constructor.
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param Translator $translator
     * @param WebstoreHelper $webStoreHelper
     * @param PayeverConfigRepository $payeverConfigRepository
     * @param StorageLock $storageLock
     */
    public function __construct(
        PaymentMethodRepositoryContract $paymentMethodRepository,
        Translator $translator,
        WebstoreHelper $webStoreHelper,
        PayeverConfigRepository $payeverConfigRepository,
        StorageLock $storageLock
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->translator = $translator;
        $this->webStoreHelper = $webStoreHelper;
        $this->payeverConfigRepository = $payeverConfigRepository;
        $this->storageLock = $storageLock;
    }

    /**
     * @return array
     */
    public function getMethodsMetaData(): array
    {
        return $this->methodsMetaData;
    }

    /**
     * Returns the payever payment method's id.
     *
     * @param number $paymentMethodId
     * @return string
     */
    public function getPaymentMopId($paymentMethodId): string
    {
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('plenty_payever');
        if (!is_null($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethod) {
                if (strtolower($paymentMethod->paymentKey) == $paymentMethodId) {
                    return $paymentMethod->id;
                }
            }
        }

        return 'no_paymentmethod_found';
    }

    /**
     * @return array
     */
    public function getMopKeyToIdMap(): array
    {
        $result = [];
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('plenty_payever');
        if (!is_null($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethod) {
                $result[$paymentMethod->paymentKey] = $paymentMethod->id;
            }
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        $webStoreConfig = $this->webStoreHelper->getCurrentWebstoreConfiguration();
        if (is_null($webStoreConfig)) {
            return 'error';
        }

        return (string) $webStoreConfig->domainSsl;
    }

    /**
     * @param string $type
     * @return string
     */
    private function getUrl(string $type): string
    {
        return $this->getBaseUrl() . $this->urlMap[$type];
    }

    /**
     * @return string
     */
    public function getCommandEndpoint(): string
    {
        return $this->getUrl('command_endpoint');
    }

    /**
     * @return string
     */
    public function getSuccessURL(): string
    {
        return $this->getUrl('success');
    }

    /**
     * @param string $paymentId
     * @return string
     */
    public function buildSuccessURL(string $paymentId): string
    {
        $baseSuccessUrl = $this->getUrl('success');

        return str_replace('--PAYMENT-ID--', $paymentId, $baseSuccessUrl);
    }

    /**
     * @return string
     */
    public function getFinishURL(): string
    {
        return $this->getUrl('finish');
    }

    /**
     * @return string
     */
    public function getNoticeURL(): string
    {
        return $this->getUrl('notice');
    }

    /**
     * @return string
     */
    public function getCancelURL(): string
    {
        return $this->getUrl('cancel');
    }

    /**
     * @return string
     */
    public function getFailureURL(): string
    {
        return $this->getUrl('failure');
    }

    /**
     * @param string $method
     * @return string
     */
    public function getIframeURL(string $method): string
    {
        $result = $this->getUrl('iframe');

        return $result . ($result != 'error' ? $method : '');
    }

    /**
     * @param $textId
     *
     * @return mixed
     */
    public function translate($textId): string
    {
        return $this->translator->trans($textId);
    }

    /**
     * Returns the payever payment method's id.
     *
     * @param int $mopId
     * @return bool
     */
    public function isPayeverPaymentMopId(int $mopId): bool
    {
        return in_array($mopId, $this->getMopKeyToIdMap());
    }

    /**
     * Returns a PaymentProperty with the given params
     *
     * @param int $typeId
     * @param mixed $value
     * @return PaymentProperty
     */
    public function getPaymentProperty(int $typeId, $value): PaymentProperty
    {
        /** @var PaymentProperty $paymentProperty */
        $paymentProperty = pluginApp(PaymentProperty::class);
        $paymentProperty->typeId = $typeId;
        $paymentProperty->value = (string)$value;

        return $paymentProperty;
    }

    /**
     * @param Payment $payment
     * @param int $propertyTypeConstant
     *
     * @return string
     */
    public function getPaymentPropertyValue(Payment $payment, int $propertyTypeConstant): string
    {
        $properties = $payment->properties;
        if (!$properties) {
            return '';
        }
        /* @var $property PaymentProperty */
        foreach ($properties as $property) {
            if (!($property instanceof PaymentProperty)) {
                continue;
            }
            if ($property->typeId == $propertyTypeConstant) {
                return (string)$property->value;
            }
        }

        return '';
    }

    /**
     * Returns the plentymarkets payment status matching the given transaction state.
     *
     * @param string $state
     * @return int|null
     */
    public function mapStatus(string $state)
    {
        switch ($state) {
            case self::STATUS_PAID:
                return Payment::STATUS_CAPTURED;
            case self::STATUS_ACCEPTED:
                return Payment::STATUS_APPROVED;
            case self::STATUS_IN_PROCESS:
                return Payment::STATUS_AWAITING_APPROVAL;
            case self::STATUS_FAILED:
            case self::STATUS_CANCELLED:
                return Payment::STATUS_CANCELED;
            case self::STATUS_REFUNDED:
                return Payment::STATUS_REFUNDED;
            case self::STATUS_DECLINED:
                return Payment::STATUS_REFUSED;
            case self::STATUS_NEW:
                return Payment::STATUS_AWAITING_RENEWAL;
        }
    }

    /**
     * Returns the plentymarkets order status
     *
     * @param string $status
     *
     * @return int|null
     */
    public function mapOrderStatus(string $status)
    {
        switch ($status) {
            case self::STATUS_PAID:
            case self::STATUS_ACCEPTED:
                return self::PLENTY_ORDER_SUCCESS;
            case self::STATUS_IN_PROCESS:
                return self::PLENTY_ORDER_INPROCESS;
            case self::STATUS_FAILED:
            case self::STATUS_CANCELLED:
            case self::STATUS_DECLINED:
                return self::PLENTY_ORDER_CANCELLED;
            case self::STATUS_REFUNDED:
                return self::PLENTY_ORDER_RETURN;
            case self::STATUS_NEW:
                return self::PLENTY_ORDER_PROCESSING;
        }
    }

    /**
     * @param string $status
     * @return bool
     */
    public function isSuccessfulPaymentStatus(string $status): bool
    {
        return in_array($status, [
            self::STATUS_PAID,
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROCESS,
        ]);
    }

    /**
     * @param mixed $paymentId
     * @return string
     */
    protected function getLockFileName($paymentId): string
    {
        return $paymentId . '.lock';
    }

    /**
     * @param string $paymentId
     */
    public function lockAndBlock(string $paymentId)
    {
        $this->storageLock->lock($this->storageLock->getLockName($paymentId));
    }

    /**
     * @param string $paymentId
     * @param string|null $fetchDest
     */
    public function acquireLock(string $paymentId, $fetchDest = null)
    {
        /**
         * Randomize waiting time before lock to assure no double-lock cases
         */
        $wait = rand(0, 3);
        if ('iframe' === $fetchDest) {
            // make second request wait to not duplicate order
            $wait = rand(3, 6);
        }
        sleep($wait);
        $this->storageLock->acquireLock($paymentId, 15);
    }

    /**
     * @param string $paymentId
     * @return bool
     */
    public function isLocked(string $paymentId): bool
    {
        return $this->storageLock->isLocked($this->storageLock->getLockName($paymentId));
    }

    /**
     * @param string $paymentId
     */
    public function unlock(string $paymentId)
    {
        $this->storageLock->unlock($this->storageLock->getLockName($paymentId));
    }

    /**
     * @param string $paymentId
     */
    public function waitForUnlock(string $paymentId)
    {
        $this->storageLock->waitForUnlock($this->storageLock->getLockName($paymentId));
    }

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @return mixed
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getOrderIdByEvent(EventProceduresTriggered $eventTriggered)
    {
        $orderId = false;
        $order = $eventTriggered->getOrder();
        // only sales orders and credit notes are allowed order types to ship
        switch ($order->typeId) {
            case OrderType::TYPE_SALES_ORDER:
                $orderId = $order->id;
                break;
            case OrderType::TYPE_CREDIT_NOTE:
                $originOrders = $order->originOrders;
                if (!$originOrders->isEmpty() && $originOrders->count() > 0) {
                    $originOrder = $originOrders->first();
                    if ($originOrder instanceof Order) {
                        if ($originOrder->typeId == 1) {
                            $orderId = $originOrder->id;
                        } else {
                            $originOriginOrders = $originOrder->originOrders;
                            if (is_array($originOriginOrders) && count($originOriginOrders) > 0) {
                                $originOriginOrder = $originOriginOrders->first();
                                if ($originOriginOrder instanceof Order) {
                                    $orderId = $originOriginOrder->id;
                                }
                            }
                        }
                    }
                }
                break;
        }

        return $orderId;
    }

    /**
     * @param array $transaction
     * @param string $typeTransaction
     * @return bool|mixed
     */
    public function isAllowedTransaction(array $transaction, string $typeTransaction = 'cancel')
    {
        $result = isset($transaction['result']) ? $transaction['result'] : [];

        if (!empty($result['actions']) && is_object($result['actions'])) {
            $result['actions'] = (array)$result['actions'];
        }

        if (!empty($result['actions']) && is_array($result['actions'])) {
            foreach ($result['actions'] as $action) {
                $action = (array)$action;
                if ($action['action'] == $typeTransaction) {
                    return $action['enabled'];
                }
            }
        }

        return false;
    }

    /**
     * Returns custom sandbox url
     *
     * @return string|null
     */
    public function getCustomSandboxUrl()
    {
        return $this->payeverConfigRepository->get(self::SANDBOX_URL_CONFIG_KEY);
    }

    /**
     * Returns custom live url
     *
     * @return string|null
     */
    public function getCustomLiveUrl()
    {
        return $this->payeverConfigRepository->get(self::LIVE_URL_CONFIG_KEY);
    }

    /**
     * Returns command timestamt
     *
     * @return string|null
     */
    public function getCommandTimestamp()
    {
        return $this->payeverConfigRepository->get(self::COMMAND_TIMESTAMP_KEY);
    }

    /**
     * Sets custom sandbox url
     *
     * @param mixed $customSandboxUrl
     */
    public function setCustomSandboxUrl($customSandboxUrl)
    {
        $this->payeverConfigRepository->set(self::SANDBOX_URL_CONFIG_KEY, $customSandboxUrl);
    }

    /**
     * Sets custom live url
     *
     * @param mixed $customLiveUrl
     */
    public function setCustomLiveUrl($customLiveUrl)
    {
        $this->payeverConfigRepository->set(self::LIVE_URL_CONFIG_KEY, $customLiveUrl);
    }

    /**
     * Sets command timestamp
     *
     * @param int $commandTimestamp
     */
    public function setCommandTimestamp(int $commandTimestamp)
    {
        $this->payeverConfigRepository->set(self::COMMAND_TIMESTAMP_KEY, $commandTimestamp);
    }

    /**
     * @param array $response
     * @return string
     */
    public function retrieveErrorMessageFromSdkResponse(array $response): string
    {
        $message = 'An unknown error occurred';
        if (!empty($response['error_description']) && is_string($response['error_description'])) {
            $message = $response['error_description'];
        }
        if (!empty($response['error_msg']) && is_string($response['error_msg'])) {
            $message = $response['error_msg'];
        }

        return $message;
    }
}
