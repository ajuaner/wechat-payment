<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Factory;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use Qinii\WechatPayment\Config\TransferConfig;
use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Transfer\V3\Builder;
use Qinii\WechatPayment\Transfer\V3\Client;

final class TransferFactory
{
    /** 禁止实例化静态工厂。 */
    private function __construct()
    {
    }
    /** 创建 API v3 商户付款到零钱客户端。 */
    public static function transfer(
        TransferConfig $config,
        ApplicationContract $application,
    ): Client {
        $version = (new PaymentDriverResolver())->resolve($config);

        if ($version !== ApiVersion::V3) {
            throw new UnsupportedModeException(
                "Wechat merchant transfer only supports API v3, got {$version}."
            );
        }

        return new Client($config, $application, new Builder($config));
    }


}
