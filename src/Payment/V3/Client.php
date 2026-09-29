<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Payment\V3;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
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
    /** 创建 V3 普通商户支付客户端。 */
    public function __construct(
        PaymentConfig $config,
        ApplicationContract $application,
        private Builder $builder,
    ) {
        parent::__construct($config, $application);
    }

    /** 发起 V3 H5 支付。 */
    public function h5(AbstractPaymentDto $dto): array
    {
        return $this->requestPayment(
            $dto,
            PaymentEndpoints::V3_TRANSACTION_H5,
        );
    }
    /** 发起 V3 APP 支付并生成客户端调起参数。 */
    public function app(AbstractPaymentDto $dto): array
    {
        $paymentPrepare = $this->requestPayment(
            $dto,
            PaymentEndpoints::V3_TRANSACTION_APP,
        );

        if (! isset($paymentPrepare['prepay_id'])) {
            return $paymentPrepare;
        }

        return $this->utils()->buildAppConfig(
            (string) $paymentPrepare['prepay_id'],
            $this->config->app_id,
        );
    }
    /** 发起 V3 公众号或小程序支付并生成客户端调起参数。 */
    public function jsapi(AbstractPaymentDto $dto): array
    {
        $paymentPrepare = $this->requestPayment(
            $dto,
            PaymentEndpoints::V3_TRANSACTION_JSAPI,
        );

        if (! isset($paymentPrepare['prepay_id'])) {
            return $paymentPrepare;
        }

        if (
            $dto instanceof PaymentDto
            && $dto->payType() === PayType::MINI_PROGRAM
        ) {
            return $this->utils()->buildMiniAppConfig(
                (string) $paymentPrepare['prepay_id'],
                $this->config->app_id,
            );
        }

        return $this->utils()->buildSdkConfig(
            (string) $paymentPrepare['prepay_id'],
            $this->config->app_id,
        );
    }
    /** 发起 V3 Native 二维码支付。 */
    public function native(AbstractPaymentDto $dto): array
    {
        return $this->requestPayment(
            $dto,
            PaymentEndpoints::V3_TRANSACTION_NATIVE,
        );
    }
    /** V3 不支持付款码支付，调用时抛出明确异常。 */
    public function micropay(AbstractPaymentDto $dto): array
    {
        throw new PaymentException(
            'Wechat payment code payment is only available through the V2 driver.'
        );
    }

    /** 按商户订单号查询 V3 普通支付订单。 */
    public function queryByOutTradeNo(string $outTradeNo): array
    {
        return $this->getJson(
            $this->uri(
                PaymentEndpoints::V3_TRANSACTION_OUT_TRADE_NO,
                ['out_trade_no' => $this->outTradeNo($outTradeNo)],
            ),
            ['mchid' => $this->config->mch_id],
        );
    }

    /** 按微信支付订单号查询 V3 普通支付订单。 */
    public function queryByTransactionId(string $transactionId): array
    {
        return $this->getJson(
            $this->uri(
                PaymentEndpoints::V3_TRANSACTION_ID,
                ['transaction_id' => $this->requiredValue(
                    $transactionId,
                    'transaction_id',
                    32,
                )],
            ),
            ['mchid' => $this->config->mch_id],
        );
    }

    /** 按商户订单号关闭 V3 普通支付订单。 */
    public function close(string $outTradeNo): array
    {
        return $this->postJson(
            $this->uri(
                PaymentEndpoints::V3_TRANSACTION_CLOSE,
                ['out_trade_no' => $this->outTradeNo($outTradeNo)],
            ),
            ['mchid' => $this->config->mch_id],
        );
    }

    /** V3 没有付款码支付查询接口。 */
    public function queryMicropay(string $outTradeNo): array
    {
        throw new PaymentException(
            'Wechat payment code payment query is only available through the V2 driver.'
        );
    }

    /** V3 没有付款码支付撤销接口。 */
    public function reverseMicropay(string $outTradeNo): array
    {
        throw new PaymentException(
            'Wechat payment code payment reverse is only available through the V2 driver.'
        );
    }
    /** 创建 EasyWeChat V3 签名和客户端参数工具。 */
    private function utils(): Utils
    {
        return new Utils($this->application->getMerchant());
    }

    /** 组装并发送 V3 普通商户支付请求，统一处理错误响应。 */
    private function requestPayment(
        AbstractPaymentDto $dto,
        string $endpoint,
    ): array
    {
        $payload = $this->builder->build($dto);
        $requireParams = [
            'appid' => $this->config->app_id,
            'mchid' => $this->config->mch_id,
        ];

        $params = array_merge($payload, $requireParams);
        $params['notify_url'] = $this->resolveNotifyUrl($params);

        $params = array_filter(
            $params,
            static fn ($value): bool => $value !== '' && $value !== null,
        );
        return $this->postJson($endpoint, $params);
    }

    /** 发送普通商户 V3 JSON POST 请求。 */
    private function postJson(string $endpoint, array $payload): array
    {
        $response = $this->application->getClient()->post(
            PaymentEndpoints::BASE_PAY_URL . $endpoint,
            ['json' => $payload],
        );

        return $this->decodeJsonResponse($response);
    }

    /** 发送普通商户 V3 GET 请求。 */
    private function getJson(string $endpoint, array $query = []): array
    {
        $options = $query === [] ? [] : ['query' => $query];
        $response = $this->application->getClient()->get(
            PaymentEndpoints::BASE_PAY_URL . $endpoint,
            $options,
        );

        return $this->decodeJsonResponse($response);
    }

    /** 解析 V3 JSON 响应并兼容关单的 204 空响应。 */
    private function decodeJsonResponse(object $response): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode === 204) {
            return [];
        }

        if ($statusCode >= 400) {
            $data = $response->toArray(false);
            $code = is_string($data['code'] ?? null)
                ? $data['code']
                : 'UNKNOWN_ERROR';
            $message = is_string($data['message'] ?? null)
                ? $data['message']
                : 'Wechat Pay returned an unsuccessful response.';

            throw new PaymentException(
                "Wechat Pay API V3 request failed ({$statusCode}) [{$code}]: {$message}"
            );
        }

        return $response->toArray(false);
    }

    /** 替换并编码 V3 接口路径中的参数。 */
    private function uri(string $template, array $params): string
    {
        foreach ($params as $key => $value) {
            $template = str_replace(
                '{' . $key . '}',
                rawurlencode((string) $value),
                $template,
            );
        }

        return $template;
    }

    /** 校验并返回 V3 商户订单号。 */
    private function outTradeNo(string $outTradeNo): string
    {
        $outTradeNo = trim($outTradeNo);
        if (! preg_match('/^[0-9A-Za-z_\-|*]{6,32}$/D', $outTradeNo)) {
            throw new PaymentException(
                'Wechat Pay V3 [out_trade_no] must be 6-32 valid characters.'
            );
        }

        return $outTradeNo;
    }

    /** 校验并返回 V3 订单接口必填字符串。 */
    private function requiredValue(
        string $value,
        string $field,
        int $maxLength,
    ): string {
        $value = trim($value);
        if ($value === '') {
            throw new PaymentException(
                "Wechat Pay V3 [{$field}] is required."
            );
        }

        if (strlen($value) > $maxLength) {
            throw new PaymentException(
                "Wechat Pay V3 [{$field}] must not exceed {$maxLength} bytes."
            );
        }

        return $value;
    }

}
