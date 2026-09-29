<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Factory;

use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Config\PaymentConfig;
use Qinii\WechatPayment\Enum\PayMode;
use Qinii\WechatPayment\Exception\UnsupportedModeException;

final class PaymentConfigFactory
{
    /**
     * 根据支付模式创建普通商户或服务商配置对象。
     *
     * @param array<string, mixed> $config
     */
    public static function make(
        string $mode,
        array $config,
    ): AbstractWechatPayConfig {
        return match ($mode) {
            PayMode::PAYMENT => new PaymentConfig($config),
            PayMode::PARTNER => new PartnerConfig($config),
            default => throw new UnsupportedModeException(
                "Unsupported payment mode: {$mode}"
            ),
        };
    }

    /** 禁止实例化静态工厂。 */
    private function __construct()
    {
    }
}
