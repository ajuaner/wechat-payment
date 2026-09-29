<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Payment\V2;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use EasyWeChat\Pay\LegacySignature;
use EasyWeChat\Pay\Utils;
use Qinii\WechatPayment\Config\PaymentConfig;
use Qinii\WechatPayment\Payment\PaymentDto;
use Qinii\WechatPayment\Endpoints\PaymentEndpoints;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentClient;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentDto;

class Client extends AbstractPaymentClient
{
    private PaymentConfig $paymentConfig;

    /** 创建 V2 普通商户支付客户端。 */
    public function __construct(
        PaymentConfig $config,
        ApplicationContract $application,
        private Builder $builder,
    ) {
        $this->paymentConfig = $config;
        parent::__construct($config, $application);
    }

    /** 调用 V2 统一下单接口，供 H5、APP、JSAPI 和 Native 共用。 */
    private function requestUnifiedOrder(AbstractPaymentDto $dto): array
    {
        $payload = $this->builder->build($dto);
        $dto = $this->paymentDto($dto);
        $requireParams = [
            'appid' => $this->config->app_id,
            'mch_id' => $this->config->mch_id,
            'spbill_create_ip' => $this->resolveClientIp($dto),
            'nonce_str' => bin2hex(random_bytes(16)),
        ];

        $params = array_merge($payload, $requireParams);
        $params['notify_url'] = $this->resolveNotifyUrl($params);

        return $this->post(
            PaymentEndpoints::V2_UNIFIED_ORDER,
            $params,
        );
    }

    /** 调用 V2 付款码支付接口。 */
    private function requestMicropay(AbstractPaymentDto $dto): array
    {
        $payload = $this->builder->buildMicropay($dto);
        $dto = $this->paymentDto($dto);

        return $this->post(PaymentEndpoints::V2_MICROPAY, array_merge(
            $payload,
            [
                'appid' => $this->config->app_id,
                'mch_id' => $this->config->mch_id,
                'spbill_create_ip' => $this->resolveClientIp($dto),
                'nonce_str' => bin2hex(random_bytes(16)),
            ],
        ));
    }

    /** 发送 V2 XML 请求并返回微信支付响应。 */
    private function post(
        string $endpoint,
        array $params,
        bool $requiresClientCertificate = false,
    ): array
    {
        $params = array_filter(
            $params,
            static fn ($value): bool => $value !== '' && $value !== null,
        );

        $options = ['xml' => $params];
        if ($requiresClientCertificate) {
            if ($this->config->certificate === '' || $this->config->private_key === '') {
                throw new PaymentException(
                    'Wechat Pay API V2 reverse requires certificate and private_key.'
                );
            }

            $options['local_cert'] = $this->config->certificate;
            $options['local_pk'] = $this->config->private_key;
        }

        return $this->application
            ->getClient()
            ->post(
                PaymentEndpoints::BASE_PAY_URL . $endpoint,
                $options,
            )
            ->toArray();
    }

    /** 按商户订单号查询 V2 普通支付订单。 */
    public function queryByOutTradeNo(string $outTradeNo): array
    {
        return $this->post(
            PaymentEndpoints::V2_ORDER_QUERY,
            $this->orderParams([
                'out_trade_no' => $this->outTradeNo($outTradeNo),
            ]),
        );
    }

    /** 按微信支付订单号查询 V2 普通支付订单。 */
    public function queryByTransactionId(string $transactionId): array
    {
        return $this->post(
            PaymentEndpoints::V2_ORDER_QUERY,
            $this->orderParams([
                'transaction_id' => $this->requiredValue(
                    $transactionId,
                    'transaction_id',
                    32,
                ),
            ]),
        );
    }

    /** 按商户订单号关闭 V2 普通支付订单。 */
    public function close(string $outTradeNo): array
    {
        return $this->post(
            PaymentEndpoints::V2_CLOSE_ORDER,
            $this->orderParams([
                'out_trade_no' => $this->outTradeNo($outTradeNo),
            ]),
        );
    }

    /** 查询 V2 付款码支付订单，底层复用订单查询接口。 */
    public function queryMicropay(string $outTradeNo): array
    {
        return $this->queryByOutTradeNo($outTradeNo);
    }

    /** 撤销 V2 付款码支付订单，请求时使用商户 API 证书。 */
    public function reverseMicropay(string $outTradeNo): array
    {
        return $this->post(
            PaymentEndpoints::V2_REVERSE_ORDER,
            $this->orderParams([
                'out_trade_no' => $this->outTradeNo($outTradeNo),
            ]),
            true,
        );
    }

