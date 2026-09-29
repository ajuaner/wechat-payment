<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Combine\V3;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use EasyWeChat\Pay\Utils;
use Qinii\WechatPayment\Combine\CombineDto;
use Qinii\WechatPayment\Combine\SubOrderDto;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Endpoints\PaymentEndpoints;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentClient;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentDto;

final class Client extends AbstractPaymentClient
{
    /** 创建 V3 合单支付客户端。 */
    public function __construct(
        AbstractWechatPayConfig $config,
        ApplicationContract $application,
        private Builder $builder,
    ) {
        parent::__construct($config, $application);
    }

    /** 发起 V3 合单公众号或小程序支付。 */
    public function jsapi(AbstractPaymentDto $dto): array
    {
        $dto = $this->combineDto($dto);
        $paymentPrepare = $this->requestPayment(
            $dto,
            PaymentEndpoints::V3_COMBINE_TRANSACTION_JSAPI,
        );

        if (! isset($paymentPrepare['prepay_id'])) {
            return $paymentPrepare;
        }

        if ($dto->payType() === PayType::MINI_PROGRAM) {
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

    /** 发起 V3 合单 H5 支付。 */
    public function h5(AbstractPaymentDto $dto): array
    {
        return $this->requestPayment(
            $this->combineDto($dto),
            PaymentEndpoints::V3_COMBINE_TRANSACTION_H5,
        );
    }

    /** 发起 V3 合单 APP 支付。 */
    public function app(AbstractPaymentDto $dto): array
    {
        $paymentPrepare = $this->requestPayment(
            $this->combineDto($dto),
            PaymentEndpoints::V3_COMBINE_TRANSACTION_APP,
        );

        if (! isset($paymentPrepare['prepay_id'])) {
            return $paymentPrepare;
        }

        return $this->utils()->buildAppConfig(
            (string) $paymentPrepare['prepay_id'],
            $this->config->app_id,
        );
    }

    /** 发起 V3 合单 Native 二维码支付。 */
    public function native(AbstractPaymentDto $dto): array
    {
        return $this->requestPayment(
            $this->combineDto($dto),
            PaymentEndpoints::V3_COMBINE_TRANSACTION_NATIVE,
        );
    }

    /** 按合单商户订单号查询 V3 合单订单。 */
    public function queryByOutTradeNo(string $combineOutTradeNo): array
    {
        return $this->getJson(
            $this->uri(
                PaymentEndpoints::V3_COMBINE_TRANSACTION_OUT_TRADE_NO,
                ['combine_out_trade_no' => $this->combineOutTradeNo(
                    $combineOutTradeNo,
                )],
            ),
            ['combine_mchid' => $this->config->mch_id],
        );
    }

    /**
     * 按合单商户订单号关闭 V3 合单订单。
     *
     * @param array<int, array<string, mixed>|SubOrderDto> $subOrders
     */
    public function close(string $combineOutTradeNo, array $subOrders): array
    {
        return $this->postJson(
            $this->uri(
                PaymentEndpoints::V3_COMBINE_TRANSACTION_CLOSE,
                ['combine_out_trade_no' => $this->combineOutTradeNo(
                    $combineOutTradeNo,
                )],
            ),
            [
                'combine_appid' => $this->config->app_id,
                'sub_orders' => $this->closeSubOrders($subOrders),
            ],
        );
    }

    /** 合单 V3 暂不支持付款码支付。 */
    public function micropay(AbstractPaymentDto $dto): array
    {
        return $this->unsupported('micropay');
    }

    /** 组装并发送 V3 合单请求，统一处理错误响应。 */
    private function requestPayment(CombineDto $dto, string $endpoint): array
    {
        $params = $this->builder->build($dto);
        $params['notify_url'] = $this->resolveNotifyUrl($params);

        if (strtolower((string) parse_url(
            $params['notify_url'],
            PHP_URL_SCHEME,
        )) !== 'https') {
            throw new PaymentException(
                'Wechat Pay API V3 combined [notify_url] must use HTTPS.'
            );
        }

        return $this->postJson($endpoint, $params);
    }

    /** 发送合单 V3 JSON POST 请求。 */
    private function postJson(string $endpoint, array $payload): array
    {
        $response = $this->application->getClient()->post(
            PaymentEndpoints::BASE_PAY_URL . $endpoint,
            ['json' => $payload],
        );

        return $this->decodeJsonResponse($response);
    }

    /** 发送合单 V3 GET 请求。 */
    private function getJson(string $endpoint, array $query = []): array
    {
        $options = $query === [] ? [] : ['query' => $query];
        $response = $this->application->getClient()->get(
            PaymentEndpoints::BASE_PAY_URL . $endpoint,
            $options,
        );

        return $this->decodeJsonResponse($response);
    }

    /** 解析合单 V3 JSON 响应并兼容关单的 204 空响应。 */
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
                "Wechat Pay API V3 combined request failed ({$statusCode}) [{$code}]: {$message}"
            );
        }

