<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Factory;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\ProfitSharing\ClientInterface;
use Qinii\WechatPayment\ProfitSharing\Ecommerce\Builder as EcommerceBuilder;
use Qinii\WechatPayment\ProfitSharing\Ecommerce\Client as EcommerceClient;
use Qinii\WechatPayment\ProfitSharing\V2\Builder as V2Builder;
use Qinii\WechatPayment\ProfitSharing\V2\Client as V2Client;
use Qinii\WechatPayment\ProfitSharing\V3\Builder as V3Builder;
use Qinii\WechatPayment\ProfitSharing\V3\Client as V3Client;

/** 创建普通商户、服务商和收付通分账客户端。 */
final class ProfitSharingFactory
{
    /** 根据支付配置版本创建普通商户或服务商分账客户端。 */
    public static function profitSharing(
        AbstractWechatPayConfig $config,
        ApplicationContract $application,
    ): ClientInterface {
        return match ((new PaymentDriverResolver())->resolve($config)) {
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
                "Unsupported profit-sharing version: {$config->version}"
            ),
        };
    }

    /** 创建平台收付通分账客户端。 */
    public static function ecommerce(
        PartnerConfig $config,
        ApplicationContract $application,
    ): ClientInterface {
        if ((new PaymentDriverResolver())->resolve($config) !== ApiVersion::V3) {
            throw new UnsupportedModeException(
                'Ecommerce profit sharing requires Wechat Pay API v3.'
            );
        }

        return new EcommerceClient(
            $config,
            $application,
            new EcommerceBuilder($config),
        );
    }

    /** 禁止实例化静态工厂。 */
    private function __construct()
    {
    }
}
