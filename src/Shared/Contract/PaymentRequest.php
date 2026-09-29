<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Shared\Contract;

interface PaymentRequest extends Validatable
{
    /** 返回普通商户或服务商支付模式。 */
    public function mode(): string;

    /** 返回 DTO 选择的支付类型。 */
    public function payType(): string;

    /** 返回客户端要调用的支付方法名。 */
    public function method(): string;

    /** 返回微信支付协议使用的交易类型。 */
    public function tradeType(): string;
}