        return $response->toArray(false);
    }

    /** 为合单接口不支持的支付类型抛出统一异常。 */
    private function unsupported(string $payType): array
    {
        throw new UnsupportedModeException(
            "Wechat Pay API v3 combined payment does not support {$payType}."
        );
    }

    /** 创建 EasyWeChat V3 签名和客户端参数工具。 */
    private function utils(): Utils
    {
        return new Utils($this->application->getMerchant());
    }

    /** 确认合单客户端接收的是 CombineDto。 */
    private function combineDto(AbstractPaymentDto $dto): CombineDto
    {
        if (! $dto instanceof CombineDto) {
            throw new PaymentException(
                'V3 combined client only supports CombineDto.'
            );
        }

        return $dto;
    }

    /** 替换并编码合单接口路径中的参数。 */
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

    /** 按普通商户或服务商协议校验合单商户订单号。 */
    private function combineOutTradeNo(string $combineOutTradeNo): string
    {
        $combineOutTradeNo = trim($combineOutTradeNo);
        $minimum = $this->config instanceof PartnerConfig ? 2 : 6;
        if (! preg_match(
            "/^[0-9A-Za-z_\\-|*]{{$minimum},32}$/D",
            $combineOutTradeNo,
        )) {
            throw new PaymentException(
                "Wechat Pay combined [combine_out_trade_no] must be {$minimum}-32 valid characters."
            );
        }

        return $combineOutTradeNo;
    }

    /**
     * 组装关单接口所需的商品单列表。
     *
     * 普通商户只发送 mchid 和 out_trade_no；服务商模式还要求
     * sub_mchid，并按需发送 sub_appid。
     *
     * @param array<int, array<string, mixed>|SubOrderDto> $subOrders
     * @return array<int, array<string, string>>
     */
    private function closeSubOrders(array $subOrders): array
    {
        $subOrders = array_values($subOrders);
        $maximum = $this->config instanceof PartnerConfig ? 50 : 10;
        $count = count($subOrders);

        if ($count < 1 || $count > $maximum) {
            throw new PaymentException(
                "Wechat Pay combined close [sub_orders] must contain 1-{$maximum} orders."
            );
        }

        $result = [];
        $identities = [];

        foreach ($subOrders as $index => $subOrder) {
            if ($subOrder instanceof SubOrderDto) {
                $subOrder = [
                    'mchid' => $subOrder->mchid,
                    'out_trade_no' => $subOrder->out_trade_no,
                    'sub_mchid' => $subOrder->sub_mchid,
                    'sub_appid' => $subOrder->sub_appid,
                ];
            }

            if (! is_array($subOrder)) {
                throw new PaymentException(
                    "Wechat Pay combined close [sub_orders.{$index}] must be an array or SubOrderDto."
                );
            }

            $item = [
                'mchid' => $this->closeValue(
                    $subOrder['mchid'] ?? null,
                    "sub_orders.{$index}.mchid",
                    32,
                ),
                'out_trade_no' => $this->closeOutTradeNo(
                    $subOrder['out_trade_no'] ?? null,
                    $index,
                ),
            ];

            if ($this->config instanceof PartnerConfig) {
                $item['sub_mchid'] = $this->closeValue(
                    $subOrder['sub_mchid'] ?? null,
                    "sub_orders.{$index}.sub_mchid",
                    32,
                );

                $subAppid = $subOrder['sub_appid'] ?? null;
                if ($subAppid !== null && $subAppid !== '') {
                    $item['sub_appid'] = $this->closeValue(
                        $subAppid,
                        "sub_orders.{$index}.sub_appid",
                        32,
                    );
                }
            }

            $identity = implode('|', [
                $item['mchid'],
                $item['out_trade_no'],
                $item['sub_mchid'] ?? '',
            ]);
            if (isset($identities[$identity])) {
                throw new PaymentException(
                    "Wechat Pay combined close contains duplicate sub order [{$item['out_trade_no']}]."
                );
            }

            $identities[$identity] = true;
            $result[] = $item;
        }

        return $result;
    }

    /** 校验关单商品单中的必填字符串。 */
    private function closeValue(
        mixed $value,
        string $field,
        int $maxLength,
    ): string {
        if (! is_string($value) && ! is_int($value)) {
            throw new PaymentException(
                "Wechat Pay combined close [{$field}] is required."
            );
        }

        $value = trim((string) $value);
        if ($value === '') {
            throw new PaymentException(
                "Wechat Pay combined close [{$field}] is required."
            );
        }

        if (strlen($value) > $maxLength) {
            throw new PaymentException(
                "Wechat Pay combined close [{$field}] must not exceed {$maxLength} bytes."
            );
        }

        return $value;
    }

    /** 校验关单商品单的商户订单号。 */
    private function closeOutTradeNo(mixed $outTradeNo, int $index): string
    {
        $field = "sub_orders.{$index}.out_trade_no";
        $outTradeNo = $this->closeValue($outTradeNo, $field, 32);

        if (! preg_match('/^[0-9A-Za-z_\-|*]{2,32}$/D', $outTradeNo)) {
            throw new PaymentException(
                "Wechat Pay combined close [{$field}] must be 2-32 valid characters."
            );
        }

        return $outTradeNo;
    }
}
