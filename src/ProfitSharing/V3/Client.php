<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\ProfitSharing\V3;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use EasyWeChat\Pay\Utils;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Endpoints\ProfitSharingEndpoints;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\ProfitSharing\ClientInterface;
use Qinii\WechatPayment\ProfitSharing\ProfitSharingDto;
use Qinii\WechatPayment\ProfitSharing\ReceiverDto;
use Qinii\WechatPayment\ProfitSharing\ReturnDto;

/** 普通商户和服务商微信支付 API V3 分账客户端。 */
final class Client implements ClientInterface
{
    /** 创建 V3 分账客户端。 */
    public function __construct(
        private AbstractWechatPayConfig $config,
        private ApplicationContract $application,
        private Builder $builder,
    ) {
    }

    /** 请求分账。 */
    public function create(ProfitSharingDto $dto): array
    {
        return $this->postJson(
            ProfitSharingEndpoints::V3_CREATE,
            $this->builder->buildCreate($dto),
        );
    }

    /** 查询分账结果。 */
    public function query(ProfitSharingDto $dto): array
    {
        return $this->getJson(
            $this->uri(
                ProfitSharingEndpoints::V3_QUERY,
                ['out_order_no' => $dto->out_order_no],
            ),
            $this->builder->buildQuery($dto),
        );
    }

    /** 解冻剩余资金并完结分账。 */
    public function finish(ProfitSharingDto $dto): array
    {
        return $this->postJson(
            ProfitSharingEndpoints::V3_FINISH,
            $this->builder->buildFinish($dto),
        );
    }

    /** 查询订单剩余待分金额。 */
    public function queryAmounts(ProfitSharingDto $dto): array
    {
        if ($dto->transaction_id === '') {
            throw new PaymentException(
                'ProfitSharingDto [transaction_id] is required.'
            );
        }

        return $this->getJson($this->uri(
            ProfitSharingEndpoints::V3_AMOUNTS,
            ['transaction_id' => $dto->transaction_id],
        ));
    }

    /** 请求分账回退。 */
    public function createReturn(ReturnDto $dto): array
    {
        return $this->postJson(
            ProfitSharingEndpoints::V3_RETURN,
            $this->builder->buildReturn($dto),
        );
    }

    /** 查询分账回退结果。 */
    public function queryReturn(ReturnDto $dto): array
    {
        return $this->getJson(
            $this->uri(
                ProfitSharingEndpoints::V3_RETURN_QUERY,
                ['out_return_no' => $dto->out_return_no],
            ),
            $this->builder->buildReturnQuery($dto),
        );
    }

    /** 添加分账接收方。 */
    public function addReceiver(
        ReceiverDto $dto,
        string $subMchid = '',
        string $subAppid = '',
        string $brandMchId = '',
    ): array {
        return $this->postJson(
            ProfitSharingEndpoints::V3_RECEIVER_ADD,
            $this->builder->buildReceiverAdd($dto, $subMchid, $subAppid),
        );
    }

    /** 删除分账接收方。 */
    public function deleteReceiver(
        ReceiverDto $dto,
        string $subMchid = '',
        string $subAppid = '',
        string $brandMchId = '',
    ): array {
        return $this->postJson(
            ProfitSharingEndpoints::V3_RECEIVER_DELETE,
            $this->builder->buildReceiverDelete($dto, $subMchid, $subAppid),
        );
    }

    /** 查询服务商子商户最大分账比例。 */
    public function queryMaxRatio(
        string $subMchid,
        string $brandMchId = '',
    ): array {
        return $this->getJson($this->uri(
            ProfitSharingEndpoints::V3_MAX_RATIO,
            ['sub_mchid' => $this->builder->buildMaxRatio($subMchid)],
        ));
    }

    /** 申请 V3 分账账单。 */
    public function requestBill(
        string $billDate,
        string $tarType = 'GZIP',
        string $subMchid = '',
    ): array {
        return $this->getJson(
            ProfitSharingEndpoints::V3_BILL,
            $this->builder->buildBill($billDate, $tarType, $subMchid),
        );
    }

    /** 发送 V3 JSON POST 请求，并在需要时加密接收方名称。 */
    private function postJson(string $endpoint, array $payload): array
    {
        $options = $this->requestOptions($payload);
        $response = $this->application->getClient()->post(
            ProfitSharingEndpoints::BASE_PAY_URL . $endpoint,
            $options,
        );

        return $this->decode($response);
    }

    /** 发送 V3 GET 请求。 */
    private function getJson(string $endpoint, array $query = []): array
    {
        $url = ProfitSharingEndpoints::BASE_PAY_URL . $endpoint;
        if ($query !== []) {
            $url .= '?' . http_build_query(
                $query,
                '',
                '&',
                PHP_QUERY_RFC3986,
            );
        }

        return $this->decode(
            $this->application->getClient()->get($url),
        );
    }

    /** 加密请求中的接收方名称并附带加密证书序列号。 */
    private function requestOptions(array $payload): array
    {
        $hasSensitiveName = is_string($payload['name'] ?? null)
            && $payload['name'] !== '';

        foreach ($payload['receivers'] ?? [] as $receiver) {
            if (is_string($receiver['name'] ?? null) && $receiver['name'] !== '') {
                $hasSensitiveName = true;
                break;
            }
        }

        if (! $hasSensitiveName) {
            return ['json' => $payload];
        }

        $serial = $this->encryptionSerial();
        $utils = new Utils($this->application->getMerchant());

        if (is_string($payload['name'] ?? null) && $payload['name'] !== '') {
            $payload['name'] = $utils->encryptWithRsaPublicKey(
                $payload['name'],
                $serial,
            );
        }

        foreach ($payload['receivers'] ?? [] as $index => $receiver) {
            if (! is_string($receiver['name'] ?? null) || $receiver['name'] === '') {
                continue;
            }

            $payload['receivers'][$index]['name'] = $utils->encryptWithRsaPublicKey(
                $receiver['name'],
                $serial,
            );
        }

        return [
            'json' => $payload,
            'headers' => ['Wechatpay-Serial' => $serial],
        ];
    }

    /** 获取敏感字段加密使用的微信支付公钥 ID 或平台证书序列号。 */
    private function encryptionSerial(): string
    {
        if ($this->config->public_key_id !== '') {
            return $this->config->public_key_id;
        }

        $serial = array_key_first($this->config->platform_certs);
        if ((! is_string($serial) && ! is_int($serial)) || (string) $serial === '') {
            throw new PaymentException(
                'Wechat profit sharing requires a public key ID or platform certificate for receiver name encryption.'
            );
        }

        return (string) $serial;
    }

    /** 替换接口路径参数并执行 URL 编码。 */
    private function uri(string $template, array $params): string
    {
        foreach ($params as $key => $value) {
            if ((string) $value === '') {
                throw new PaymentException(
                    "Wechat profit sharing path parameter [{$key}] is required."
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

    /** 解析 V3 分账响应，并将 HTTP 错误转换为扩展包异常。 */
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
                : 'Wechat Pay returned an unsuccessful response.';

            throw new PaymentException(
                "Wechat Pay API V3 profit-sharing request failed ({$statusCode}) [{$code}]: {$message}"
            );
        }

        return $data;
    }
}
