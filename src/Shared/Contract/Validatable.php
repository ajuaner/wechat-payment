<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Shared\Contract;

interface Validatable
{
    /** 校验当前请求对象的业务必填项和格式。 */
    public function validate(): void;
}
