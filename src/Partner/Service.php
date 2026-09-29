<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Partner;

use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Config\PaymentConfigResolver;
use Qinii\WechatPayment\Enum\PayMode;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Factory\PartnerFactory;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentClient;
use Qinii\WechatPayment\Shared\WechatContext;
use Qinii\WechatPayment\Factory\RefundFactory;
use Qinii\WechatPayment\Refund\V2\Client as RefundV2Client;
use Qinii\WechatPayment\Refund\V3\Client as RefundV3Client;
use Qinii\WechatPayment\Refund\AbnormalRefundDto;
use Qinii\WechatPayment\Partner\V2\Client as V2PartnerClient;

final class Service
{
    /** 创建服务商支付服务。 */
    public function __construct(
        private WechatContext $context,
        private ?PaymentConfigResolver $configResolver = null,
    )
    {
    }

    /** 根据 DTO 的支付类型发起服务商支付。 */
    public function create(PartnerDto $dto): array
    {
        return $this->client($dto)->create($dto);
    }

    /** 发起服务商支付的兼容别名。 */
    public function pay(PartnerDto $dto): array
    {
        return $this->create($dto);
    }

    /** 按微信支付订单号查询服务商订单。 */
    public function queryByTransactionId(
        string $transactionId,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->client()->queryByTransactionId(
            $transactionId,
            $subMchid,
            $subAppid,
        );
    }

    /** 按商户订单号查询服务商订单。 */
    public function queryByOutTradeNo(
        string $outTradeNo,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->client()->queryByOutTradeNo(
            $outTradeNo,
            $subMchid,
            $subAppid,
        );
    }

    /** 按商户订单号关闭服务商订单。 */
    public function close(
        string $outTradeNo,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->client()->close(
            $outTradeNo,
            $subMchid,
            $subAppid,
        );
    }

    /** 查询服务商 V2 付款码支付订单。 */
    public function queryMicropay(
        string $outTradeNo,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->v2Client()->queryMicropay(
            $outTradeNo,
            $subMchid,
            $subAppid,
        );
    }

    /** 撤销服务商 V2 付款码支付订单。 */
    public function reverseMicropay(
        string $outTradeNo,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->v2Client()->reverseMicropay(
            $outTradeNo,
            $subMchid,
            $subAppid,
        );
    }

    /** 通过付款码查询服务商或子商户维度的用户 OpenID。 */
    public function authCodeToOpenid(
        string $authCode,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->v2Client()->authCodeToOpenid(
            $authCode,
            $subMchid,
            $subAppid,
        );
    }

    /** 将服务商 V2 Native 支付长链接转换为短链接。 */
    public function shortUrl(
        string $longUrl,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->v2Client()->shortUrl(
            $longUrl,
            $subMchid,
            $subAppid,
        );
    }

    /** 上报服务商 V2 接口调用耗时和返回结果。 */
    public function reportTransaction(
        array $report,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->v2Client()->reportTransaction(
            $report,
            $subMchid,
            $subAppid,
        );
    }

    /** 申请服务商交易账单。 */
    public function applyTradeBill(
        string $billDate,
        ?string $subMchid = null,
        string $billType = 'ALL',
        ?string $tarType = null,
    ): array|string {
        return $this->client()->applyTradeBill(
            $billDate,
            $subMchid,
            $billType,
            $tarType,
        );
    }

    /** 申请服务商资金账单。 */
    public function applyFundFlowBill(
        string $billDate,
        string $accountType = 'BASIC',
        ?string $tarType = null,
    ): array|string {
        return $this->client()->applyFundFlowBill(
            $billDate,
            $accountType,
            $tarType,
        );
    }

    /** 使用微信返回的临时地址下载账单原始内容。 */
    public function downloadBill(string $downloadUrl): string
    {
        return $this->client()->downloadBill($downloadUrl);
    }

    /** 申请服务商普通退款。 */
    public function refund(PartnerRefundDto $dto): array
    {
        return $this->refundClient()->refund($dto);
    }

    /** 申请电商收付通退款。 */
    public function ecommerceRefund(PartnerRefundDto $dto): array
    {
        return $this->refundClient()->ecommerceRefund($dto);
    }

    /** 按商户退款单号查询服务商普通退款。 */
    public function queryRefund(string $outRefundNo, string $subMchid): array
    {
        return $this->refundClient()->query($outRefundNo, $subMchid);
    }

    /** 按商户退款单号查询电商收付通退款。 */
    public function queryEcommerceRefund(
        string $outRefundNo,
        string $subMchid,
    ): array {
        return $this->refundClient()->queryEcommerce($outRefundNo, $subMchid);
    }

    /** 按微信退款单号查询电商收付通退款。 */
    public function queryEcommerceRefundById(
        string $refundId,
        string $subMchid,
    ): array {
        return $this->refundClient()->queryEcommerceById($refundId, $subMchid);
    }

    /** 处理服务商普通异常退款。 */
    public function abnormalRefund(AbnormalRefundDto $dto): array
    {
        return $this->refundClient()->abnormalRefund($dto);
    }

    /** 处理电商收付通异常退款。 */
    public function abnormalEcommerceRefund(AbnormalRefundDto $dto): array
    {
        return $this->refundClient()->abnormalEcommerceRefund($dto);
    }

    /** 根据服务商配置和 API 版本创建退款客户端。 */
    private function refundClient(): RefundV2Client|RefundV3Client
    {
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolveMode(
            $this->context->accountConfig(),
            PayMode::PARTNER,
        );

        if (! $config instanceof PartnerConfig) {
            throw new UnsupportedModeException(
                'Partner refund requires PartnerConfig.'
            );
        }

        return RefundFactory::refund(
            $config,
            $this->context->application($config),
        );
    }

    /** 校验 DTO 与服务商配置匹配，并创建对应版本的支付客户端。 */
    private function client(?PartnerDto $dto = null): AbstractPaymentClient
    {
        $resolver = $this->configResolver ?? new PaymentConfigResolver();
        if ($dto !== null) {
            $dto->validate();
            $config = $resolver->resolve(
                $this->context->accountConfig(),
                $dto,
            );
        } else {
            $config = $resolver->resolveMode(
                $this->context->accountConfig(),
                PayMode::PARTNER,
            );
        }

        if (! $config instanceof PartnerConfig) {
            throw new UnsupportedModeException(
                'Partner payment requires PartnerConfig.'
            );
        }

        return PartnerFactory::partner(
            $config,
            $this->context->application($config),
        );
    }

    /** 获取服务商 V2 客户端，并为版本不匹配提供明确异常。 */
    private function v2Client(): V2PartnerClient
    {
        $client = $this->client();
        if (! $client instanceof V2PartnerClient) {
            throw new UnsupportedModeException(
                'This partner operation requires Wechat Pay API v2.'
            );
        }

        return $client;
    }
}
