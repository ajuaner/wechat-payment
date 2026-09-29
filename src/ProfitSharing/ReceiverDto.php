<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\ProfitSharing;

use Qinii\WechatPayment\Shared\Traits\Fillable;

/** 分账接收方数据。 */
class ReceiverDto
{
    use Fillable;

    /** 接收方类型。 */
    public string $type = '';
    /** 接收方账号。 */
    public string $account = '';
    /** 接收方名称，涉及敏感信息时由 EasyWeChat 加密。 */
    public string $name = '';
    /** 分账金额，单位为分。 */
    public int $amount = 0;
    /** 分账描述。 */
    public string $description = '';
    /** 与分账方的关系类型。 */
    public string $relation_type = '';
    /** 自定义关系描述。 */
    public string $custom_relation = '';
    /** 协议扩展参数。 */
    public array $extra = [];

    /** 标准化接收方金额和扩展参数。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'amount' => (int) $value,
            'extra' => is_array($value) ? $value : [],
            default => $value,
        };
    }
}
