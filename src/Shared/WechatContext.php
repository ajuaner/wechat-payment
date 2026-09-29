<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Shared;

use EasyWeChat\Pay\Application as PayApplication;
use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use Qinii\WechatCore\Config\ConfigResolver;
use Qinii\WechatPayment\Certificate\PlatformCertificateManager;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WechatContext
{
    private ?HttpClientInterface $httpClient = null;
    private PlatformCertificateManager $certificateManager;

    /**
     * 创建支付上下文，统一保存账号、HTTP 客户端和平台证书状态。
     */
    public function __construct(
        private ConfigResolver $configResolver,
        private string $accountName = 'default',
        ?PlatformCertificateManager $certificateManager = null,
    ) {
        $this->certificateManager = $certificateManager
            ?? new PlatformCertificateManager();
    }

    /**
     * 设置支付请求和平台证书下载共用的 HTTP 客户端。
     */
    public function setHttpClient(HttpClientInterface $httpClient): self
    {
        $this->httpClient = $httpClient;
        $this->certificateManager->setHttpClient($httpClient);

        return $this;
    }

    /**
     * 克隆上下文并切换到指定账号组。
     */
    public function account(string $name): self
    {
        $context = clone $this;
        $context->accountName = $name;
        $context->certificateManager = clone $this->certificateManager;

        return $context;
    }

    /**
     * 获取当前账号组的完整配置，交给各业务边界解析自己的配置。
     *
     * @return array<string, mixed>
     */
    public function accountConfig(): array
    {
        return $this->configResolver->require($this->accountName);
    }

    /**
     * 根据支付配置创建 EasyWeChat 支付应用，并注入已配置的 HTTP 客户端。
     */
    public function application(
        AbstractWechatPayConfig $config,
    ): ApplicationContract {
        $config = $this->certificateManager->resolve($config);
        $application = new PayApplication($config->toArray());

        if ($this->httpClient !== null) {
            $application->setHttpClient($this->httpClient);
        }

        return $application;
    }
}
