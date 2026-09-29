<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Refund\V3;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use EasyWeChat\Pay\Utils;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Endpoints\PaymentEndpoints;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Payment\PaymentRefundDto;
use Qinii\WechatPayment\Partner\PartnerRefundDto;
use Qinii\WechatPayment\Refund\AbnormalRefundDto;

final class Client
{
    /** 创建 V3 退款客户端，并保存配置用于收付通和异常退款。 */
    public function __construct(
        private ApplicationContract $application,
        private Builder $builder,
        private ?AbstractWechatPayConfig $config = null,
    ) {
    }

    /** 发起普通商户或服务商 V3 退款。 */
    public function refund(PaymentRefundDto|PartnerRefundDto $dto): array
    {
        return $this->postJson(
            PaymentEndpoints::V3_TRANSACTION_REFUND,
            $this->builder->build($dto),
        );
    }

    /** 发起电商收付通退款。 */
    public function ecommerceRefund(PartnerRefundDto $dto): array
    {
        $this->assertEcommerceConfig();

        return $this->postJson(
            PaymentEndpoints::V3_TRANSACTION_REFUND_APPLY,
            $this->builder->buildEcommerce($dto),
        );
    }

    /** 发起银行卡异常退款。 */
    public function abnormalRefund(AbnormalRefundDto $dto): array
    {
        return $this->postAbnormalJson(
            str_replace(
                '{refund_id}',
                rawurlencode($dto->refund_id),
                PaymentEndpoints::V3_TRANSACTION_REFUND_EXCEPTION,
            ),
            $this->builder->buildAbnormal($dto),
        );
    }

    /** 发起电商收付通银行卡异常退款。 */
    public function abnormalEcommerceRefund(AbnormalRefundDto $dto): array
    {
        $this->assertEcommerceConfig();

        return $this->postAbnormalJson(
            str_replace(
                '{refund_id}',
                rawurlencode($dto->refund_id),
                PaymentEndpoints::V3_TRANSACTION_REFUND_EXCEPTION_APPLY,
            ),
            $this->builder->buildAbnormal($dto),
        );
    }

    /** 按商户退款单号查询普通商户或服务商退款。 */
    public function query(string $outRefundNo, ?string $subMchid = null): array
    {
        if ($outRefundNo === '') {
            throw new PaymentException('Refund query requires out_refund_no.');
        }

        if ($subMchid === '') {
            throw new PaymentException('Refund query requires sub_mchid for partner refunds.');
        }

        $endpoint = str_replace(
            '{out_refund_no}',
            rawurlencode($outRefundNo),
            PaymentEndpoints::V3_TRANSACTION_REFUND_QUERY,
        );

        if ($subMchid !== null) {
            $endpoint .= '?sub_mchid=' . rawurlencode($subMchid);
        }

        return $this->getJson(
            $endpoint,
        );
    }

    /** 按商户退款单号查询电商收付通退款。 */
    public function queryEcommerce(string $outRefundNo, string $subMchid): array
    {
        $this->assertEcommerceConfig();
        $this->requireQueryValue($outRefundNo, 'out_refund_no');
        $this->requireQueryValue($subMchid, 'sub_mchid');

        $endpoint = str_replace(
            '{out_refund_no}',
            rawurlencode($outRefundNo),
            PaymentEndpoints::V3_TRANSACTION_REFUND_QUERY_OUT_REFUND_NO,
        );

        return $this->getJson(
            $endpoint . '?sub_mchid=' . rawurlencode($subMchid),
        );
    }

    /** 按微信退款单号查询电商收付通退款。 */
    public function queryEcommerceById(string $refundId, string $subMchid): array
    {
        $this->assertEcommerceConfig();
        $this->requireQueryValue($refundId, 'refund_id');
        $this->requireQueryValue($subMchid, 'sub_mchid');

        $endpoint = str_replace(
            '{refund_id}',
            rawurlencode($refundId),
            PaymentEndpoints::V3_TRANSACTION_REFUND_QUERY_ID,
        );

        return $this->getJson(
            $endpoint . '?sub_mchid=' . rawurlencode($subMchid),
        );
    }

    /** 发送 V3 JSON POST 请求并解析统一响应。 */
    private function postJson(
        string $endpoint,
        array $payload,
        array $headers = [],
    ): array
    {
        $options = ['json' => $payload];
        if ($headers !== []) {
            $options['headers'] = $headers;
        }

        $response = $this->application->getClient()->post(
            PaymentEndpoints::BASE_PAY_URL . $endpoint,
            $options,
        );

        return $this->decode($response);
    }

    /** 发送 V3 GET 请求并解析统一响应。 */
    private function getJson(string $endpoint): array
    {
        $response = $this->application->getClient()->get(
            PaymentEndpoints::BASE_PAY_URL . $endpoint,
        );

        return $this->decode($response);
    }

    /** 仅在异常退款包含敏感字段时加密并发送公钥序列号。 */
    private function postAbnormalJson(string $endpoint, array $payload): array
    {
        foreach (['bank_account', 'real_name'] as $field) {
            if (($payload[$field] ?? '') === '') {
                continue;
            }

            $serial = $this->encryptionSerial();

            return $this->postJson(
                $endpoint,
                $this->encryptAbnormalPayload($payload, $serial),
                ['Wechatpay-Serial' => $serial],
            );
        }

        return $this->postJson($endpoint, $payload);
    }

    /** 创建 EasyWeChat 加密工具，用于异常退款敏感字段加密。 */
    private function utils(): Utils
    {
        return new Utils($this->application->getMerchant());
    }

    /** 获取异常退款加密使用的平台公钥序列号。 */
    private function encryptionSerial(): string
    {
        if ($this->config !== null && $this->config->public_key_id !== '') {
            return $this->config->public_key_id;
        }

        $serial = array_key_first($this->config?->platform_certs ?? []);
        if ((! is_string($serial) && ! is_int($serial)) || (string) $serial === '') {
            throw new PaymentException(
                'Wechat abnormal refund requires a public key ID or platform certificate for sensitive field encryption.'
            );
        }

        return (string) $serial;
    }

    /** 使用微信支付公钥加密银行卡号和收款人姓名。 */
    private function encryptAbnormalPayload(array $payload, string $serial): array
    {
        foreach (['bank_account', 'real_name'] as $field) {
            if (($payload[$field] ?? '') === '') {
                continue;
            }

            $payload[$field] = $this->utils()->encryptWithRsaPublicKey(
                (string) $payload[$field],
                $serial,
            );
        }

        return $payload;
    }

    /** 校验收付通查询路径参数不能为空。 */
    private function requireQueryValue(string $value, string $field): void
    {
        if ($value === '') {
            throw new PaymentException(
                "E-commerce refund query requires {$field}."
            );
        }
    }

    /** 确认当前退款客户端绑定的是服务商配置。 */
    private function assertEcommerceConfig(): void
    {
        if (! $this->config instanceof PartnerConfig) {
            throw new PaymentException(
                'E-commerce refunds require PartnerConfig.',
            );
        }
    }

    /** 解析 V3 退款响应，并将 HTTP 错误转换为扩展包异常。 */
    private function decode(object $response): array
    {
        $statusCode = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($statusCode >= 400) {
            $code = is_string($data['code'] ?? null)
                ? $data['code']
                : 'UNKNOWN_ERROR';
            $message = is_string($data['message'] ?? null)
                ? $data['message']
                : 'Wechat Pay returned an unsuccessful V3 refund response.';

            throw new PaymentException(
                "Wechat Pay API V3 refund request failed ({$statusCode}) [{$code}]: {$message}"
            );
        }

        return $data;
    }
}
