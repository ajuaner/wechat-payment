<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Transfer\V3;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use EasyWeChat\Pay\Utils;
use Qinii\WechatPayment\Config\TransferConfig;
use Qinii\WechatPayment\Endpoints\TransferEndpoints;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Transfer\AuthorizationDto;
use Qinii\WechatPayment\Transfer\TransferDto;

final class Client
{
    /** 创建 API v3 商户付款到零钱客户端。 */
    public function __construct(
        private TransferConfig $config,
        private ApplicationContract $application,
        private Builder $builder,
    ) {
    }

    /** 发起商户付款到零钱请求。 */
    public function create(TransferDto $dto): array
    {
        return $this->createTransfer($dto);
    }

    /** 发起商户付款到零钱的兼容别名。 */
    public function transfer(TransferDto $dto): array
    {
        return $this->createTransfer($dto);
    }

    /** 创建商户转账单。 */
    public function createTransfer(TransferDto $dto): array
    {
        return $this->postJson(
            TransferEndpoints::V3_TRANSFER_BILL,
            $this->builder->buildTransfer($dto),
        );
    }

    /** 取消指定商户转账单。 */
    public function cancelTransfer(string $outBillNo): array
    {
        return $this->postJson(
            TransferEndpoints::V3_TRANSFER_BILL_CANCEL,
            [],
            ['out_bill_no' => $outBillNo],
        );
    }

    /** 按商户单号查询转账结果。 */
    public function queryTransferByOutBillNo(string $outBillNo): array
    {
        return $this->getJson(
            TransferEndpoints::V3_TRANSFER_BILL_QUERY,
            ['out_bill_no' => $outBillNo],
        );
    }

    /** 按微信转账单号查询转账结果。 */
    public function queryTransferByTransferBillNo(string $transferBillNo): array
    {
        return $this->getJson(
            TransferEndpoints::V3_TRANSFER_BILL_QUERY_BY_TRANSFER_BILL_NO,
            ['transfer_bill_no' => $transferBillNo],
        );
    }

    /** 按商户单号申请电子回单。 */
    public function applyReceiptByOutBillNo(string $outBillNo): array
    {
        return $this->postJson(
            TransferEndpoints::V3_TRANSFER_ELECSIGN,
            ['out_bill_no' => $outBillNo],
        );
    }

    /** 按商户单号查询电子回单申请结果。 */
    public function queryReceiptByOutBillNo(string $outBillNo): array
    {
        return $this->getJson(
            TransferEndpoints::V3_TRANSFER_ELECSIGN_QUERY,
            ['out_bill_no' => $outBillNo],
        );
    }

    /** 按微信转账单号申请电子回单。 */
    public function applyReceiptByTransferBillNo(string $transferBillNo): array
    {
        return $this->postJson(
            TransferEndpoints::V3_TRANSFER_ELECSIGN_BY_TRANSFER_BILL_NO,
            ['transfer_bill_no' => $transferBillNo],
        );
    }

    /** 按微信转账单号查询电子回单申请结果。 */
    public function queryReceiptByTransferBillNo(string $transferBillNo): array
    {
        return $this->getJson(
            TransferEndpoints::V3_TRANSFER_ELECSIGN_QUERY_BY_TRANSFER_BILL_NO,
            ['transfer_bill_no' => $transferBillNo],
        );
    }

    /** 发起转账并完成免确认收款授权。 */
    public function preTransferWithAuthorization(AuthorizationDto $dto): array
    {
        return $this->postJson(
            TransferEndpoints::V3_TRANSFER_PRE_TRANSFER_WITH_AUTHORIZATION,
            $this->builder->buildPreTransferWithAuthorization($dto),
        );
    }

    /** 发起免确认收款授权申请。 */
    public function applyAuthorization(AuthorizationDto $dto): array
    {
        return $this->postJson(
            TransferEndpoints::V3_TRANSFER_USER_CONFIRM_AUTHORIZATION,
            $this->builder->buildAuthorization($dto),
        );
    }

