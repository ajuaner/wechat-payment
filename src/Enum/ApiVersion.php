<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Enum;

final class ApiVersion
{
    public const V2 = 'v2';
    public const V3 = 'v3';

    /**
     * 返回支持的微信支付 API 版本列表。
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::V2,
            self::V3,
        ];
    }

    /** 判断给定版本是否受当前扩展包支持。 */
    public static function supports(string $version): bool
    {
        return in_array($version, self::values(), true);
    }

    /** 禁止实例化版本常量类。 */
    private function __construct()
    {
    }
}
