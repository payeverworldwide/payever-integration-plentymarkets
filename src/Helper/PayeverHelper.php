<?php //strict

namespace Payever\Helper;

use Payever\Methods\PaymillcreditcardPaymentMethod;
use Payever\Methods\PaymilldirectdebitPaymentMethod;
use Payever\Methods\PaypalPaymentMethod;
use Payever\Methods\SantanderinstdkPaymentMethod;
use Payever\Methods\SantanderinstnoPaymentMethod;
use Payever\Methods\SantanderinstsePaymentMethod;
use Payever\Methods\SantanderinvoicedePaymentMethod;
use Payever\Methods\SantanderinvoicenoPaymentMethod;
use Payever\Methods\SantanderfactoringdePaymentMethod;
use Payever\Methods\SantanderPaymentMethod;
use Payever\Methods\SofortPaymentMethod;
use Payever\Methods\StripePaymentMethod;
use Payever\Methods\PayexfakturaPaymentMethod;
use Payever\Methods\PayexcreditcardPaymentMethod;
use Payever\Services\PayeverSdkService;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Plugin\Application;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Helper\Services\WebstoreHelper;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Plugin\Storage\Contracts\StorageRepositoryContract;
use Plenty\Plugin\Log\Loggable;
use Plenty\Modules\Order\Models\OrderType;

/**
 * Class PayeverHelper
 *
 * @package Payever\Helper
 */
class PayeverHelper
{
    use Loggable;

    const PLUGIN_KEY = 'plenty_payever';

    const LOCKFILE_TIME_LOCK  = 60; //sec
    const LOCKFILE_TIME_SLEEP = 1; //sec
    const LOCKFILE_MAX_LIFETIME = 120; //sec

    private $app;
    private $webstoreHelper;
    private $paymentMethodRepository;
    private $config;
    private $paymentOrderRelationRepo;
    private $paymentProperty;
    private $paymentRepo;
    private $payment;
    private $orderRepo;
    private $statusMap;
    private $sdkService;
    private $addressRepo;

    /** @var  Checkout */
    private $checkout;

    /**
     * @var StorageRepositoryContract $storageRepository
     */
    private $storageRepository;

