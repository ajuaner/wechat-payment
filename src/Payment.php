<?php

declare(strict_types=1);

namespace Qinii\WechatPayment;

use Qinii\WechatCore\Config\ArrayConfigProvider;
use Qinii\WechatCore\Config\ConfigResolver;
use Qinii\WechatPayment\Certificate\PlatformCertificateManager;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Qinii\WechatPayment\Shared\WechatContext;
use Qinii\WechatPayment\Combine\Service as CombineService;
use Qinii\WechatPayment\Partner\Service as PartnerService;
use Qinii\WechatPayment\Payment\Service as PaymentService;
use Qinii\WechatPayment\Transfer\Service as TransferService;
use Qinii\WechatPayment\Merchant\Service as MerchantService;
use Qinii\WechatPayment\ProfitSharing\Service as ProfitSharingService;
use Qinii\WechatPayment\Notify\Service as NotifyService;


class Payment
{
    private WechatContext $context;

    /**
     * 创建支付门面，并绑定当前使用的账号组。
     *
     * @param ConfigResolver $configResolver 账号配置解析器
     * @param string $accountName 默认账号组名称
     * @param PlatformCertificateManager|null $certificateManager 平台证书管理器
     */
    public function __construct(
        ConfigResolver $configResolver,
        string $accountName = 'default',
        ?PlatformCertificateManager $certificateManager = null,
    ) {
        $this->context = new WechatContext(
            $configResolver,
            $accountName,
            $certificateManager,
        );
    }

    /**
     * 使用数组配置快速创建支付门面，不依赖外部容器。
     *
     * @param array<string, array<string, mixed>> $config
     */
    public static function create(
        array $config,
        string $defaultAccount = 'default',
    ): self {
        return new self(
            configResolver: new ConfigResolver(
                new ArrayConfigProvider($config),
            ),
            accountName: $defaultAccount,
        );
    }

    /**
     * 设置证书下载和支付请求共用的 HTTP 客户端。
     *
     * @param HttpClientInterface $httpClient HTTP 客户端实例
     * @return self 当前门面实例
     */
    public function setHttpClient(HttpClientInterface $httpClient): self
    {
        $this->context->setHttpClient($httpClient);

        return $this;
    }

    /**
     * 切换账号组，并返回不会影响原实例的新门面。
     *
     * @param string $name 账号组名称
     * @return self 切换账号后的门面
     */
    public function account(string $name): self
    {
        $payment = clone $this;
        $payment->context = $this->context->account($name);

        return $payment;
    }

    /**
     * 获取普通商户支付服务实例。
     *
     * @return PaymentService 普通商户支付服务
     */
    public function payment(): PaymentService
    {
        return new PaymentService($this->context);
    }

    /**
     * 获取服务商支付服务实例。
     *
     * @return PartnerService 服务商支付服务
     */
    public function partner(): PartnerService
    {
        return new PartnerService($this->context);
    }

    /**
     * 获取合单支付服务实例。
     *
     * @return CombineService 合单支付服务
     */
    public function combine(): CombineService
    {
        return new CombineService($this->context);
    }

    /**
     * 获取商户付款到零钱服务实例。
     *
     * @return TransferService 转账服务
     */
    public function transfer(): TransferService
    {
        return new TransferService($this->context);
    }

    /**
     * 获取子商户入驻与管理服务实例。
     *
     * @return MerchantService 子商户管理服务
     */
    public function merchant(): MerchantService
    {
        return new MerchantService($this->context);
    }

    /**
     * 获取分账服务入口。
     *
     * 调用方需要继续选择 payment、partner 或 ecommerce 协议。
     */
    public function profitSharing(): ProfitSharingService
    {
        return new ProfitSharingService($this->context);
    }

    /** 获取支付、退款、转账和分账通知验签解密服务。 */
    public function notify(): NotifyService
    {
        return new NotifyService($this->context);
    }
}
