<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Payment;

use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\Traits\Fillable;

/** Request data shared by ordinary and combined payment refunds. */
class PaymentRefundDto
{
    use Fillable;

    /** One of transaction_id or out_trade_no is required. */
    public string $transaction_id = '';
    public string $out_trade_no = '';
    public string $out_refund_no = '';
    public int $total_fee = 0;
    public int $refund_fee = 0;
    public string $currency = 'CNY';
    public string $reason = '';
    public string $notify_url = '';
    public string $funds_account = '';
    /** @var array<int, array<string, mixed>> */
    public array $refund_from = [];
    /** @var array<int, array<string, mixed>> */
    public array $goods_detail = [];
    /** @var array<string, mixed> */
    public array $extra = [];

    /** 将退款 DTO 字段转换为请求所需的标量或数组类型。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'total_fee', 'refund_fee' => (int) $value,
            'refund_from', 'goods_detail', 'extra'
                => is_array($value) ? $value : [],
            default => $value,
        };
    }

    /** 校验普通退款请求的订单、金额和币种。 */
    public function validate(): void
    {
        if ($this->transaction_id === '' && $this->out_trade_no === '') {
            throw new PaymentException(
                'PaymentRefundDto requires transaction_id or out_trade_no.'
            );
        }

        $this->requireValue($this->out_refund_no, 'out_refund_no');

        if ($this->total_fee <= 0) {
            throw new PaymentException(
                'PaymentRefundDto [total_fee] must be greater than 0.'
            );
        }

        if ($this->refund_fee <= 0) {
            throw new PaymentException(
                'PaymentRefundDto [refund_fee] must be greater than 0.'
            );
        }

        if ($this->refund_fee > $this->total_fee) {
            throw new PaymentException(
                'PaymentRefundDto [refund_fee] cannot exceed total_fee.'
            );
        }

        foreach ([
            'transaction_id' => [$this->transaction_id, 32],
            'out_trade_no' => [$this->out_trade_no, 32],
            'out_refund_no' => [$this->out_refund_no, 64],
            'reason' => [$this->reason, 80],
            'currency' => [$this->currency, 16],
        ] as $field => [$value, $maxLength]) {
            if (strlen((string) $value) > $maxLength) {
                throw new PaymentException(
                    "PaymentRefundDto [{$field}] must not exceed {$maxLength} bytes."
                );
            }
        }

        if ($this->currency !== 'CNY') {
            throw new PaymentException(
                'PaymentRefundDto [currency] must be CNY.'
            );
        }
    }

    /** 校验一个退款字段不能为空。 */
    protected function requireValue(mixed $value, string $field): void
    {
        if ($value === '' || $value === null || $value === []) {
            throw new PaymentException("PaymentRefundDto [{$field}] is required.");
        }
    }
}
