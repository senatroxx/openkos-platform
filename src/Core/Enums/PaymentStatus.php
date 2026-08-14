<?php

namespace OpenKOS\Core\Enums;

/**
 * Provider-independent lifecycle states for one gateway payment attempt.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Settled = 'settled';
    case Failed = 'failed';
    case Expired = 'expired';
    case Canceled = 'canceled';
}
