<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Factory;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use Qinii\WechatPayment\Payment\V2\Builder as PaymentV2Builder;
use Qinii\WechatPayment\Payment\V2\Client as PaymentV2Client;
use Qinii\WechatPayment\Payment\V3\Builder as PaymentV3Builder;
use Qinii\WechatPayment\Payment\V3\Client as PaymentV3Client;
use Qinii\WechatPayment\Config\PaymentConfig;
use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentClient;

class PaymentFactory
{
    /** 根据普通商户配置的 API 版本创建支付客户端。 */
    public static function payment(
        PaymentConfig $config,
        ApplicationContract $application,
    ): AbstractPaymentClient
    {
        $version = (new PaymentDriverResolver())->resolve($config);

        return match ($version) {
            ApiVersion::V2 => new PaymentV2Client(
                $config,
                $application,
                new PaymentV2Builder(
                    $config->h5,
                    $config->notify_url,
                ),
            ),
            ApiVersion::V3 => new PaymentV3Client(
                $config,
                $application,
                new PaymentV3Builder(
                    $config->h5,
                    (string) $config->spbill_create_ip,
                ),
            ),
            default => throw new UnsupportedModeException(
                "Unsupported merchant payment version: {$version}"
            ),
        };
    }

}
