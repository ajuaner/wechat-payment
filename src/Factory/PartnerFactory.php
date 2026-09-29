<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Factory;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Partner\V2\Builder as V2Builder;
use Qinii\WechatPayment\Partner\V2\Client as V2Client;
use Qinii\WechatPayment\Partner\V3\Builder as V3Builder;
use Qinii\WechatPayment\Partner\V3\Client as V3Client;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentClient;

final class PartnerFactory
{
    /** 根据服务商配置的 API 版本创建服务商支付客户端。 */
    public static function partner(
        PartnerConfig $config,
        ApplicationContract $application,
    ): AbstractPaymentClient {
        $version = (new PaymentDriverResolver())->resolve($config);

        return match ($version) {
            ApiVersion::V2 => new V2Client(
                $config,
                $application,
                new V2Builder($config),
            ),
            ApiVersion::V3 => new V3Client(
                $config,
                $application,
                new V3Builder($config),
            ),
            default => throw new UnsupportedModeException(
                "Unsupported partner payment version: {$version}"
            ),
        };
    }

    /** 禁止实例化静态工厂。 */
    private function __construct()
    {
    }
}
