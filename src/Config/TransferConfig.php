<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Config;

use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\InvalidConfigException;

/** Merchant credentials used by the transfer API. */
final class TransferConfig extends AbstractWechatPayConfig
{
    /** 创建商户付款到零钱配置，目前仅支持 API v3。 */
    public function __construct(array $config)
    {
        $config['version'] ??= ApiVersion::V3;

        parent::__construct($config);

        $this->version = (string) $config['version'];

        if ($this->version !== ApiVersion::V3) {
            throw new InvalidConfigException(
                'Wechat transfer config [version] must be v3.'
            );
        }

        $this->validateV3();
    }
}
