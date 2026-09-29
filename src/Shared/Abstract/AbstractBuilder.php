<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Shared\Abstract;


abstract class AbstractBuilder
{
    /**
     * 校验 DTO 并构造请求参数。
     *
     * @return array<string, mixed>
     */
    final public function build(AbstractPaymentDto $dto): array
    {
        $this->validate($dto);
        return $this->toArray($dto);
    }

    /** 校验当前版本和业务边界支持的 DTO 字段。 */
    abstract protected function validate(AbstractPaymentDto $dto): void;

    /**
     * 将已校验的 DTO 转换为微信支付请求结构。
     *
     * @return array<string, mixed>
     */
    abstract protected function toArray(AbstractPaymentDto $dto): array;
}
