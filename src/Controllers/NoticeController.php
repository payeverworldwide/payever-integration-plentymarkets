<?php

namespace Payever\Controllers;

use Payever\Services\Payment\Notification\NotificationRequestProcessor;
use Payever\Traits\Logger;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Class NoticeController
 */
class NoticeController extends Controller
{
    use Logger;

    /**
     * @param NotificationRequestProcessor $notificationRequestProcessor
     *
     * @return SymfonyResponse
     */
    public function checkoutNotice(NotificationRequestProcessor $notificationRequestProcessor): SymfonyResponse
    {
        $this->log(
            'debug',
            __METHOD__,
            'Payever::debug.noticeUrlWasCalled',
            'Notice Url Was Called',
            []
        );

        $response = pluginApp(Response::class);
        $result = $notificationRequestProcessor->processNotification();

        return $response->json($result);
    }
}
