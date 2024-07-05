<?php

namespace Payever\Helper;

use Plenty\Modules\Payment\Models\Payment;

/**
 * Class StatusHelper
 */
class StatusHelper
{
    const STATUS_NEW = 'STATUS_NEW';
    const STATUS_IN_PROCESS = 'STATUS_IN_PROCESS';
    const STATUS_ACCEPTED = 'STATUS_ACCEPTED';
    const STATUS_PAID = 'STATUS_PAID';
    const STATUS_DECLINED = 'STATUS_DECLINED';
    const STATUS_REFUNDED = 'STATUS_REFUNDED';
    const STATUS_FAILED = 'STATUS_FAILED';
    const STATUS_CANCELLED = 'STATUS_CANCELLED';

    const PLENTY_ORDER_SUCCESS = 5;
    const PLENTY_ORDER_PROCESSING = 3;
    const PLENTY_ORDER_IN_PROCESS = 3.3;
    const PLENTY_ORDER_CANCELLED = 8;
    const PLENTY_ORDER_RETURN = 9;

    /**
     * Returns the plentymarkets payment status matching the given transaction state.
     *
     * @param string $state
     * @return int|null
     */
    public static function mapPaymentStatus(string $state)
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
     * @return int|float|null
     */
    public static function mapOrderStatus(string $status)
    {
        switch ($status) {
            case self::STATUS_PAID:
            case self::STATUS_ACCEPTED:
                return self::PLENTY_ORDER_SUCCESS;
            case self::STATUS_IN_PROCESS:
                return self::PLENTY_ORDER_IN_PROCESS;
            case self::STATUS_FAILED:
            case self::STATUS_CANCELLED:
            case self::STATUS_DECLINED:
                return self::PLENTY_ORDER_CANCELLED;
            case self::STATUS_REFUNDED:
                return self::PLENTY_ORDER_RETURN;
            case self::STATUS_NEW:
                return self::PLENTY_ORDER_PROCESSING;
            default:
                return null;
        }
    }

    /**
     * @param string $status
     * @return bool
     */
    public static function isSuccessfulPaymentStatus(string $status): bool
    {
        return in_array($status, [
            self::STATUS_PAID,
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROCESS,
        ]);
    }
}
