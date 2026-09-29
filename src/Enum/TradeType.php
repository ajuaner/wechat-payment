<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Enum;

final class TradeType
{
    public const JSAPI = 'JSAPI';
    public const APP = 'APP';
    public const H5 = 'MWEB';
    public const NATIVE = 'NATIVE';
    public const MICROPAY = 'MICROPAY';

    /** 禁止实例化交易类型常量类。 */
    private function __construct()
    {
    }
}
