<?php

namespace Payever\Services\Payment\Notification;

use Payever\Helper\PayeverHelper;
use Payever\Helper\PaymentActionManager;
use Payever\Services\Lock\StorageLock;
use Payever\Services\PayeverService;
use Payever\Services\Processor\CheckoutProcessor;
use Payever\Services\Processor\OrderProcessor;
use Payever\Traits\Logger;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Http\Request;

class NotificationRequestProcessor
{
    use Logger;

    const HEADER_SIGNATURE = 'X-PAYEVER-SIGNATURE';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var StorageLock
     */
    private $lock;

    /**
     * @var PayeverService
     */
    private $payeverService;

    /**
     * @var PayeverHelper
     */
    private $payeverHelper;

    /**
     * @var OrderProcessor
     */
    private $orderProcessor;

    /**
     * @var CheckoutProcessor
     */
    private $checkoutProcessor;

    /**
     * @var NotificationActionHandler
     */
    private NotificationActionHandler $notificationActionHandler;

    /**
     * @var PaymentActionManager
     */
    private PaymentActionManager $paymentActionManager;

    /**
     * @param Request $request
     * @param ConfigRepository $config
     * @param StorageLock $lock
     * @param PayeverService $payeverService
     * @param PayeverHelper $payeverHelper
     * @param OrderProcessor $orderProcessor
     * @param CheckoutProcessor $checkoutProcessor
     * @param NotificationActionHandler $notificationHandler
     * @param PaymentActionManager $paymentActionManager
     */
    public function __construct(
        Request $request,
        ConfigRepository $config,
        StorageLock $lock,
        PayeverService $payeverService,
        PayeverHelper $payeverHelper,
        OrderProcessor $orderProcessor,
        CheckoutProcessor $checkoutProcessor,
        NotificationActionHandler $notificationHandler,
        PaymentActionManager $paymentActionManager
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->lock = $lock;
        $this->payeverService = $payeverService;
        $this->payeverHelper = $payeverHelper;
        $this->orderProcessor = $orderProcessor;
        $this->checkoutProcessor = $checkoutProcessor;
        $this->notificationActionHandler = $notificationHandler;
        $this->paymentActionManager = $paymentActionManager;
    }

    /**
     * @return array
     */
    public function processNotification(): array
    {
        $result = 'error';
        try {
            $payload = $this->getRequestPayload();
            if (!$payload) {
                throw new \RuntimeException('Got empty notification payload', 20);
            }

            $payload = \json_decode($payload, true);
            $payeverPayment = $payload['data']['payment'] ?? [];

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.notificationDebug',
                'Notification debug',
                ['payeverPayment' => $payeverPayment]
            );

            $notificationTime = array_key_exists('created_at', $payload)
                ? date('Y-m-d H:i:s', strtotime($payload['created_at']))
                : false;

            $paymentId = $payeverPayment['id'] ?? null;
            if (!$payeverPayment || !$paymentId) {
                throw new \UnexpectedValueException('Notification entity is invalid', 21);
            }

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.processingPayeverStatus',
                'processing payever status',
                ['payeverStatus' => $payeverPayment['status']]
            );

            // Check if action was already processed before
            if (isset($payload['data']['action'])) {
                $this->checkPaymentAction($payload, $payeverPayment);
            }

            // Get or create order
            $orderId = $this->checkoutProcessor->processCheckout($paymentId, null, $notificationTime);

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.updatingPlentyPaymentForNotifications',
                'updating plenty payment for notifications',
                ['orderId' => $orderId]
            );

            // Handle capture/refund/cancel notification
            if ($this->payeverHelper->isLocked(PayeverHelper::ACTION_PREFIX . $paymentId)) {
                $this->payeverHelper->waitForUnlock(PayeverHelper::ACTION_PREFIX . $paymentId);
            }

            $this->notificationActionHandler->handleNotificationAction($payeverPayment, $orderId);

            if (isset($payload['data']['action'])) {
                $this->paymentActionManager->addAction(
                    $orderId,
                    $payload['data']['action']['unique_identifier'],
                    $payload['data']['action']['type'],
                    $payload['data']['action']['source'],
                    $payload['data']['action']['amount']
                );
            }

            $result = 'success';
            $message = 'Order was updated';
        } catch (\Exception $e) {
            $message = $e->getMessage();

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.notificationRequestProcessorException',
                'NotificationRequestProcessor exception: ' . $message,
                [
                    'exception' => $message,
                ]
            );
        }

        $data = ['result' => $result, 'message' => $message];

        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.notificationRequestProcessorReturnData',
            'NotificationRequestProcessor return data',
            $data
        );

        return $data;
    }

    /**
     * @return string
     */
    private function getRequestPayload(): string
    {
        $paymentId = $this->request->get('payment_id', '');
        $signature = $this->request->header(self::HEADER_SIGNATURE);
        $payload = $this->request->getContent();
        if ($signature) {
            $this->assertSignatureValid($paymentId, $signature);

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.notificationRequestProcessorSignatureMatches',
                'NotificationRequestProcessor signature matches: ' . $payload,
                [
                    'paymentId' => $paymentId,
                    'payload' => $payload,
                ]
            );
        } else {
            $rawData = !empty($payload) ? \json_decode($payload, true) : [];
            $payeverPayment = $this->payeverService->handlePayeverPayment($paymentId);

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.retrievingPaymentForNotifications',
                'retrieve payment for notifications',
                [
                    'payeverPayment' => $payeverPayment,
                ]
            );

            $payload = \json_encode([
                'created_at' => $rawData['created_at'] ?? null,
                'data' => [
                    'payment' => $payeverPayment,
                ],
            ]);
        }

        return $payload;
    }

    /**
     * @param array $payload
     * @param array $payeverPayment
     * @return void
     */
    private function checkPaymentAction(array $payload, array $payeverPayment)
    {
        $orderId = $this->orderProcessor->getPlentyOrderByPayeverPayment($payeverPayment);
        if ($orderId) {
            $processedNotice = $this->paymentActionManager->isActionExists(
                $orderId,
                $payload['data']['action']['unique_identifier'],
            );

            if ($processedNotice) {
                throw new \UnexpectedValueException('Notification rejected: notification already processed', 21);
            }
        }
    }

    /**
     * @param string $paymentId
     * @param string $signature
     */
    private function assertSignatureValid(string $paymentId, string $signature)
    {
        $expectedSignature = hash_hmac(
            'sha256',
            $this->config->get('Payever.clientId') . $paymentId,
            (string) $this->config->get('Payever.clientSecret')
        );
        if ($signature !== $expectedSignature) {
            throw new \UnexpectedValueException('Notification rejected: invalid signature');
        }
    }
}
