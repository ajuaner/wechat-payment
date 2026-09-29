<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Enum;

final class PayMode
{
    // 普通商户
    public const PAYMENT = 'payment';
    // 服务商商户
    public const PARTNER = 'partner';
    /**
     * 返回支持的普通商户和服务商模式列表。
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::PAYMENT,
            self::PARTNER,
        ];
    }

    /** 判断给定模式是否受当前扩展包支持。 */
    public static function supports(string $mode): bool
    {
        return in_array($mode, self::values(), true);
    }

    /** 禁止实例化支付模式常量类。 */
    private function __construct()
    {
    }
}