    /** 按商户授权单号查询免确认收款授权结果。 */
    public function queryAuthorization(string $outAuthorizationNo): array
    {
        return $this->getJson(
            TransferEndpoints::V3_TRANSFER_USER_CONFIRM_AUTHORIZATION_QUERY_BY_TRANSFER_BILL_NO,
            ['out_authorization_no' => $outAuthorizationNo],
        );
    }

    /** 使用免确认收款授权完成转账。 */
    public function transferAfterAuthorization(AuthorizationDto $dto): array
    {
        return $this->postJson(
            TransferEndpoints::V3_TRANSFER_USER_CONFIRM_TRANSFER_BILL,
            $this->builder->buildTransferAfterAuthorization($dto),
        );
    }

    /** 关闭免确认收款授权。 */
    public function closeAuthorization(string $outAuthorizationNo): array
    {
        return $this->postJson(
            TransferEndpoints::V3_TRANSFER_USER_CONFIRM_AUTHORIZATION_CLOSE,
            [],
            ['out_authorization_no' => $outAuthorizationNo],
        );
    }

    /** 发送转账相关 JSON POST 请求。 */
    private function postJson(string $endpoint, array $payload = [], array $path = []): array
    {
        $uri = $this->uri($endpoint, $path);
        $options = $this->requestOptions($payload);
        $response = $this->application->getClient()->post(
            TransferEndpoints::BASE_PAY_URL . $uri,
            $options,
        );

        return $this->decodeResponse($response);
    }

    /** 加密转账敏感字段，并附带所用微信支付公钥或平台证书序列号。 */
    private function requestOptions(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        $options = ['json' => $payload];
        if (! is_string($payload['user_name'] ?? null) || $payload['user_name'] === '') {
            return $options;
        }

        $serial = $this->encryptionSerial();
        $options['json']['user_name'] = (new Utils(
            $this->application->getMerchant(),
        ))->encryptWithRsaPublicKey($payload['user_name'], $serial);
        $options['headers'] = ['Wechatpay-Serial' => $serial];

        return $options;
    }

    /** 获取转账敏感字段加密使用的微信支付公钥 ID 或平台证书序列号。 */
    private function encryptionSerial(): string
    {
        if ($this->config->public_key_id !== '') {
            return $this->config->public_key_id;
        }

        $serial = array_key_first($this->config->platform_certs);
        if ((! is_string($serial) && ! is_int($serial)) || (string) $serial === '') {
            throw new PaymentException(
                'Wechat transfer requires a public key ID or platform certificate for user_name encryption.'
            );
        }

        return (string) $serial;
    }

    /** 发送转账相关 GET 请求。 */
    private function getJson(string $endpoint, array $path): array
    {
        $response = $this->application->getClient()->get(
            TransferEndpoints::BASE_PAY_URL . $this->uri($endpoint, $path),
        );

        return $this->decodeResponse($response);
    }

    /** 替换转账接口路径模板中的占位符。 */
    private function uri(string $template, array $params = []): string
    {
        foreach ($params as $key => $value) {
            if ((string) $value === '') {
                throw new PaymentException(
                    "Wechat transfer path parameter [{$key}] is required."
                );
            }

            $template = str_replace(
                '{' . $key . '}',
                rawurlencode((string) $value),
                $template,
            );
        }

        return $template;
    }

    /** 解析转账响应，并将 HTTP 错误转换为扩展包异常。 */
    private function decodeResponse(object $response): array
    {
        $statusCode = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($statusCode >= 400) {
            $code = is_string($data['code'] ?? null)
                ? $data['code']
                : 'UNKNOWN_ERROR';
            $message = is_string($data['message'] ?? null)
                ? $data['message']
                : 'Wechat Pay returned an unsuccessful response.';

            throw new PaymentException(
                "Wechat Pay API V3 transfer request failed ({$statusCode}) [{$code}]: {$message}"
            );
        }

        return $data;
    }
}
