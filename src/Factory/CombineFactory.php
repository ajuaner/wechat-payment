<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Factory;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use Qinii\WechatPayment\Combine\V3\Builder;
use Qinii\WechatPayment\Combine\V3\Client;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\UnsupportedModeException;

final class CombineFactory
{
    /** 创建 API v3 合单支付客户端。 */
    public static function combine(
        AbstractWechatPayConfig $config,
        ApplicationContract $application,
    ): Client {
        if ((new PaymentDriverResolver())->resolve($config) !== ApiVersion::V3) {
            throw new UnsupportedModeException(
                'Combined payment only supports API v3.'
            );
        }

        return new Client(
            $config,
            $application,
            new Builder($config),
        );
    }

    /** 禁止实例化静态工厂。 */
    private function __construct()
    {
    }
}
