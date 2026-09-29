<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Refund\V3;

use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Payment\PaymentRefundDto;
use Qinii\WechatPayment\Partner\PartnerRefundDto;
use Qinii\WechatPayment\Refund\AbnormalRefundDto;

final class Builder
{
    /** 使用普通商户或服务商配置初始化 V3 退款 Builder。 */
    public function __construct(private AbstractWechatPayConfig $config)
    {
    }

    /** 将普通退款 DTO 组装为 V3 退款请求参数。 */
    public function build(PaymentRefundDto|PartnerRefundDto $dto): array
    {
        $dto->validate();

        $amount = [
            'total' => $dto->total_fee,
            'refund' => $dto->refund_fee,
            'currency' => $dto->currency,
            'from' => $dto->refund_from,
        ];

        $data = array_replace($dto->extra, [
            'transaction_id' => $dto->transaction_id,
            'out_trade_no' => $dto->out_trade_no,
            'out_refund_no' => $dto->out_refund_no,
            'reason' => $dto->reason,
            'notify_url' => $dto->notify_url !== ''
                ? $dto->notify_url
                : $this->config->notify_url,
            'funds_account' => $dto->funds_account,
            'amount' => $amount,
            'goods_detail' => $dto->goods_detail,
        ]);

        if ($this->config instanceof PartnerConfig && ! $dto instanceof PartnerRefundDto) {
            throw new PaymentException(
                'Partner V3 refund requires PartnerRefundDto.',
            );
        }

        if (! $this->config instanceof PartnerConfig && $dto instanceof PartnerRefundDto) {
            throw new PaymentException(
                'Ordinary V3 refund requires PaymentRefundDto.',
            );
        }

        if ($dto instanceof PartnerRefundDto) {
            $data['sub_mchid'] = $dto->sub_mchid;
        }

        return $this->filter($data);
    }

    /** 组装电商收付通退款请求，结构和普通服务商退款不同。 */
    public function buildEcommerce(PartnerRefundDto $dto): array
    {
        if (! $this->config instanceof PartnerConfig) {
            throw new PaymentException(
                'E-commerce refund requires PartnerConfig.',
            );
        }

        // E-commerce also supports platform transactions without a sub-merchant.
        $dto->validate(false);

        if ($dto->refund_from !== [] && $dto->funds_account !== '') {
            throw new PaymentException(
                'E-commerce refund [refund_from] and [funds_account] cannot be used together.'
            );
        }

        if (
            $dto->refund_account !== ''
            && ! in_array($dto->refund_account, [
                'REFUND_SOURCE_PARTNER_ADVANCE',
                'REFUND_SOURCE_SUB_MERCHANT',
            ], true)
        ) {
            throw new PaymentException(
                'E-commerce refund [refund_account] is not supported.'
            );
        }

        if (
            $dto->funds_account !== ''
            && ! in_array($dto->funds_account, [
                'AVAILABLE',
                'UNSETTLED',
                'PREPAID',
            ], true)
        ) {
            throw new PaymentException(
                'E-commerce refund [funds_account] is not supported.'
            );
        }

        $data = array_replace($dto->extra, [
            'sub_mchid' => $dto->sub_mchid,
            'sp_appid' => $this->config->app_id,
            'sub_appid' => $dto->sub_appid,
            'transaction_id' => $dto->transaction_id,
            'out_trade_no' => $dto->out_trade_no,
            'out_refund_no' => $dto->out_refund_no,
            'reason' => $dto->reason,
            'notify_url' => $dto->notify_url !== ''
                ? $dto->notify_url
                : $this->config->notify_url,
            'refund_account' => $dto->refund_account,
            'funds_account' => $dto->funds_account,
            'amount' => [
                'total' => $dto->total_fee,
                'refund' => $dto->refund_fee,
                'currency' => $dto->currency,
                'from' => $dto->refund_from,
            ],
        ]);

        return $this->filter($data);
    }

    /** 组装银行卡异常退款请求参数。 */
    public function buildAbnormal(AbnormalRefundDto $dto): array
    {
        $isServiceProvider = $this->config instanceof PartnerConfig;
        $dto->validate($isServiceProvider);

        $data = array_replace($dto->extra, [
            'out_refund_no' => $dto->out_refund_no,
            'type' => $dto->type,
            'bank_type' => $dto->bank_type,
            'bank_account' => $dto->bank_account,
            'real_name' => $dto->real_name,
        ]);

        if ($isServiceProvider) {
            $data['sub_mchid'] = $dto->sub_mchid;
        }

        return $this->filter($data);
    }

    /** 清理退款请求中的空字段，并处理 amount 子数组。 */
    private function filter(array $data): array
    {
        if (isset($data['amount']) && is_array($data['amount'])) {
            $data['amount'] = array_filter(
                $data['amount'],
                static fn ($value): bool => $value !== '' && $value !== null && $value !== [],
            );
        }

        return array_filter(
            $data,
            static fn ($value): bool => $value !== '' && $value !== null && $value !== [],
        );
    }

}
