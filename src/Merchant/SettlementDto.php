<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Merchant;

use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\Traits\Fillable;

/** 特约商户或二级商户结算账户修改数据。 */
final class SettlementDto
{
    use Fillable;

    public string $sub_mchid = '';
    public string $account_type = '';
    public string $account_bank = '';
    public string $bank_name = '';
    public string $bank_branch_id = '';
    public string $account_number = '';
    public string $account_name = '';
    public array $extra = [];

    /** 校验修改结算账户所需的固定参数。 */
    public function validate(): void
    {
        foreach (['sub_mchid', 'account_type', 'account_bank', 'account_number'] as $field) {
            if ($this->{$field} === '') {
                throw new PaymentException(
                    "SettlementDto [{$field}] is required."
                );
            }
        }

        if (strlen($this->sub_mchid) > 32) {
            throw new PaymentException(
                'SettlementDto [sub_mchid] must not exceed 32 bytes.'
            );
        }

        if (! in_array($this->account_type, [
            'ACCOUNT_TYPE_BUSINESS',
            'ACCOUNT_TYPE_PRIVATE',
        ], true)) {
            throw new PaymentException(
                'SettlementDto [account_type] must be ACCOUNT_TYPE_BUSINESS or ACCOUNT_TYPE_PRIVATE.'
            );
        }
    }

    /** 标准化扩展参数。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        return $key === 'extra' && ! is_array($value) ? [] : $value;
    }
}
