<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\ProfitSharing;

use Qinii\WechatPayment\Shared\Traits\Fillable;

/** 分账回退及回退查询数据。 */
class ReturnDto
{
    use Fillable;

    /** 服务商模式下的子商户号。 */
    public string $sub_mchid = '';
    /** 服务商 V2 模式下的子商户 AppID。 */
    public string $sub_appid = '';
    /** 微信分账单号。 */
    public string $order_id = '';
    /** 商户分账单号。 */
    public string $out_order_no = '';
    /** 商户分账回退单号。 */
    public string $out_return_no = '';
    /** V3 回退接收商户号。 */
    public string $return_mchid = '';
    /** V2 回退接收方类型。 */
    public string $return_account_type = '';
    /** V2 回退接收方账号。 */
    public string $return_account = '';
    /** 回退金额，单位为分。 */
    public int $amount = 0;
    /** 回退描述。 */
    public string $description = '';
    /** 协议扩展参数。 */
    public array $extra = [];

    /** 标准化回退金额和扩展参数。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'amount' => (int) $value,
            'extra' => is_array($value) ? $value : [],
            default => $value,
        };
    }
}
