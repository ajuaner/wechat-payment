<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Factory;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Refund\V2\Builder as V2Builder;
use Qinii\WechatPayment\Refund\V2\Client as V2Client;
use Qinii\WechatPayment\Refund\V3\Builder as V3Builder;
use Qinii\WechatPayment\Refund\V3\Client as V3Client;

final class RefundFactory
{
    /** 根据支付配置版本创建退款客户端。 */
    public static function refund(
        AbstractWechatPayConfig $config,
        ApplicationContract $application,
    ): V2Client|V3Client {
        return match ((new PaymentDriverResolver())->resolve($config)) {
            ApiVersion::V2 => new V2Client(
                $application,
                new V2Builder($config),
                $config,
            ),
            ApiVersion::V3 => new V3Client(
                $application,
                new V3Builder($config),
                $config,
            ),
            default => throw new UnsupportedModeException(
                "Unsupported refund version: {$config->version}"
            ),
        };
    }

    /** 禁止实例化静态工厂。 */
    private function __construct()
    {
    }
}
