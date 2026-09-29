<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\ProfitSharing;

use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Config\PaymentConfig;
use Qinii\WechatPayment\Config\PaymentConfigResolver;
use Qinii\WechatPayment\Enum\PayMode;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Factory\ProfitSharingFactory;
use Qinii\WechatPayment\Shared\WechatContext;

/** 分账服务入口，显式选择普通商户、服务商或平台收付通协议。 */
final class Service
{
    private ?ClientInterface $paymentClient = null;
    private ?ClientInterface $partnerClient = null;
    private ?ClientInterface $ecommerceClient = null;

    /** 创建分账服务并绑定当前微信账号上下文。 */
    public function __construct(
        private WechatContext $context,
        private ?PaymentConfigResolver $configResolver = null,
    ) {
    }

    /** 获取普通商户分账客户端，版本由 payment 配置决定。 */
    public function payment(): ClientInterface
    {
        if ($this->paymentClient !== null) {
            return $this->paymentClient;
        }

        $config = $this->resolver()->resolveMode(
            $this->context->accountConfig(),
            PayMode::PAYMENT,
        );

        if (! $config instanceof PaymentConfig) {
            throw new UnsupportedModeException(
                'Ordinary profit sharing requires PaymentConfig.'
            );
        }

        return $this->paymentClient = ProfitSharingFactory::profitSharing(
            $config,
            $this->context->application($config),
        );
    }

    /** 获取服务商分账客户端，版本由 partner 配置决定。 */
    public function partner(): ClientInterface
    {
        if ($this->partnerClient !== null) {
            return $this->partnerClient;
        }

        $config = $this->partnerConfig();

        return $this->partnerClient = ProfitSharingFactory::profitSharing(
            $config,
            $this->context->application($config),
        );
    }

    /** 获取平台收付通分账客户端，仅支持 PartnerConfig API v3。 */
    public function ecommerce(): ClientInterface
    {
        if ($this->ecommerceClient !== null) {
            return $this->ecommerceClient;
        }

        $config = $this->partnerConfig();

        return $this->ecommerceClient = ProfitSharingFactory::ecommerce(
            $config,
            $this->context->application($config),
        );
    }

    /** 解析当前账号的服务商支付配置。 */
    private function partnerConfig(): PartnerConfig
    {
        $config = $this->resolver()->resolveMode(
            $this->context->accountConfig(),
            PayMode::PARTNER,
        );

        if (! $config instanceof PartnerConfig) {
            throw new UnsupportedModeException(
                'Partner profit sharing requires PartnerConfig.'
            );
        }

        return $config;
    }

    /** 获取当前服务使用的支付配置解析器。 */
    private function resolver(): PaymentConfigResolver
    {
        return $this->configResolver ??= new PaymentConfigResolver();
    }
}
