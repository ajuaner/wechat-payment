<?php
declare(strict_types=1);

namespace Qinii\WechatPayment\Partner;

use Qinii\WechatPayment\Enum\PayMode;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentDto;

class PartnerDto extends AbstractPaymentDto
{
    // 子商户号
    public string $sub_mchid = '';
    // 商品描述
    public string $description = '';
    // 商户订单号
    public string $out_trade_no = '';
    // 订单金额，单位为分
    public int $total_fee = 0;
    // 子商户 APPID
    public string $sub_appid = '';
    // 用户在服务商 sp_appid 下的 openid，V3 Builder 映射为 payer.sp_openid
    public string $openid = '';
    // 用户在子商户 sub_appid 下的 openid
    public string $sub_openid = '';
    // 通知地址
    public string $notify_url = '';
    // 商品详情
    public ?array $detail = null;
    // 附加数据，在查询 API 和支付通知中原样返回
    public string $attach = '';
    // 标价币种
    public string $fee_type = 'CNY';
    // 支付类型
    public string $pay_type = PayType::OFFICIAL;
    // 交易结束时间：V2 为 yyyyMMddHHmmss，V3 为 RFC3339
    public string $time_expire = '';
    // 终端 IP，V2 支付优先使用该值，为空时使用服务商配置的 spbill_create_ip
    public string $payer_client_ip = '';
    // 分账信息：Y 需要分账，N 不分账
    public string $profit_sharing = 'N';
    // 商品标签
    public string $goods_tag = '';
    // 是否支持发票
    public bool $support_fapiao = false;
    // 场景信息
    public ?array $scene_info = null;
    // 结算信息
    public ?array $settle_info = null;
    // 协议扩展参数，核心字段由 Builder 生成且不可被覆盖
    public array $extra = [];

    /**
     * V2 服务商支付专属参数。
     */
    // 交易起始时间，格式为 yyyyMMddHHmmss
    public string $time_start = '';
    // Native 支付使用的商品 ID
    public string $product_id = '';
    // 付款码支付使用的授权码
    public string $auth_code = '';

    /** 返回服务商支付模式。 */
    public function mode(): string
    {
        return PayMode::PARTNER;
    }

    /** 返回 DTO 中配置的支付类型。 */
    public function payType(): string
    {
        return $this->pay_type;
    }

    /** 将支付类型转换为客户端方法名。 */
    public function method(): string
    {
        return PayType::paymentMethod($this->pay_type);
    }

    /** 将支付类型转换为微信支付交易类型。 */
    public function tradeType(): string
    {
        return PayType::tradeType($this->pay_type);
    }


    /** 对服务商 DTO 字段做类型和枚举标准化。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        if ($key === 'support_fapiao') {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        if ($key === 'total_fee') {
            return (int) $value;
        }

        if ($key === 'profit_sharing') {
            if (is_bool($value)) {
                return $value ? 'Y' : 'N';
            }

            return strtoupper(trim((string) $value));
        }

        return parent::normalizeValue($key, $value);
    }

    /** 校验服务商支付的子商户、金额和付款人信息。 */
    public function validate(): void
    {
        $this->requireFields([
            'sub_mchid',
            'description',
            'out_trade_no',
            'total_fee',
            'pay_type',
        ]);

        if ((int) $this->total_fee <= 0) {
            throw new PaymentException(
                'PartnerDto [total_fee] must be greater than 0.'
            );
        }

        if (! preg_match('/^[0-9A-Za-z_\-|*]{6,32}$/D', $this->out_trade_no)) {
            throw new PaymentException(
                'PartnerDto [out_trade_no] must be 6-32 valid characters.'
            );
        }

        if (
            $this->payer_client_ip !== ''
            && filter_var($this->payer_client_ip, FILTER_VALIDATE_IP) === false
        ) {
            throw new PaymentException(
                'PartnerDto [payer_client_ip] must be a valid IPv4 or IPv6 address.'
            );
        }

        match ($this->pay_type) {
            PayType::OFFICIAL,
            PayType::MINI_PROGRAM => $this->validatePayer(),
            PayType::NATIVE,
            PayType::MICROPAY,
            PayType::APP,
            PayType::H5 => null,
            default => throw new PaymentException(
                "Unsupported pay type: {$this->pay_type}"
            ),
        };
    }

    /** 校验 JSAPI/小程序支付所需的服务商或子商户用户标识。 */
    private function validatePayer(): void
    {
        if ($this->openid === '' && $this->sub_openid === '') {
            throw new PaymentException(
                'PartnerDto [openid] or [sub_openid] is required.'
            );
        }

        if ($this->sub_openid !== '' && $this->sub_appid === '') {
            throw new PaymentException(
                'PartnerDto [sub_appid] is required when [sub_openid] is used.'
            );
        }
    }

    /** 校验 DTO 中指定的必填字段。 */
    private function requireFields(array $fields): void
    {
        foreach ($fields as $field) {
            if (
                !property_exists($this, $field)
                || $this->$field === ''
                || $this->$field === null
                || $this->$field === []
            ) {
                throw new PaymentException(
                    "PartnerDto [{$field}] is required."
                );
            }
        }
    }
}
