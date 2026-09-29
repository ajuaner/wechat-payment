<?php
declare(strict_types=1);

namespace Qinii\WechatPayment\Payment;

use Qinii\WechatPayment\Enum\PayMode;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentDto;

class PaymentDto extends AbstractPaymentDto
{
    //商品描述
    public string $description = '';
    //商品详情
    public ?array $detail = null;
    //订单金额 必须是最小单位分整数
    public int $total_fee = 0;
    //通知地址
    public string $notify_url = '';
    //支付类型
    public string $pay_type = PayType::OFFICIAL;
    //附加数据 在查询API和支付通知中原样返回，可作为自定义参数使用
    public string $attach = '';
    //商户订单号
    public string $out_trade_no = '';
    //标价币种
    public string $fee_type = 'CNY';
    //交易结束时间：V2 为 yyyyMMddHHmmss，V3 为 RFC3339
    public string $time_expire = '';
    //分账信息 Y-是，需要分账 N-否，不分账
    public string $profit_sharing = 'N';
    //用户openid，JSAPI支付必传,即: PayType::OFFICIAL, PayType::MINI_PROGRAM
    public string $openid = '';
    //终端IP。V2 映射为 spbill_create_ip，V3 H5 映射为 payer_client_ip
    public string $payer_client_ip = '';
    //协议扩展参数，核心字段由 Builder 生成且不可被覆盖
    public array $extra = [];

    /**
     *  v2 特有参数
     */
    // 交易起始时间 20091225091010
    public string $time_start = '';
    //商品ID v2支付 NATIVE,此参数必传
    public string $product_id = '';
    //支付授权码，MICROPAY支付必传
    public string $auth_code = '';

    //商品标签
    public string $goods_tag = '';
    /**
     *  v3 新增参数
     */
    //是否支持发票 Y-是 N-否
    public bool $support_fapiao = false;
    //场景信息
    public ?array $scene_info = null;
    //结算信息
    public ?array $settle_info = null;


    /**
     * 返回普通商户支付模式。
     *
     * @return string 普通商户模式标识
     */
    public function mode(): string
    {
        return PayMode::PAYMENT;
    }

    /** 标准化普通支付 DTO 的字段；通用字段沿用 Fillable 默认处理。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        return parent::normalizeValue($key, $value);
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

    /** 校验普通支付请求的通用字段和支付类型。 */
    public function validate(): void
    {
        $this->requireFields([
            'description',
            'out_trade_no',
            'total_fee',
            'pay_type',
        ]);

        if ((int) $this->total_fee <= 0) {
            throw new PaymentException('PaymentDto [total_fee] must be greater than 0.');
        }

        if (! preg_match('/^[0-9A-Za-z_\-|*]{6,32}$/D', $this->out_trade_no)) {
            throw new PaymentException(
                'PaymentDto [out_trade_no] must be 6-32 valid characters.'
            );
        }

        if (
            $this->payer_client_ip !== ''
            && filter_var($this->payer_client_ip, FILTER_VALIDATE_IP) === false
        ) {
            throw new PaymentException(
                'PaymentDto [payer_client_ip] must be a valid IPv4 or IPv6 address.'
            );
        }

        match ($this->pay_type) {
            PayType::OFFICIAL,
            PayType::MINI_PROGRAM,
            PayType::NATIVE,
            PayType::MICROPAY,
            PayType::APP,
            PayType::H5 => null,
            default => throw new PaymentException("Unsupported pay type: {$this->pay_type}"),
        };
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
                throw new PaymentException("PaymentDto [{$field}] is required.");
            }
        }
    }

}
