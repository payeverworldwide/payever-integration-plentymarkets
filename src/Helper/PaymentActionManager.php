<?php

namespace Payever\Helper;

use Payever\Contracts\PaymentActionRepositoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.UnusedPrivateField)
 */
class PaymentActionManager
{
    use Loggable;

    /**
     * @var PaymentActionRepositoryContract
     */
    private PaymentActionRepositoryContract $paymentActionRepository;

    /**
     * PayeverHelper PaymentActionManager.
     *
     * @param PaymentActionRepositoryContract $paymentActionRepository
     */
    public function __construct(PaymentActionRepositoryContract $paymentActionRepository)
    {
        $this->paymentActionRepository = $paymentActionRepository;
    }

    /**
     * @param int $orderId
     * @param string $identifier
     * @return bool
     */
    public function isActionExists(int $orderId, string $identifier)
    {
        $paymentAction = $this->paymentActionRepository->getAction(
            $orderId,
            $identifier
        );

        return !!$paymentAction;
    }

    /**
     * @param int $orderId
     * @param string $identifier
     * @param string $actionType
     * @param string $actionSource
     * @param float|null $amount
     */
    public function addAction(
        int $orderId,
        string $identifier,
        string $actionType,
        string $actionSource,
        float $amount = null
    ) {
        $paymentAction = $this->paymentActionRepository->create();
        $paymentAction->amount = $amount;
        $paymentAction->uniqueIdentifier = $identifier;
        $paymentAction->orderId = $orderId;
        $paymentAction->actionType = $actionType;
        $paymentAction->actionSource = $actionSource;

        $this->paymentActionRepository->persist($paymentAction);
    }

    /**
     * @return string
     */
    public function generateIdentifier()
    {
        return uniqid();
    }
}
