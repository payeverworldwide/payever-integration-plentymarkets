<?php

namespace Payever\Traits;

use Payever\Models\Log;
use Payever\Repositories\LogRepository;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Translation\Translator;
use Plenty\Log\Contracts\LoggerContract;

trait Logger
{
    use Loggable;

    /**
     * Log data.
     *
     * @param string $level
     * @param string $identifier
     * @param string $code
     * @param string $message
     * @param mixed|null $context
     * @param mixed|null $orderId
     * @return LoggerContract
     */
    public function log(
        $level,
        $identifier,
        $code,
        $message,
        $context = null,
        $orderId = null
    ) {
        $logger = $this->getLogger($identifier)
            ->setReferenceType('payeverLog');
        $this->applyPlentyLog($logger, $level, $code, $context);

        /** @var Translator $translator */
        $translator = pluginApp(Translator::class);
        $message = sprintf(
            '%s: %s',
            $translator ? $translator->trans($code) : $code,
            $message
        );

        /** @var LogRepository $logRepository */
        $logRepository = pluginApp(LogRepository::class);
        if ($logRepository) {
            // @codeCoverageIgnoreStart
            /** @var Log $log */
            $log = $logRepository->create();
            $log->level = $level;
            $log->message = $message;
            $log->data = is_array($context) ? $context : [$context];

            if ($orderId) {
                $log->orderId = $orderId;
            } else {
                $log->orderId = 0;
            }

            try {
                $logRepository->persist($log);
            } catch (\Exception $exception) {
                // Silence is golden
            }
            // @codeCoverageIgnoreEnd
        }

        return $logger;
    }

    /**
     * @param LoggerContract $logger
     * @param string $level
     * @param string $code
     * @param mixed|null $context
     */
    protected function applyPlentyLog($logger, $level, $code, $context)
    {
        switch ($level) {
            default:
            case 'debug':
                $logger->debug($code, $context);
                break;
            case 'info':
                $logger->info($code, $context);
                break;
            case 'critical':
                $logger->critical($code, $context);
                break;
        }
    }
}
