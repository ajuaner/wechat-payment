<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Partner;

use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Payment\PaymentRefundDto;

/** Refund request for service-provider and platform ecommerce transactions. */
class PartnerRefundDto extends PaymentRefundDto
{
    public string $sub_mchid = '';
    public string $sub_appid = '';
    /** E-commerce refund funding merchant. */
    public string $refund_account = '';

    /** 校验服务商普通退款或电商收付通退款字段。 */
    public function validate(bool $requireSubMchid = true): void
    {
        parent::validate();

        if ($requireSubMchid && $this->sub_mchid === '') {
            throw new PaymentException(
                'PartnerRefundDto [sub_mchid] is required.'
            );
        }

        if (strlen($this->sub_mchid) > 32) {
            throw new PaymentException(
                'PartnerRefundDto [sub_mchid] must not exceed 32 bytes.'
            );
        }

        if (strlen($this->sub_appid) > 32) {
            throw new PaymentException(
                'PartnerRefundDto [sub_appid] must not exceed 32 bytes.'
            );
        }

        if (strlen($this->refund_account) > 32) {
            throw new PaymentException(
                'PartnerRefundDto [refund_account] must not exceed 32 bytes.'
            );
        }
    }
}
