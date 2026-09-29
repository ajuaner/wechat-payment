<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Config;

use Qinii\WechatCore\Enum\ApplicationType;
use Qinii\WechatPayment\Combine\CombineDto;
use Qinii\WechatPayment\Enum\PayMode;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\InvalidConfigException;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Factory\PaymentConfigFactory;
use Qinii\WechatPayment\Shared\Contract\PaymentRequest;

/**
 * 从完整账号组中拼装当前支付请求的原始配置。
 * 具体字段校验由对应的配置对象负责。
 */
final class PaymentConfigResolver
{
    /**
     * 从完整账号组中拼装当前支付请求的原始配置。
     * @param array<string, mixed> $accountConfig
     */
    public function resolve(
        array $accountConfig,
        PaymentRequest $dto,
    ): AbstractWechatPayConfig {
        $mode = $dto->mode();

        if (
            $dto instanceof CombineDto
            && ! in_array($mode, [PayMode::PAYMENT, PayMode::PARTNER], true)
        ) {
            throw new UnsupportedModeException(
                'Combined payment mode must be payment or partner.'
            );
        }

        return $this->resolveMode($accountConfig, $mode, $dto->payType());
    }

    /**
     * 解析没有支付 DTO 的业务凭证，例如退款请求。
     * 当前账号组的默认应用会提供 app_id。
     *
     * @param array<string, mixed> $accountConfig
     */
    public function resolveMode(
        array $accountConfig,
        string $mode,
        ?string $payType = null,
    ): AbstractWechatPayConfig {
        if (! PayMode::supports($mode)) {
            throw new UnsupportedModeException(
                "Unsupported payment mode: {$mode}"
            );
        }

        $config = $accountConfig[$mode] ?? null;

        if (! is_array($config) || $config === []) {
            throw new InvalidConfigException(
                "Wechat payment config [{$mode}] not found."
            );
        }

        $applicationName = $payType === null
            ? $this->getDefaultApplication($config, 'refund')
            : $this->resolveApplicationName($payType, $config);

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

        // app_id 必须来自当前账号组，不允许跨账号组拼接。
        $config['app_id'] = $appId;
        $config['http'] = $this->mergeHttpConfig(
            $accountConfig['http'] ?? [],
            $config['http'] ?? [],
            $mode,
        );

        return PaymentConfigFactory::make($mode, $config);
    }

    /**
     * 合并账号组级和业务模块级 HTTP 配置。
     *
     * @param mixed $commonHttp 账号组级 HTTP 配置
     * @param mixed $moduleHttp 业务模块级 HTTP 配置
     * @return array<string, mixed>
     */
    private function mergeHttpConfig(
        $commonHttp,
        $moduleHttp,
        string $mode,
    ): array {
        if (! is_array($commonHttp)) {
            throw new InvalidConfigException(
                'Wechat account config [http] must be array.'
            );
        }

        if (! is_array($moduleHttp)) {
            throw new InvalidConfigException(
                "Wechat payment config [{$mode}.http] must be array."
            );
        }

        return array_replace_recursive($commonHttp, $moduleHttp);
    }

    /**
     * 根据支付类型选择公众号、小程序或默认应用配置。
     *
     * @param array<string, mixed> $config 支付模块配置
     */
    private function resolveApplicationName(
        string $payType,
        array $config,
    ): string {
        return match ($payType) {
            PayType::MINI_PROGRAM => ApplicationType::MINI_PROGRAM,
            PayType::OFFICIAL => ApplicationType::OFFICIAL_ACCOUNT,
            PayType::APP => ApplicationType::APP,
            PayType::H5,
            PayType::NATIVE,
            PayType::MICROPAY => $this->getDefaultApplication(
                $config,
                $payType,
            ),
            default => throw new UnsupportedModeException(
                "Unsupported pay type: {$payType}"
            ),
        };
    }

    /**
     * 获取模块配置声明的默认应用名称。
     *
     * @param array<string, mixed> $config 支付模块配置
     */
    private function getDefaultApplication(
        array $config,
        string $payType,
    ): string {
        $application = $config['default_application'] ?? null;

        if (! is_string($application) || $application === '') {
            throw new InvalidConfigException(
                "Wechat payment config [default_application] is required for {$payType}."
            );
        }

        return $application;
    }
}