    private $methodsMetaData = [
        'STRIPE' => [
            'class' => StripePaymentMethod::class,
            'name' => 'Stripe',
        ],
        'PAYMILL_DIRECTDEBIT' => [
            'class' => PaymilldirectdebitPaymentMethod::class,
            'name' => 'Direct Debit',
        ],
        'PAYPAL' => [
            'class' => PaypalPaymentMethod::class,
            'name' => 'PayPal',
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
    ];

    private $urlMap = [
        'process' => '/payment/payever/processCheckout?method=',
        'success' => '/payment/payever/checkoutSuccess?payment_id=--PAYMENT-ID--',
        'notice' => '/payment/payever/checkoutNotice?payment_id=--PAYMENT-ID--',
        'cancel' => '/payment/payever/checkoutCancel?payment_id=--PAYMENT-ID--',
        'failure' => '/payment/payever/checkoutFailure?payment_id=--PAYMENT-ID--',
        'iframe' => '/payment/payever/checkoutIframe',
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
     * PaymentHelper constructor.
     * @param Application $app
     * @param WebstoreHelper $webstoreHelper
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param PaymentRepositoryContract $paymentRepo
     * @param PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo
     * @param ConfigRepository $config
     * @param Payment $payment
     * @param PaymentProperty $paymentProperty
     * @param OrderRepositoryContract $orderRepo,
     * @param Checkout $checkout
     * @param PayeverSdkService $sdkService
     * @param AddressRepositoryContract $addressRepo
     * @param StorageRepositoryContract $storageRepository
     */
    public function __construct(
        Application $app,
        PaymentMethodRepositoryContract $paymentMethodRepository,
        PaymentRepositoryContract $paymentRepo,
        PaymentOrderRelationRepositoryContract $paymentOrderRelationRepo,
        ConfigRepository $config,
        Payment $payment,
        PaymentProperty $paymentProperty,
        OrderRepositoryContract $orderRepo,
        WebstoreHelper $webstoreHelper,
        Checkout $checkout,
        PayeverSdkService $sdkService,
        AddressRepositoryContract $addressRepo,
        StorageRepositoryContract $storageRepository
    ) {
        $this->app = $app;
        $this->webstoreHelper = $webstoreHelper;
        $this->config = $config;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentOrderRelationRepo = $paymentOrderRelationRepo;
        $this->paymentRepo = $paymentRepo;
        $this->paymentProperty = $paymentProperty;
        $this->orderRepo = $orderRepo;
        $this->payment = $payment;
        $this->statusMap = [];
        $this->checkout = $checkout;
        $this->sdkService = $sdkService;
        $this->addressRepo = $addressRepo;
        $this->storageRepository = $storageRepository;
    }

    /**
     * @return array
     */
    public function getMethodsMetaData():array
    {
        return $this->methodsMetaData;
    }

    /**
     * @return array
     */
    public function getMopKeyToIdMap():array
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
     * @param $type
     * @return string
     */
    private function getUrl(string $type):string
    {
        $webstoreConfig = $this->webstoreHelper->getCurrentWebstoreConfiguration();
        if (is_null($webstoreConfig)) {
            return 'error';
        }
        $domain = $webstoreConfig->domainSsl;

        return $domain.$this->urlMap[$type];
    }

    /**
     * @return string
     */
    public function getProcessURL(string $method):string
    {
        $result = $this->getUrl('process');

        return $result . ($result != 'error' ? $method : '');
    }

    /**
     * @return string
     */
    public function getSuccessURL():string
    {
        return $this->getUrl('success');
    }

    /**
     * @return string
     */
    public function getNoticeURL():string
    {
        return $this->getUrl('notice');
    }

    /**
     * @return string
     */
    public function getCancelURL():string
    {
        return $this->getUrl('cancel');
    }

    /**
     * @return string
     */
    public function getFailureURL():string
    {
        return $this->getUrl('failure');
    }

    /**
     * @return string
     */
    public function getIframeURL():string
    {
        return $this->getUrl('iframe');
    }

    /**
     * Returns the payever payment method's id.
     *
     * @param number $paymentMethodId
     * @return string
     */
    public function isPayeverPaymentMopId(int $mopId): bool
    {
        return in_array($mopId, $this->getMopKeyToIdMap());
    }

    /**
     * @param array $payeverPayment
     * @param int $mopId
     * @return Payment
     */
    public function createPlentyPayment(array $payeverPayment, int $mopId):Payment
    {
        /** @var Payment $payment */
        $payment = pluginApp(Payment::class);
        $payment->mopId = (int)$mopId;
        $payment->transactionType = Payment::TRANSACTION_TYPE_BOOKED_POSTING;
        $payment->status = $this->mapStatus($payeverPayment['status']);
        $payment->currency = $payeverPayment['currency'];
        $payment->amount = $payeverPayment['amount'];
        $payment->receivedAt = date("Y-m-d H:i:s", strtotime($payeverPayment['entryDate']));
        $paymentProperty = [];
        $paymentProperty[] = $this->getPaymentProperty(
            PaymentProperty::TYPE_BOOKING_TEXT,
            'TransactionID: '.(string)$payeverPayment['transactionId']
        );
        $paymentProperty[] = $this->getPaymentProperty(
            PaymentProperty::TYPE_TRANSACTION_ID,
            $payeverPayment['transactionId']
        );
        $paymentProperty[] = $this->getPaymentProperty(
            PaymentProperty::TYPE_REFERENCE_ID,
            $payeverPayment['reference']
        );

        $paymentProperty[] = $this->getPaymentProperty(PaymentProperty::TYPE_ORIGIN, Payment::ORIGIN_PLUGIN);
        $paymentProperty[] = $this->getPaymentProperty(PaymentProperty::TYPE_PAYMENT_TEXT, $payeverPayment['reference']);
        $payment->properties = $paymentProperty;
        //$payment->regenerateHash = true;
        $payment = $this->paymentRepo->createPayment($payment);

        return $payment;
    }

    /**
     * @param string $transactionId
     * @param string $status
     * @param bool|Date $notificationTime
     * @return bool|Payment
     */
    public function updatePlentyPayment(string $transactionId, string $status, $notificationTime = false)
    {
        $payments = $this->paymentRepo->getPaymentsByPropertyTypeAndValue(
            PaymentProperty::TYPE_TRANSACTION_ID,
            $transactionId
        );

        $state = $this->mapStatus($status);
        foreach ($payments as $payment) {
            if ($notificationTime) {
                if (strtotime($notificationTime) > strtotime($payment->receivedAt)) {
                    $payment->receivedAt = $notificationTime;
                } else {
                    return false;
                }
            }

            /* @var Payment $payment */
            if ($payment->status != $state) {
                $payment->status = $state;
            }

            $this->paymentRepo->updatePayment($payment);

            return $payment;
        }

        return false;
    }

    /**
     * Returns a PaymentProperty with the given params
     *
     * @param $typeId
     * @param $value
     * @return PaymentProperty
     */
    private function getPaymentProperty($typeId, $value):PaymentProperty
    {
        /** @var PaymentProperty $paymentProperty */
        $paymentProperty = pluginApp(PaymentProperty::class);
        $paymentProperty->typeId = $typeId;
        $paymentProperty->value = (string)$value;

        return $paymentProperty;
    }

    /**
     * @param Payment $payment
     * @param int $orderId
     */
    public function assignPlentyPaymentToPlentyOrder(Payment $payment, int $orderId)
    {
        // Get the order by the given order ID
        $order = $this->orderRepo->findOrderById($orderId);
        // Check whether the order truly exists in plentymarkets
        if (!is_null($order) && $order instanceof Order) {
            // Assign the given payment to the given order
            $this->paymentOrderRelationRepo->createOrderRelation($payment, $order);
        }

        $transactionId = $this->getPaymentPropertyValue($payment, PaymentProperty::TYPE_TRANSACTION_ID);
        $this->getLogger(__METHOD__)->debug('Payever::debug.assignPlentyPaymentToPlentyOrder', 'Transaction ' . $transactionId . ' was assigned to the order #' . $orderId);
    }

    /**
     * @param Payment $payment
     * @param int $propertyTypeConstant
     *
     * @return string
     */
    public function getPaymentPropertyValue($payment, $propertyTypeConstant)
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
                return (string) $property->value;
            }
        }
        return '';
    }

    /**
     * @param string $methodCode
     * @param Basket $bakset
     *
     * @return bool
     */
    public function isPaymentMethodHidden($methodCode, Basket $bakset)
    {
        return $this->isBasketAddressesDifferent($bakset)
            ? in_array($methodCode, $this->hideOnDifferentAddressMethods)
            : false;
    }

    /**
     * @param Basket $basket
     *
     * @return bool
     */
    public function isBasketAddressesDifferent(Basket $basket)
    {
        static $result = null;

        if ($result === null) {
            $result = false;

            if (
                !$basket->customerShippingAddressId
                || !$basket->customerInvoiceAddressId
            ) {
                return $result;
            }

            $result = $basket->customerInvoiceAddressId !== $basket->customerShippingAddressId;
        }

        return $result;
    }

    /**
     * Returns the plentymarkets payment status matching the given transaction state.
     *
     * @param string $state
     * @return number
     */
    public function mapStatus(string $state)
    {
        switch ($state) {
            case 'STATUS_PAID':
                return Payment::STATUS_CAPTURED;
            case 'STATUS_ACCEPTED':
                return Payment::STATUS_APPROVED;
            case 'STATUS_IN_PROCESS':
                return Payment::STATUS_AWAITING_APPROVAL;
            case 'STATUS_FAILED':
                return Payment::STATUS_CANCELED;
            case 'STATUS_CANCELLED':
                return Payment::STATUS_CANCELED;
            case 'STATUS_REFUNDED':
                return Payment::STATUS_REFUNDED;
            case 'STATUS_DECLINED':
                return Payment::STATUS_REFUSED;
            case 'STATUS_NEW':
                return Payment::STATUS_AWAITING_RENEWAL;
        }
    }

    public function isSuccessfulPaymentStatus(string $status): bool
    {
        return in_array($status, [
            'STATUS_PAID',
            'STATUS_ACCEPTED',
            'STATUS_IN_PROCESS',
        ]);
    }

    protected function getLockFileName($paymentId)
    {
        return $paymentId . '.lock';
    }

    public function lockAndBlock($paymentId)
    {
        $fileName = $this->getLockFileName($paymentId);
        $this->storageRepository->uploadObject('Payever', $fileName, '');
        $this->getLogger(__METHOD__)->debug('Payever::debug.lockAndBlock', $paymentId);
    }

    public function isLocked($paymentId)
    {
        $fileName = $this->getLockFileName($paymentId);

        return $this->storageRepository->doesObjectExist('Payever', $fileName);
    }

    public function unlock($paymentId)
    {
        $fileName = $this->getLockFileName($paymentId);
        $this->storageRepository->deleteObject('Payever', $fileName);
        $this->getLogger(__METHOD__)->debug('Payever::debug.unlock', $paymentId);
    }

    public function waitForUnlock($paymentId)
    {
        $this->getLogger(__METHOD__)->debug('Payever::debug.waitForUnlock', "start $paymentId");

        $waitingTime = 0;
        while ($this->isLocked($paymentId) && $waitingTime <= self::LOCKFILE_TIME_LOCK) {
            $waitingTime += self::LOCKFILE_TIME_SLEEP;
            sleep(self::LOCKFILE_TIME_SLEEP);
        }

        $this->getLogger(__METHOD__)->debug('Payever::debug.waitForUnlock', "finish $paymentId");
    }

    /**
     * @param $eventTriggered
     * @return bool
     */
    public function getOrderIdByEvent($eventTriggered)
    {
        $orderId = false;
        /** @var Order $order */
        $order = $eventTriggered->getOrder();
        // only sales orders and credit notes are allowed order types to ship
        switch ($order->typeId) {
            case OrderType::TYPE_SALES_ORDER:
                $orderId = $order->id;
                break;
            case OrderType::TYPE_CREDIT_NOTE:
                $originOrders = $order->originOrders;
                if (! $originOrders->isEmpty() && $originOrders->count() > 0) {
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
     * @param $transaction
     * @param string $typeTransaction
     * @return bool|mixed
     */
    public function isAllowedTransaction($transaction, $typeTransaction = 'cancel')
    {
        $result = isset($transaction['result']) ? $transaction['result'] : [];

        if (!empty($result['actions']) && is_object($result['actions'])) {
            $result['actions'] = (array) $result['actions'];
        }

        if (!empty($result['actions']) && is_array($result['actions'])) {
            foreach ($result['actions'] as $action) {
                $action = (array) $action;
                if ($action['action'] == $typeTransaction) {
                    return $action['enabled'];
                }
            }
        }

        return false;
    }
}