    /** 发起 V2 H5 支付。 */
    public function h5(AbstractPaymentDto $dto): array
    {
        return $this->requestUnifiedOrder($dto);
    }
    /** 发起 V2 APP 支付并生成客户端调起参数。 */
    public function app(AbstractPaymentDto $dto): array
    {
        $paymentPrepare = $this->requestUnifiedOrder($dto);

        if (! isset($paymentPrepare['prepay_id'])) {
            return $paymentPrepare;
        }

        $params = [
            'appid' => $this->config->app_id,
            'partnerid' => $this->config->mch_id,
            'prepayid' => (string) $paymentPrepare['prepay_id'],
            'package' => 'Sign=WXPay',
            'noncestr' => bin2hex(random_bytes(16)),
            'timestamp' => time(),
        ];
        // 使用 EasyWeChat 旧版签名器签名 APP 字段，并禁止额外生成 nonce_str。
        return (new LegacySignature($this->application->getMerchant()))->sign(
            $params + ['nonce_str' => ''],
        );
    }
    /** 发起 V2 公众号或小程序支付并生成客户端调起参数。 */
    public function jsapi(AbstractPaymentDto $dto): array
    {
        $paymentPrepare = $this->requestUnifiedOrder($dto);

        if (! isset($paymentPrepare['prepay_id'])) {
            return $paymentPrepare;
        }

        $dto = $this->paymentDto($dto);

        if ($dto->payType() === PayType::MINI_PROGRAM) {
            return $this->utils()->buildMiniAppConfig(
                (string) $paymentPrepare['prepay_id'],
                $this->config->app_id,
                'MD5',
            );
        }

        return $this->utils()->buildSdkConfig(
            (string) $paymentPrepare['prepay_id'],
            $this->config->app_id,
            'MD5',
        );
    }
    /** 发起 V2 Native 二维码支付。 */
    public function native(AbstractPaymentDto $dto): array
    {
        return $this->requestUnifiedOrder($dto);
    }
    /** 发起 V2 付款码支付。 */
    public function micropay(AbstractPaymentDto $dto): array
    {
        return $this->requestMicropay($dto);
    }
    /** 解析并校验 V2 请求使用的客户端 IP。 */
    private function resolveClientIp(PaymentDto $dto): string
    {
        $clientIp = trim(
            $dto->payer_client_ip !== ''
                ? $dto->payer_client_ip
                : (string) $this->paymentConfig->spbill_create_ip
        );

        if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            throw new PaymentException(
                'Wechat V2 payment requires a valid payer_client_ip or spbill_create_ip.'
            );
        }

        return $clientIp;
    }

    /** 确认当前客户端接收的是普通支付 PaymentDto。 */
    private function paymentDto(AbstractPaymentDto $dto): PaymentDto
    {
        if (! $dto instanceof PaymentDto) {
            throw new PaymentException(
                'V2Payment only supports PaymentDto.'
            );
        }

        return $dto;
    }

    /** 组装 V2 订单操作共用的商户身份和随机串。 */
    private function orderParams(array $params): array
    {
        return array_merge($params, [
            'appid' => $this->config->app_id,
            'mch_id' => $this->config->mch_id,
            'nonce_str' => bin2hex(random_bytes(16)),
        ]);
    }

    /** 校验并返回 V2 商户订单号。 */
    private function outTradeNo(string $outTradeNo): string
    {
        $outTradeNo = trim($outTradeNo);
        if (! preg_match('/^[0-9A-Za-z_\-|*]{6,32}$/D', $outTradeNo)) {
            throw new PaymentException(
                'Wechat Pay V2 [out_trade_no] must be 6-32 valid characters.'
            );
        }

        return $outTradeNo;
    }

    /** 校验并返回 V2 订单接口必填字符串。 */
    private function requiredValue(
        string $value,
        string $field,
        int $maxLength,
    ): string {
        $value = trim($value);
        if ($value === '') {
            throw new PaymentException(
                "Wechat Pay V2 [{$field}] is required."
            );
        }

        if (strlen($value) > $maxLength) {
            throw new PaymentException(
                "Wechat Pay V2 [{$field}] must not exceed {$maxLength} bytes."
            );
        }

        return $value;
    }

    /** 创建 EasyWeChat V2 签名和客户端参数工具。 */
    private function utils(): Utils
    {
        return new Utils($this->application->getMerchant());
    }
}
