<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Factory;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Merchant\V3\Builder;
use Qinii\WechatPayment\Merchant\V3\Client;

/** 子商户管理客户端工厂。 */
final class MerchantFactory
{
    /** 根据服务商配置创建 API v3 子商户管理客户端。 */
    public static function merchant(
        PartnerConfig $config,
        ApplicationContract $application,
    ): Client {
        if ((new PaymentDriverResolver())->resolve($config) !== ApiVersion::V3) {
            throw new UnsupportedModeException(
                'Wechat merchant applyment only supports API v3.'
            );
        }

        return new Client($config, $application, new Builder());
    }

    /** 禁止实例化静态工厂。 */
    private function __construct()
    {
    }
}
