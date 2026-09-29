<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Refund;

use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\Traits\Fillable;

/**
 * Request data for handling a refund whose original bank-card route failed.
 *
 * The bank account and recipient name are accepted as plaintext here and are
 * encrypted with the configured WeChat Pay public key by the V3 client.
 */
class AbnormalRefundDto
{
    use Fillable;

    public string $refund_id = '';
    public string $out_refund_no = '';
    public string $type = '';
    public string $bank_type = '';
    public string $bank_account = '';
    public string $real_name = '';
    /** Service-provider refunds require this field; ordinary refunds leave it empty. */
    public string $sub_mchid = '';

    /** @var array<string, mixed> */
    public array $extra = [];

    /** 将异常退款字段转换为请求所需的类型。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        if ($key === 'extra') {
            return is_array($value) ? $value : [];
        }

        return $value;
    }

    /** 校验银行卡异常退款的退款单、账户和收款人信息。 */
    public function validate(bool $serviceProvider = false): void
    {
        $this->requireValue($this->refund_id, 'refund_id');
        $this->requireValue($this->out_refund_no, 'out_refund_no');
        $this->requireValue($this->type, 'type');

        if (! in_array($this->type, [
            'USER_BANK_CARD',
            'MERCHANT_BANK_CARD',
        ], true)) {
            throw new PaymentException(
                'AbnormalRefundDto [type] must be USER_BANK_CARD or MERCHANT_BANK_CARD.'
            );
        }

        if ($serviceProvider) {
            $this->requireValue($this->sub_mchid, 'sub_mchid');
        }

        foreach ([
            'refund_id' => [$this->refund_id, 32],
            'out_refund_no' => [$this->out_refund_no, 64],
            'sub_mchid' => [$this->sub_mchid, 32],
            'bank_type' => [$this->bank_type, 16],
            'bank_account' => [$this->bank_account, 1024],
            'real_name' => [$this->real_name, 1024],
        ] as $field => [$value, $maxLength]) {
            if (strlen((string) $value) > $maxLength) {
                throw new PaymentException(
                    "AbnormalRefundDto [{$field}] must not exceed {$maxLength} bytes."
                );
            }
        }

        if ($this->type === 'USER_BANK_CARD') {
            $this->requireValue($this->bank_type, 'bank_type');
            $this->requireValue($this->bank_account, 'bank_account');
            $this->requireValue($this->real_name, 'real_name');
        }
    }

    /** 校验一个异常退款字段不能为空。 */
    protected function requireValue(mixed $value, string $field): void
    {
        if ($value === '' || $value === null || $value === []) {
            throw new PaymentException("AbnormalRefundDto [{$field}] is required.");
        }
    }
}
