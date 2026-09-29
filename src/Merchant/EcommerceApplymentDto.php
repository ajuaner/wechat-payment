<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Merchant;

use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\Traits\Fillable;

/** 电商收付通二级商户入驻申请数据。 */
final class EcommerceApplymentDto
{
    use Fillable;

    public string $out_request_no = '';
    public string $organization_type = '';
    public bool $finance_institution = false;
    public array $business_license_info = [];
    public array $finance_institution_info = [];
    public string $id_holder_type = '';
    public string $id_doc_type = '';
    public string $authorize_letter_copy = '';
    public array $id_card_info = [];
    public array $id_doc_info = [];
    public bool $owner = false;
    public array $account_info = [];
    public array $contact_info = [];
    public array $sales_scene_info = [];
    public array $settlement_info = [];
    public string $merchant_shortname = '';
    public array $qualifications = [];
    public array $business_addition_pics = [];
    public string $business_addition_desc = '';
    public array $ubo_info_list = [];
    public array $extra = [];

    /** 校验电商收付通入驻的固定顶层参数。 */
    public function validate(): void
    {
        if (preg_match('/^[0-9A-Za-z_]{1,124}$/D', $this->out_request_no) !== 1) {
            throw new PaymentException(
                'EcommerceApplymentDto [out_request_no] must be 1-124 letters, numbers or underscores.'
            );
        }

        if ($this->organization_type === '') {
            throw new PaymentException(
                'EcommerceApplymentDto [organization_type] is required.'
            );
        }

        if ($this->merchant_shortname === '') {
            throw new PaymentException(
                'EcommerceApplymentDto [merchant_shortname] is required.'
            );
        }
    }

    /** 标准化布尔值和嵌套对象字段。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        if (in_array($key, ['finance_institution', 'owner'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        if (in_array($key, [
            'business_license_info',
            'finance_institution_info',
            'id_card_info',
            'id_doc_info',
            'account_info',
            'contact_info',
            'sales_scene_info',
            'settlement_info',
            'qualifications',
            'business_addition_pics',
            'ubo_info_list',
            'extra',
        ], true)) {
            return is_array($value) ? $value : [];
        }

        return $value;
    }
}
