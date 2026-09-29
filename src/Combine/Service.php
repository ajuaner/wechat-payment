<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Combine;

use Qinii\WechatPayment\Factory\CombineFactory;
use Qinii\WechatPayment\Factory\RefundFactory;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Config\PaymentConfigResolver;
use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Enum\PayMode;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Payment\PaymentRefundDto;
use Qinii\WechatPayment\Partner\PartnerRefundDto;
use Qinii\WechatPayment\Refund\V2\Client as RefundV2Client;
use Qinii\WechatPayment\Refund\V3\Client as RefundV3Client;
use Qinii\WechatPayment\Refund\AbnormalRefundDto;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentClient;
use Qinii\WechatPayment\Shared\WechatContext;
use Qinii\WechatPayment\Combine\V3\Client as CombineV3Client;

final class Service
{
    /** 创建合单支付服务。 */
    public function __construct(
        private WechatContext $context,
        private ?PaymentConfigResolver $configResolver = null,
    )
    {
    }

    /** 根据合单 DTO 发起合单支付。 */
    public function create(CombineDto $dto): array
    {
        return $this->client($dto)->create($dto);
    }

    /** 发起合单支付的兼容别名。 */
    public function pay(CombineDto $dto): array
    {
        return $this->create($dto);
    }

    /** 按合单商户订单号查询合单订单。 */
    public function queryByOutTradeNo(
        string $combineOutTradeNo,
        string $mode = PayMode::PAYMENT,
    ): array {
        return $this->orderClient($mode)->queryByOutTradeNo(
            $combineOutTradeNo,
        );
    }

    /**
     * 按合单商户订单号和商品单列表关闭合单订单。
     *
     * @param array<int, array<string, mixed>|SubOrderDto> $subOrders
     */
    public function close(
        string $combineOutTradeNo,
        array $subOrders,
        string $mode = PayMode::PAYMENT,
    ): array {
        return $this->orderClient($mode)->close(
            $combineOutTradeNo,
            $subOrders,
        );
    }

    /** 申请普通合单退款，配置类型由 DTO 决定。 */
    public function refund(PaymentRefundDto|PartnerRefundDto $dto): array
    {
        return $this->refundClient($dto)->refund($dto);
    }

    /** 申请电商收付通合单退款。 */
    public function ecommerceRefund(PartnerRefundDto $dto): array
    {
        return $this->refundClient($dto)->ecommerceRefund($dto);
    }

    /** 按商户退款单号查询普通合单退款。 */
    public function queryRefund(string $outRefundNo, ?string $subMchid = null): array
    {
        $mode = $subMchid === null
            ? PayMode::PAYMENT
            : PayMode::PARTNER;
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolveMode(
            $this->context->accountConfig(),
            $mode,
        );
        $this->assertV3($config);

        return RefundFactory::refund(
            $config,
            $this->context->application($config),
        )->query(
            $outRefundNo,
            $subMchid,
        );
    }

    /** 按商户退款单号查询电商收付通合单退款。 */
    public function queryEcommerceRefund(
        string $outRefundNo,
        string $subMchid,
    ): array {
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolveMode(
            $this->context->accountConfig(),
            PayMode::PARTNER,
        );
        $this->assertV3($config);

        return RefundFactory::refund(
            $config,
            $this->context->application($config),
        )->queryEcommerce($outRefundNo, $subMchid);
    }

    /** 按微信退款单号查询电商收付通合单退款。 */
    public function queryEcommerceRefundById(
        string $refundId,
        string $subMchid,
    ): array {
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolveMode(
            $this->context->accountConfig(),
            PayMode::PARTNER,
        );
        $this->assertV3($config);

        return RefundFactory::refund(
            $config,
            $this->context->application($config),
        )->queryEcommerceById($refundId, $subMchid);
    }

    /** 处理普通合单异常退款。 */
    public function abnormalRefund(AbnormalRefundDto $dto): array
    {
        $mode = $dto->sub_mchid === ''
            ? PayMode::PAYMENT
            : PayMode::PARTNER;
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolveMode(
            $this->context->accountConfig(),
            $mode,
        );
        $this->assertV3($config);

        return RefundFactory::refund(
            $config,
            $this->context->application($config),
        )->abnormalRefund($dto);
    }

    /** 处理电商收付通合单异常退款。 */
    public function abnormalEcommerceRefund(AbnormalRefundDto $dto): array
    {
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolveMode(
            $this->context->accountConfig(),
            PayMode::PARTNER,
        );
        $this->assertV3($config);

        return RefundFactory::refund(
            $config,
            $this->context->application($config),
        )->abnormalEcommerceRefund($dto);
    }

    /** 根据退款 DTO 类型选择普通商户或服务商退款客户端。 */
    private function refundClient(PaymentRefundDto|PartnerRefundDto $dto): RefundV2Client|RefundV3Client
    {
        $mode = $dto instanceof PartnerRefundDto
            ? PayMode::PARTNER
            : PayMode::PAYMENT;
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolveMode(
            $this->context->accountConfig(),
            $mode,
        );
        $this->assertV3($config);

        return RefundFactory::refund(
            $config,
            $this->context->application($config),
        );
    }

    /** 校验合单 DTO 并创建对应配置的合单支付客户端。 */
    private function client(CombineDto $dto): AbstractPaymentClient
    {
        $dto->validate();
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolve(
            $this->context->accountConfig(),
            $dto,
        );

        return CombineFactory::combine(
            $config,
            $this->context->application($config),
        );
    }

    /** 根据调用方指定的普通商户或服务商配置创建合单订单客户端。 */
    private function orderClient(string $mode): CombineV3Client
    {
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolveMode(
            $this->context->accountConfig(),
            $mode,
        );

        $this->assertV3($config);

        return CombineFactory::combine(
            $config,
            $this->context->application($config),
        );
    }

    /** 合单退款仅支持 API v3，统一在这里校验。 */
    private function assertV3(AbstractWechatPayConfig $config): void
    {
        if ($config->version !== ApiVersion::V3) {
            throw new UnsupportedModeException(
                "Combined payment refunds require API v3, got {$config->version}."
            );
        }
    }
}
