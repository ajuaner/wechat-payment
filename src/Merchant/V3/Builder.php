<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Merchant\V3;

use Qinii\WechatPayment\Merchant\EcommerceApplymentDto;
use Qinii\WechatPayment\Merchant\SettlementDto;
use Qinii\WechatPayment\Merchant\SpecialApplymentDto;

/** 将子商户管理 DTO 转换为微信支付 API v3 请求参数。 */
final class Builder
{
    /** 构造普通服务商特约商户入驻参数。 */
    public function buildSpecialApplyment(SpecialApplymentDto $dto): array
    {
        $dto->validate();
        $extra = $dto->extra;
        unset(
            $extra['business_license_info'],
            $extra['certificate_info'],
            $extra['identity_info'],
        );

        return $this->filter(array_replace($extra, [
            'business_code' => $dto->business_code,
            'contact_info' => $dto->contact_info,
            'subject_info' => $this->specialSubjectInfo($dto),
            'business_info' => $dto->business_info,
            'settlement_info' => $dto->settlement_info,
            'bank_account_info' => $dto->bank_account_info,
            'addition_info' => $dto->addition_info,
        ]));
    }

    /** 将旧版 DTO 顶层主体字段兼容合并到官方要求的 subject_info。 */
    private function specialSubjectInfo(SpecialApplymentDto $dto): array
    {
        $subjectInfo = $dto->subject_info;

        foreach ([
            'business_license_info',
            'certificate_info',
            'identity_info',
        ] as $field) {
            if (! array_key_exists($field, $subjectInfo) && $dto->{$field} !== []) {
                $subjectInfo[$field] = $dto->{$field};
            }
        }

        return $subjectInfo;
    }

    /** 构造电商收付通二级商户入驻参数。 */
    public function buildEcommerceApplyment(EcommerceApplymentDto $dto): array
    {
        $dto->validate();

        return $this->filter(array_replace($dto->extra, [
            'out_request_no' => $dto->out_request_no,
            'organization_type' => $dto->organization_type,
            'finance_institution' => $dto->finance_institution,
            'business_license_info' => $dto->business_license_info,
            'finance_institution_info' => $dto->finance_institution_info,
            'id_holder_type' => $dto->id_holder_type,
            'id_doc_type' => $dto->id_doc_type,
            'authorize_letter_copy' => $dto->authorize_letter_copy,
            'id_card_info' => $dto->id_card_info,
            'id_doc_info' => $dto->id_doc_info,
            'owner' => $dto->owner,
            'account_info' => $dto->account_info,
            'contact_info' => $dto->contact_info,
            'sales_scene_info' => $dto->sales_scene_info,
            'settlement_info' => $dto->settlement_info,
            'merchant_shortname' => $dto->merchant_shortname,
            'qualifications' => $dto->qualifications,
            'business_addition_pics' => $dto->business_addition_pics,
            'business_addition_desc' => $dto->business_addition_desc,
            'ubo_info_list' => $dto->ubo_info_list,
        ]));
    }

    /** 构造修改特约商户或二级商户结算账户的参数。 */
    public function buildSettlement(SettlementDto $dto): array
    {
        $dto->validate();

        return $this->filter(array_replace($dto->extra, [
            'account_type' => $dto->account_type,
            'account_bank' => $dto->account_bank,
            'bank_name' => $dto->bank_name,
            'bank_branch_id' => $dto->bank_branch_id,
            'account_number' => $dto->account_number,
            'account_name' => $dto->account_name,
        ]));
    }

    /** 移除请求顶层的空选填字段，保留 false 等有效值。 */
    private function filter(array $payload): array
    {
        return array_filter(
            $payload,
            static fn (mixed $value): bool => $value !== ''
                && $value !== null
                && $value !== [],
        );
    }
}
