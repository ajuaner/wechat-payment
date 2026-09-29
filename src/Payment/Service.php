<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Payment;

use Qinii\WechatPayment\Config\PaymentConfig;
use Qinii\WechatPayment\Config\PaymentConfigResolver;
use Qinii\WechatPayment\Enum\PayMode;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Factory\PaymentFactory;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentClient;
use Qinii\WechatPayment\Shared\WechatContext;
use Qinii\WechatPayment\Factory\RefundFactory;
use Qinii\WechatPayment\Payment\PaymentRefundDto;
use Qinii\WechatPayment\Refund\V2\Client as RefundV2Client;
use Qinii\WechatPayment\Refund\V3\Client as RefundV3Client;
use Qinii\WechatPayment\Refund\AbnormalRefundDto;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Payment\V2\Client as PaymentV2Client;
use Qinii\WechatPayment\Payment\V3\Client as PaymentV3Client;

final class Service
{
    /**
     * 创建普通商户支付服务。
     */
    public function __construct(
        private WechatContext $context,
        private ?PaymentConfigResolver $configResolver = null,
    )
    {
    }

    /**
     * 根据 DTO 的支付类型发起普通商户支付。
     *
     * @return array<string, mixed> 微信支付返回结果
     */
    public function create(PaymentDto $dto): array
    {
        return $this->client($dto)->create($dto);
    }

    /**
     * 发起普通商户支付的兼容别名，行为与 create() 相同。
     *
     * @return array<string, mixed> 微信支付返回结果
     */
    public function pay(PaymentDto $dto): array
    {
        return $this->create($dto);
    }

    /** 按商户订单号查询普通支付订单。 */
    public function queryByOutTradeNo(
        string $outTradeNo,
        ?string $payType = null,
    ): array {
        return $this->orderClient($payType)->queryByOutTradeNo($outTradeNo);
    }

    /** 按微信支付订单号查询普通支付订单。 */
    public function queryByTransactionId(
        string $transactionId,
        ?string $payType = null,
    ): array {
        return $this->orderClient($payType)->queryByTransactionId(
            $transactionId,
        );
    }

    /** 按商户订单号关闭普通支付订单。 */
    public function close(
        string $outTradeNo,
        ?string $payType = null,
    ): array {
        return $this->orderClient($payType)->close($outTradeNo);
    }

    /** 查询 V2 付款码支付订单。 */
    public function queryMicropay(string $outTradeNo): array
    {
        return $this->orderClient(PayType::MICROPAY)
            ->queryMicropay($outTradeNo);
    }

    /** 撤销 V2 付款码支付订单。 */
    public function reverseMicropay(string $outTradeNo): array
    {
        return $this->orderClient(PayType::MICROPAY)
            ->reverseMicropay($outTradeNo);
    }

    /**
     * 申请普通商户订单退款。
     *
     * @return array<string, mixed> 微信支付返回结果
     */
    public function refund(PaymentRefundDto $dto): array
    {
        return $this->refundClient()->refund($dto);
    }

    /**
     * 按商户退款单号查询普通商户退款。
     *
     * @return array<string, mixed> 微信支付返回结果
     */
    public function queryRefund(string $outRefundNo): array
    {
        return $this->refundClient()->query($outRefundNo);
    }

    /**
     * 处理普通商户异常退款（银行卡退款失败后的补偿流程）。
     *
     * @return array<string, mixed> 微信支付返回结果
     */
    public function abnormalRefund(AbnormalRefundDto $dto): array
    {
        return $this->refundClient()->abnormalRefund($dto);
    }

    /** 根据普通支付配置和 API 版本创建退款客户端。 */
    private function refundClient(): RefundV2Client|RefundV3Client
    {
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolveMode(
            $this->context->accountConfig(),
            PayMode::PAYMENT,
        );

        if (! $config instanceof PaymentConfig) {
            throw new UnsupportedModeException(
                'Ordinary refund requires PaymentConfig.'
            );
        }

        return RefundFactory::refund(
            $config,
            $this->context->application($config),
        );
    }

    /** 校验 DTO 与普通商户配置匹配，并创建对应版本的支付客户端。 */
    private function client(PaymentDto $dto): AbstractPaymentClient
    {
        $dto->validate();
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolve(
            $this->context->accountConfig(),
            $dto,
        );

        if (! $config instanceof PaymentConfig) {
            throw new UnsupportedModeException(
                'Ordinary payment requires PaymentConfig.'
            );
        }

        return PaymentFactory::payment(
            $config,
            $this->context->application($config),
        );
    }

    /** 根据配置版本创建普通支付订单操作客户端。 */
    private function orderClient(
        ?string $payType = null,
    ): PaymentV2Client|PaymentV3Client {
        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolveMode(
            $this->context->accountConfig(),
            PayMode::PAYMENT,
            $payType,
        );

        if (! $config instanceof PaymentConfig) {
            throw new UnsupportedModeException(
                'Ordinary order operation requires PaymentConfig.'
            );
        }

        $client = PaymentFactory::payment(
            $config,
            $this->context->application($config),
        );

        if (! $client instanceof PaymentV2Client && ! $client instanceof PaymentV3Client) {
            throw new UnsupportedModeException(
                'Unsupported ordinary payment order client.'
            );
        }

        return $client;
    }
}
