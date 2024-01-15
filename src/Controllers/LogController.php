<?php

namespace Payever\Controllers;

use Payever\Services\PayeverSdkService;
use Plenty\Plugin\Controller;
use Payever\Services\LogService;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;

class LogController extends Controller
{
    const HEADER_AUTHORIZATION = 'authorization';
    const PARAM_TOKEN = 'token';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var LogService
     */
    private $logService;

    /**
     * @var PayeverSdkService
     */
    private $sdkService;

    /**
     * @param Request $request
     * @param Response $response
     * @param LogService $logService
     * @param PayeverSdkService $sdkService
     */
    public function __construct(
        Request $request,
        LogService $logService,
        PayeverSdkService $sdkService
    ) {
        $this->request = $request;
        $this->response = pluginApp(Response::class);
        $this->logService = $logService;
        $this->sdkService = $sdkService;
    }

    /**
     * Show logs.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showLogs()
    {
        if (!$this->isAuthorized()) {
            return $this->response->json('Access denied.', 401);
        }

        $entries = $this->logService->getEntries(
            date('Y-m-d 00:00:00', strtotime('-90 days')),
            date('Y-m-d 23:59:59', strtotime('+1 day'))
        );

        return $this->response->json($entries);
    }

    /**
     * Download logs.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadLogs()
    {
        if (!$this->isAuthorized()) {
            return $this->response->make(
                'Access denied.',
                401,
                []
            );
        }

        $entries = $this->logService->getEntries(
            date('Y-m-d 00:00:00', strtotime('-90 days')),
            date('Y-m-d 23:59:59', strtotime('+1 day'))
        );

        $result = '';
        foreach ($entries as $entry) {
            $result .= sprintf(
                "[%s] payever.%s: %s %s []\n",
                $entry['created_at'],
                $entry['level'],
                $entry['message'],
                json_encode($entry['data'])
            );
        }

        return $this->response->make(
            $result,
            200,
            [
                'Pragma' => 'public',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Content-type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename=' . uniqid('payever_logs_') . '.log',
                'Content-Description' => 'Payever logs'
            ]
        );
    }

    /**
     * Check token validation.
     *
     * @return bool
     */
    private function isAuthorized(): bool
    {
        $token = $this->request->header(self::HEADER_AUTHORIZATION);
        if (empty($token)) {
            $token = $this->request->get(self::PARAM_TOKEN);
        }

        if (empty($token)) {
            return false;
        }

        return (bool) $this->sdkService->call(
            'validateToken',
            [
                'authorization' => $token
            ]
        );
    }
}
