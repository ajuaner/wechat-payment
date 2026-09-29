<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\ProfitSharing;

use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\Traits\Fillable;

/** 分账单及其查询、完结操作使用的数据。 */
class ProfitSharingDto
{
    use Fillable;

    /** 微信支付订单号。 */
    public string $transaction_id = '';
    /** 商户分账单号。 */
    public string $out_order_no = '';
    /** 服务商模式下的子商户号。 */
    public string $sub_mchid = '';
    /** 服务商模式下的子商户 AppID。 */
    public string $sub_appid = '';
    /** V2 品牌主商户号。 */
    public string $brand_mch_id = '';
    /** 完结或解冻剩余资金的原因。 */
    public string $description = '';
    /** 本次分账后是否完结并解冻剩余资金。 */
    public bool $finish = false;
    /** @var array<int, ReceiverDto> 分账接收方列表。 */
    public array $receivers = [];
    /** 协议扩展参数。 */
    public array $extra = [];

    /** 标准化完结标识、接收方列表和扩展参数。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'finish' => filter_var($value, FILTER_VALIDATE_BOOL),
            'receivers' => $this->normalizeReceivers($value),
            'extra' => is_array($value) ? $value : [],
            default => $value,
        };
    }

    /** @return array<int, ReceiverDto> */
    private function normalizeReceivers(mixed $value): array
    {
        if (! is_array($value)) {
            throw new PaymentException(
                'ProfitSharingDto [receivers] must be array.'
            );
        }

        return array_map(
            static function (mixed $receiver): ReceiverDto {
                if ($receiver instanceof ReceiverDto) {
                    return $receiver;
                }

                if (! is_array($receiver)) {
                    throw new PaymentException(
                        'ProfitSharingDto [receivers] items must be arrays or ReceiverDto instances.'
                    );
                }

                return ReceiverDto::fromArray($receiver);
            },
            array_values($value),
        );
    }
}
