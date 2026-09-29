<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Config;

use Qinii\WechatCore\Enum\ApplicationType;
use Qinii\WechatPayment\Exception\InvalidConfigException;

/** 商家转账复用普通商户凭证，转账配置与支付 DTO 保持独立。 */
final class TransferConfigResolver
{
    /**
     * 从当前账号组中解析商户付款到零钱所需的支付凭证。
     *
     * @param array<string, mixed> $accountConfig
     * @param string|null $applicationName 显式选择的收款人 OpenID 所属应用
     */
    public function resolve(
        array $accountConfig,
        ?string $applicationName = null,
    ): TransferConfig
    {
        $config = $accountConfig['payment'] ?? null;

        if (! is_array($config) || $config === []) {
            throw new InvalidConfigException(
                'Wechat transfer config [payment] not found.'
            );
        }

        $explicitApplication = $applicationName !== null;
        $applicationName ??= $config['default_application']
            ?? ApplicationType::OFFICIAL_ACCOUNT;

        if (! is_string($applicationName) || $applicationName === '') {
            throw new InvalidConfigException(
                'Wechat transfer config [default_application] must be a string.'
            );
        }

        if ($explicitApplication || ! isset($config['app_id']) || (string) $config['app_id'] === '') {
            $applicationConfig = $accountConfig[$applicationName] ?? null;

            if (! is_array($applicationConfig)) {
                throw new InvalidConfigException(
                    "Wechat application config [{$applicationName}] not found."
                );
            }

            $appId = $applicationConfig['app_id'] ?? null;

            if (! is_string($appId) || $appId === '') {
                throw new InvalidConfigException(
                    "Wechat application config [{$applicationName}.app_id] is required."
                );
            }

            $config['app_id'] = $appId;
        }

        $commonHttp = $accountConfig['http'] ?? [];
        $moduleHttp = $config['http'] ?? [];

        if (! is_array($commonHttp) || ! is_array($moduleHttp)) {
            throw new InvalidConfigException(
                'Wechat transfer config [http] must be array.'
            );
        }

        $config['http'] = array_replace_recursive($commonHttp, $moduleHttp);

        return new TransferConfig($config);
    }
}
