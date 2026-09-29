<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Combine;

use Qinii\WechatPayment\Exception\PaymentException;

final class SubOrderDto
{
    public string $mchid = '';
    public string $sub_mchid = '';
    public string $sub_appid = '';
    public string $attach = '';
    public int $total_amount = 0;
    public string $currency = 'CNY';
    public string $out_trade_no = '';
    public string $description = '';
    public ?array $settle_info = null;
    public string $goods_tag = '';
    public array $extra = [];

    /** 从数组创建一个合单子订单 DTO。 */
    public static function fromArray(array $data): self
    {
        $dto = new self();

        foreach ($data as $key => $value) {
            if (! property_exists($dto, $key)) {
                continue;
            }

            $dto->{$key} = match ($key) {
                'total_amount' => (int) $value,
                default => $value,
            };
        }

        return $dto;
    }

    /** 校验合单子订单的商户、金额、订单号和描述。 */
    public function validate(): void
    {
        $this->requireFields([
            'mchid',
            'attach',
            'total_amount',
            'out_trade_no',
            'description',
        ]);

        $this->validateLength($this->mchid, 32, 'mchid');
        $this->validateLength($this->sub_mchid, 32, 'sub_mchid');
        $this->validateLength($this->sub_appid, 32, 'sub_appid');
        $this->validateLength($this->attach, 128, 'attach');
        $this->validateLength($this->description, 127, 'description');
        $this->validateLength($this->goods_tag, 32, 'goods_tag');

        if ($this->total_amount <= 0) {
            throw new PaymentException(
                'SubOrderDto [total_amount] must be greater than 0.'
            );
        }

        if ($this->currency !== 'CNY') {
            throw new PaymentException(
                'SubOrderDto [currency] must be CNY.'
            );
        }

        if (! preg_match('/^[0-9A-Za-z_\-|*]{2,32}$/D', $this->out_trade_no)) {
            throw new PaymentException(
                'SubOrderDto [out_trade_no] must be 2-32 valid characters.'
            );
        }

        if (
            $this->settle_info !== null
            && array_key_exists('profit_sharing', $this->settle_info)
            && ! is_bool($this->settle_info['profit_sharing'])
        ) {
            throw new PaymentException(
                'SubOrderDto [settle_info.profit_sharing] must be boolean.'
            );
        }
    }

    /** 校验子订单中指定的必填字段。 */
    private function requireFields(array $fields): void
    {
        foreach ($fields as $field) {
            if (
                $this->{$field} === ''
                || $this->{$field} === null
                || $this->{$field} === []
                || $this->{$field} === 0
            ) {
                throw new PaymentException(
                    "SubOrderDto [{$field}] is required."
                );
            }
        }
    }

    /** 校验字段不超过微信支付接口规定的字节长度。 */
    private function validateLength(
        string $value,
        int $maxLength,
        string $field,
    ): void {
        if (strlen($value) > $maxLength) {
            throw new PaymentException(
                "SubOrderDto [{$field}] must not exceed {$maxLength} bytes."
            );
        }
    }
}
