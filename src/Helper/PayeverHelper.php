<?php

namespace Payever\Helper;

use Payever\Methods\AllianzTradePayPaymentMethod;
use Payever\Methods\IdealPaymentMethod;
use Payever\Methods\InstantPaymentMethod;
use Payever\Methods\IvyPaymentMethod;
use Payever\Methods\PayexcreditcardPaymentMethod;
use Payever\Methods\PayexfakturaPaymentMethod;
use Payever\Methods\PaymillcreditcardPaymentMethod;
use Payever\Methods\PaymilldirectdebitPaymentMethod;
use Payever\Methods\PaypalPaymentMethod;
use Payever\Methods\SantanderPaymentMethod;
use Payever\Methods\SantanderfactoringdePaymentMethod;
use Payever\Methods\SantanderinstatPaymentMethod;
use Payever\Methods\SantanderinstbePaymentMethod;
use Payever\Methods\SantanderinstdkPaymentMethod;
use Payever\Methods\SantanderinstfiPaymentMethod;
use Payever\Methods\SantanderinstnoPaymentMethod;
use Payever\Methods\SantanderinstsePaymentMethod;
use Payever\Methods\SantanderinvoicedePaymentMethod;
use Payever\Methods\SantanderinvoicenoPaymentMethod;
use Payever\Methods\SofortPaymentMethod;
use Payever\Methods\StripeDirectDebitPaymentMethod;
use Payever\Methods\StripePaymentMethod;
use Payever\Methods\SwedbankCreditCardPaymentMethod;
use Payever\Methods\SwedbankInvoicePaymentMethod;
use Payever\Methods\ZiniaBnplDePaymentMethod;
use Payever\Methods\ZiniaBnplPaymentMethod;
use Payever\Methods\ZiniaInstallmentDePaymentMethod;
use Payever\Methods\ZiniaInstallmentPaymentMethod;
use Payever\Methods\ZiniaLendingDePaymentMethod;
use Payever\Methods\ZiniaSliceThreeDePaymentMethod;
use Payever\Methods\ZiniaSliceThreePaymentMethod;
use Payever\Repositories\PayeverConfigRepository;
use Payever\Services\Lock\StorageLock;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderType;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Translation\Translator;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.UnusedPrivateField)
 */
class PayeverHelper
{
    use Loggable;

    const PLUGIN_KEY = 'plenty_payever';
    const PLUGIN_CONFIG_PREFIX = 'Payever.';

    const COMMAND_TIMESTAMP_KEY = 'command_timestamp';
    const SANDBOX_URL_CONFIG_KEY = 'sandbox_url';
    const LIVE_URL_CONFIG_KEY = 'live_url';
    const API_VERSION_KEY  = 'api_version';

    const PLUGIN_VERSION = '3.3.0';

    const ACTION_PREFIX = "action.";

    const COMPANY_SEARCH_CONFIG_KEY  = 'is_company_search_on';

    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

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
        'SANTANDER_INSTALLMENT_FI' => [
            'class' => SantanderinstfiPaymentMethod::class,
            'name' => 'Santander Installment FI',
        ],
        'SANTANDER_INSTALLMENT_BE' => [
            'class' => SantanderinstbePaymentMethod::class,
            'name' => 'Santander Installment BE',
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
        'ZINIA_BNPL' => [
            'class' => ZiniaBnplPaymentMethod::class,
            'name' => 'Zinia BNPL',
        ],
        'ZINIA_BNPL_DE' => [
            'class' => ZiniaBnplDePaymentMethod::class,
            'name' => 'Zinia BNPL DE',
        ],
        'ZINIA_INSTALLMENT' => [
            'class' => ZiniaInstallmentPaymentMethod::class,
            'name' => 'Zinia Installment',
        ],
        'ZINIA_INSTALLMENT_DE' => [
            'class' => ZiniaInstallmentDePaymentMethod::class,
            'name' => 'Zinia Installment DE',
        ],
        'ZINIA_SLICE_THREE' => [
            'class' => ZiniaSliceThreePaymentMethod::class,
            'name' => 'Zinia Slice Three',
        ],
        'ZINIA_SLICE_THREE_DE' => [
            'class' => ZiniaSliceThreeDePaymentMethod::class,
            'name' => 'Zinia Slice Three DE',
        ],
        'ZINIA_LENDING_DE' => [
            'class' => ZiniaLendingDePaymentMethod::class,
            'name' => 'Zinia Lending',
        ],
        'IVY' => [
            'class' => IvyPaymentMethod::class,
            'name' => 'Ivy',
        ],
        'IDEAL' => [
            'class' => IdealPaymentMethod::class,
            'name' => 'Ideal',
        ],
        'ALLIANZ_TRADE_B2B_BNPL' => [
            'class' => AllianzTradePayPaymentMethod::class,
            'name' => 'Allianz Trade pay',
        ],
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
     * @param PayeverConfigRepository $payeverConfigRepository
     * @param StorageLock $storageLock
     */
    public function __construct(
        PaymentMethodRepositoryContract $paymentMethodRepository,
        Translator $translator,
        PayeverConfigRepository $payeverConfigRepository,
        StorageLock $storageLock
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->translator = $translator;
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
        if (!empty($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethod) {
                $result[$paymentMethod->paymentKey] = $paymentMethod->id;
            }
        }

        return $result;
    }

    /**
     * @param $textId
     *
     * @return string
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
        if (empty($properties)) {
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
     * Returns current plugin version
     *
     * @return string
     */
    public function getPluginVersion(): string
    {
        return self::PLUGIN_VERSION;
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
    public function acquireLock(string $paymentId, string $fetchDest = null)
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
                if ($order->originOrders->isEmpty() && !$order->originOrders->count()) {
                    return false;
                }
                $originOrder = $order->originOrders->first();
                if ($originOrder instanceof Order) {
                    if ($originOrder->typeId == 1) {
                        $orderId = $originOrder->id;
                    } elseif (
                        is_array($originOrder->originOrders) &&
                        count($originOrder->originOrders) > 0 &&
                        $originOrder->originOrders[0] instanceof Order
                    ) {
                        $orderId = $originOrder->originOrders[0]->id;
                    }
                }
                break;
            default:
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
        $result = $transaction['result'] ?? [];

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
     * Returns api version
     *
     * @return int|null
     */
    public function getApiVersion()
    {
        return (int)$this->payeverConfigRepository->get(self::API_VERSION_KEY);
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
        $this->payeverConfigRepository->set(self::COMMAND_TIMESTAMP_KEY, (string) $commandTimestamp);
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

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     *
     * @return false|mixed
     */
    public function getClientIP()
    {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        }

        if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        }

        if (isset($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        }

        if (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return false;
    }
}
