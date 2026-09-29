<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Enum;

use Qinii\WechatPayment\Exception\UnsupportedModeException;

final class PayType
{
    public const OFFICIAL = 'official';
    public const APP = 'app';
    public const H5 = 'h5';
    public const NATIVE = 'native';
    public const MINI_PROGRAM = 'mini_program';
    public const MICROPAY = 'micropay';

    /** 根据支付类型返回客户端要调用的方法名。 */
    public static function paymentMethod(string $payType): string
    {
        return match ($payType) {
            self::OFFICIAL,
            self::MINI_PROGRAM => 'jsapi',
            self::APP => 'app',
            self::H5 => 'h5',
            self::NATIVE => 'native',
            self::MICROPAY => 'micropay',
            default => throw new UnsupportedModeException(
                "Unsupported pay type: {$payType}"
            ),
        };
    }

    /** 根据支付类型返回微信支付接口使用的 trade_type。 */
    public static function tradeType(string $payType): string
    {
        return match ($payType) {
            self::OFFICIAL,
            self::MINI_PROGRAM => TradeType::JSAPI,
            self::APP => TradeType::APP,
            self::H5 => TradeType::H5,
            self::NATIVE => TradeType::NATIVE,
            self::MICROPAY => TradeType::MICROPAY,
            default => throw new UnsupportedModeException(
                "Unsupported pay type: {$payType}"
            ),
        };
    }
}
