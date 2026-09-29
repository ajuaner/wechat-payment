<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Factory;

use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\UnsupportedModeException;

final class PaymentDriverResolver
{
    /** 根据配置返回 API v2 或 v3 驱动标识。 */
    public function resolve(AbstractWechatPayConfig $config): string
    {
        if (! ApiVersion::supports($config->version)) {
            throw new UnsupportedModeException(
                "Unsupported payment API version: {$config->version}"
            );
        }

        return $config->version;
    }
}
