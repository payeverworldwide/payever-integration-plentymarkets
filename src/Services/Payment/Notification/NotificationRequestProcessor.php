<?php

namespace Payever\Services\Payment\Notification;

use Payever\Helper\PayeverHelper;
use Payever\Services\Lock\StorageLock;
use Payever\Services\PayeverService;
use Payever\Traits\Logger;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Log\Loggable;

class NotificationRequestProcessor
{
    use Logger;

    const NOTIFICATION_LOCK_SECONDS = 30;
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
     * @var NotificationActionHandler
     */
    private NotificationActionHandler $notificationActionHandler;

    /**
     * @param Request $request
     * @param ConfigRepository $config
     * @param StorageLock $lock
     * @param PayeverService $payeverService
     * @param PayeverHelper $payeverHelper
     * @param NotificationActionHandler $notificationHandler
     */
    public function __construct(
        Request $request,
        ConfigRepository $config,
        StorageLock $lock,
        PayeverService $payeverService,
        PayeverHelper $payeverHelper,
        NotificationActionHandler $notificationHandler
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->lock = $lock;
        $this->payeverService = $payeverService;
        $this->payeverHelper = $payeverHelper;
        $this->notificationActionHandler = $notificationHandler;
    }

    /**
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function processNotification(): array
    {
        $result = 'error';
        $paymentId = null;
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
            $this->lock->acquireLock($paymentId, static::NOTIFICATION_LOCK_SECONDS);
            $payeverStatus = $payeverPayment['status'] ?? null;

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.processingPayeverStatus',
                'processing payever status',
                ['payeverStatus' => $payeverStatus]
            );

            if (!empty($payeverPayment['reference']) && is_numeric($payeverPayment['reference'])) {
                $update = $this->payeverService->createAndUpdatePlentyPayment($payeverPayment);
            } else {
                $update = $this->payeverService->updatePlentyPayment(
                    $paymentId,
                    $payeverStatus,
                    $notificationTime
                );
            }
            $result = 'success';
            $message = 'Order was updated';
            if (!$update && $this->payeverHelper->isSuccessfulPaymentStatus($payeverPayment['status'])) {
                $this->payeverService->prepareBasket($payeverPayment['reference']);
                $orderData = $this->payeverService->placeOrder();
                $payeverPayment['reference'] = $orderData->order->id;
                $update = $this->payeverService->createAndUpdatePlentyPayment($payeverPayment);
                $message = 'Order was created';
            }

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.updatingPlentyPaymentForNotifications',
                'updating plenty payment for notifications',
                [
                    'update' => $update,
                    'message' => $message
                ]
            );

            // Handle capture/refund/cancel notification
            if (
                (isset($payeverPayment['captured_items']) && count($payeverPayment['captured_items']) > 0) ||
                isset($payeverPayment['refunded_items']) && count($payeverPayment['refunded_items']) > 0 ||
                isset($payeverPayment['capture_amount']) && $payeverPayment['capture_amount'] > 0  ||
                isset($payeverPayment['refund_amount']) && $payeverPayment['refund_amount'] > 0 ||
                isset($payeverPayment['cancel_amount']) && $payeverPayment['cancel_amount'] > 0
            ) {
                if ($this->payeverHelper->isLocked(PayeverHelper::ACTION_PREFIX . $paymentId)) {
                    $this->payeverHelper->waitForUnlock(PayeverHelper::ACTION_PREFIX . $paymentId);
                }

                $this->notificationActionHandler->handleNotificationAction($payeverPayment);
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();

            $this->log(
                'debug',
                __METHOD__,
                'Payever::debug.notificationRequestProcessorException',
                'NotificationRequestProcessor exception: ' . $message,
                [
                    'exception' => $message
                ]
            );
        } finally {
            $paymentId && $this->lock->releaseLock($paymentId);
        }
        $data = [
            'result' => $result,
            'message' => $message ?? null,
        ];

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
                    'payload' => $payload
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
                    'payeverPayment' => $payeverPayment
                ]
            );

            $notificationDateTime = is_array($rawData) && array_key_exists('created_at', $rawData)
                ? $rawData['created_at']
                : null;
            $payload = \json_encode([
                'created_at' => $notificationDateTime,
                'data' => [
                    'payment' => $payeverPayment,
                ],
            ]);
        }

        return $payload;
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
