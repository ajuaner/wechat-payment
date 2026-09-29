<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Shared\Abstract;

use Qinii\WechatPayment\Shared\Contract\PaymentRequest;
use Qinii\WechatPayment\Shared\Traits\Fillable;

abstract class AbstractPaymentDto implements PaymentRequest
{
    /** 提供数组填充和字段标准化能力。 */
    use Fillable;
}
