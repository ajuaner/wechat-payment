<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Refund\V2;

use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Payment\PaymentRefundDto;
use Qinii\WechatPayment\Partner\PartnerRefundDto;

final class Builder
{
    /** 使用普通商户或服务商配置初始化 V2 退款 Builder。 */
    public function __construct(private AbstractWechatPayConfig $config)
    {
    }

    /** 将退款 DTO 组装为 V2 XML 请求参数。 */
    public function build(PaymentRefundDto|PartnerRefundDto $dto): array
    {
        $dto->validate();

        $data = array_replace($dto->extra, [
            'appid' => $this->config->app_id,
            'mch_id' => $this->config->mch_id,
            'nonce_str' => bin2hex(random_bytes(16)),
            'transaction_id' => $dto->transaction_id,
            'out_trade_no' => $dto->out_trade_no,
            'out_refund_no' => $dto->out_refund_no,
            'total_fee' => $dto->total_fee,
            'refund_fee' => $dto->refund_fee,
            'refund_fee_type' => $dto->currency,
            'refund_desc' => $dto->reason,
            'refund_account' => $this->refundAccount($dto->funds_account),
            'notify_url' => $dto->notify_url !== ''
                ? $dto->notify_url
                : $this->config->notify_url,
        ]);

        if ($this->config instanceof PartnerConfig && ! $dto instanceof PartnerRefundDto) {
            throw new PaymentException(
                'Partner V2 refund requires PartnerRefundDto.',
            );
        }

        if (! $this->config instanceof PartnerConfig && $dto instanceof PartnerRefundDto) {
            throw new PaymentException(
                'Ordinary V2 refund requires PaymentRefundDto.',
            );
        }

        if ($dto instanceof PartnerRefundDto) {
            $data['sub_mch_id'] = $dto->sub_mchid;
        }

        return $this->filter($data);
    }

    /** 组装 V2 退款查询请求参数。 */
    public function buildQuery(string $outRefundNo, ?string $subMchid = null): array
    {
        if ($outRefundNo === '') {
            throw new PaymentException('Refund query requires out_refund_no.');
        }

        $data = [
            'appid' => $this->config->app_id,
            'mch_id' => $this->config->mch_id,
            'nonce_str' => bin2hex(random_bytes(16)),
            'out_refund_no' => $outRefundNo,
        ];

        if ($subMchid !== null && $subMchid !== '') {
            $data['sub_mch_id'] = $subMchid;
        }

        return $this->filter($data);
    }

    /** 将旧版资金账户名称映射为 V2 接口枚举值。 */
    private function refundAccount(string $account): string
    {
        return match ($account) {
            'AVAILABLE' => 'REFUND_SOURCE_RECHARGE_FUNDS',
            'UNSETTLED' => 'REFUND_SOURCE_UNSETTLED_FUNDS',
            default => $account,
        };
    }

    /** 移除退款请求中的空字段。 */
    private function filter(array $data): array
    {
        return array_filter(
            $data,
            static fn ($value): bool => $value !== '' && $value !== null && $value !== [],
        );
    }
}
