<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Combine;

use DateTimeImmutable;
use Qinii\WechatPayment\Enum\PayMode;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentDto;

final class CombineDto extends AbstractPaymentDto
{
    /** 使用 payment 或 partner 配置完成签名。 */
    public string $pay_mode = PayMode::PAYMENT;
    public string $combine_out_trade_no = '';
    public string $openid = '';
    public string $notify_url = '';
    public string $payer_client_ip = '';
    public string $device_id = '';
    public ?array $scene_info = null;
    public string $time_start = '';
    public string $time_expire = '';
    public string $pay_type = PayType::OFFICIAL;

    /** @var array<int, SubOrderDto> */
    public array $sub_orders = [];

    /** @var array<string, mixed> */
    public array $extra = [];

    /** 返回合单使用的普通商户或服务商模式。 */
    public function mode(): string
    {
        return $this->pay_mode;
    }

    /** 返回合单支付类型。 */
    public function payType(): string
    {
        return $this->pay_type;
    }

    /** 根据支付类型返回合单客户端方法名。 */
    public function method(): string
    {
        return PayType::paymentMethod($this->pay_type);
    }

    /** 返回合单支付对应的交易类型。 */
    public function tradeType(): string
    {
        return PayType::tradeType($this->pay_type);
    }

    /** 将子订单数组转换为 SubOrderDto 实例数组。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        if ($key !== 'sub_orders') {
            return parent::normalizeValue($key, $value);
        }

        if (! is_array($value)) {
            throw new PaymentException(
                'CombineDto [sub_orders] must be array.'
            );
        }

        return array_map(
            static function (mixed $subOrder): SubOrderDto {
                if ($subOrder instanceof SubOrderDto) {
                    return $subOrder;
                }

                if (! is_array($subOrder)) {
                    throw new PaymentException(
                        'CombineDto [sub_orders] items must be arrays or SubOrderDto instances.'
                    );
                }

                return SubOrderDto::fromArray($subOrder);
            },
            array_values($value),
        );
    }

    /** 校验合单模式、支付场景和 2-50 个子订单。 */
    public function validate(): void
    {
        if (! in_array($this->pay_mode, [PayMode::PAYMENT, PayMode::PARTNER], true)) {
            throw new PaymentException(
                'CombineDto [pay_mode] must be payment or partner.'
            );
        }

        if (! in_array($this->pay_type, [
            PayType::OFFICIAL,
            PayType::MINI_PROGRAM,
            PayType::APP,
            PayType::H5,
            PayType::NATIVE,
        ], true)) {
            throw new UnsupportedModeException(
                "Wechat Pay API v3 combined {$this->pay_type} payment is not supported."
            );
        }

        if (! preg_match(
            '/^[0-9A-Za-z_\-|*]{2,32}$/D',
            $this->combine_out_trade_no,
        )) {
            throw new PaymentException(
                'CombineDto [combine_out_trade_no] must be 2-32 valid characters.'
            );
        }

        if (
            in_array(
                $this->pay_type,
                [PayType::OFFICIAL, PayType::MINI_PROGRAM],
                true,
            )
            && $this->openid === ''
        ) {
            throw new PaymentException(
                'CombineDto [openid] is required for JSAPI payment.'
            );
        }

        if (strlen($this->openid) > 128) {
            throw new PaymentException(
                'CombineDto [openid] must not exceed 128 bytes.'
            );
        }

        if (strlen($this->device_id) > 32) {
            throw new PaymentException(
                'CombineDto [device_id] must not exceed 32 bytes.'
            );
        }

        foreach (['time_start', 'time_expire'] as $field) {
            if (
                $this->{$field} !== ''
                && DateTimeImmutable::createFromFormat(
                    'Y-m-d\\TH:i:sP',
                    $this->{$field},
                ) === false
            ) {
                throw new PaymentException(
                    "CombineDto [{$field}] must use RFC3339 format."
                );
            }
        }

        $count = count($this->sub_orders);

        if ($count < 2 || $count > 50) {
            throw new PaymentException(
                'CombineDto [sub_orders] must contain 2-50 orders.'
            );
        }

        $outTradeNos = [];

        foreach ($this->sub_orders as $subOrder) {
            if (! $subOrder instanceof SubOrderDto) {
                throw new PaymentException(
                    'CombineDto [sub_orders] items must be SubOrderDto instances.'
                );
            }

            $subOrder->validate();

            if (isset($outTradeNos[$subOrder->out_trade_no])) {
                throw new PaymentException(
                    "CombineDto contains duplicate sub order [{$subOrder->out_trade_no}]."
                );
            }

            $outTradeNos[$subOrder->out_trade_no] = true;
        }
    }
}
