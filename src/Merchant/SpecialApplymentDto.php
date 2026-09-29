<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Merchant;

use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\Traits\Fillable;

/** 普通服务商特约商户入驻申请数据。 */
final class SpecialApplymentDto
{
    use Fillable;

    /** 服务商自定义的业务申请编号。 */
    public string $business_code = '';

    /** 超级管理员信息。 */
    public array $contact_info = [];

    /** 主体资料。 */
    public array $subject_info = [];

    /** 兼容旧写法，发送时会合并到 subject_info.business_license_info。 */
    public array $business_license_info = [];

    /** 兼容旧写法，发送时会合并到 subject_info.certificate_info。 */
    public array $certificate_info = [];

    /** 兼容旧写法，发送时会合并到 subject_info.identity_info。 */
    public array $identity_info = [];

    /** 经营资料。 */
    public array $business_info = [];

    /** 结算规则。 */
    public array $settlement_info = [];

    /** 结算银行账户。 */
    public array $bank_account_info = [];

    /** 补充材料。 */
    public array $addition_info = [];

    /** 微信后续新增字段。 */
    public array $extra = [];

    /** 校验特约商户入驻的固定顶层参数。 */
    public function validate(): void
    {
        if (preg_match('/^[0-9A-Za-z_]{1,124}$/D', $this->business_code) !== 1) {
            throw new PaymentException(
                'SpecialApplymentDto [business_code] must be 1-124 letters, numbers or underscores.'
            );
        }

        foreach ([
            'contact_info',
            'subject_info',
            'business_info',
            'settlement_info',
            'bank_account_info',
        ] as $field) {
            if ($this->{$field} === []) {
                throw new PaymentException(
                    "SpecialApplymentDto [{$field}] is required."
                );
            }
        }
    }

    /** 标准化特约商户入驻的嵌套对象字段。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        return in_array($key, [
            'contact_info',
            'subject_info',
            'business_license_info',
            'certificate_info',
            'identity_info',
            'business_info',
            'settlement_info',
            'bank_account_info',
            'addition_info',
            'extra',
        ], true) && ! is_array($value)
            ? []
            : $value;
    }
}
