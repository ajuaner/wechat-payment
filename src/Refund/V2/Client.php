<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Refund\V2;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Endpoints\PaymentEndpoints;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Payment\PaymentRefundDto;
use Qinii\WechatPayment\Partner\PartnerRefundDto;
use Qinii\WechatPayment\Refund\AbnormalRefundDto;

final class Client
{
    /** 创建 V2 退款客户端。 */
    public function __construct(
        private ApplicationContract $application,
        private Builder $builder,
        private AbstractWechatPayConfig $config,
    ) {
    }

    /** 发起普通商户或服务商 V2 退款。 */
    public function refund(PaymentRefundDto|PartnerRefundDto $dto): array
    {
        return $this->postXml(
            PaymentEndpoints::V2_TRANSACTION_REFUND,
            $this->builder->build($dto),
            true,
        );
    }

    /** V2 不支持电商收付通退款。 */
    public function ecommerceRefund(PartnerRefundDto $dto): array
    {
        throw new UnsupportedModeException(
            'E-commerce refunds require Wechat Pay API v3.'
        );
    }

    /** 查询普通商户或服务商 V2 退款。 */
    public function query(string $outRefundNo, ?string $subMchid = null): array
    {
        return $this->postXml(
            PaymentEndpoints::V2_TRANSACTION_REFUND_QUERY,
            $this->builder->buildQuery($outRefundNo, $subMchid),
        );
    }

    /** V2 不支持电商收付通退款查询。 */
    public function queryEcommerce(string $outRefundNo, string $subMchid): array
    {
        throw new UnsupportedModeException(
            'E-commerce refund queries require Wechat Pay API v3.'
        );
    }

    /** V2 不支持按微信退款单号查询电商退款。 */
    public function queryEcommerceById(string $refundId, string $subMchid): array
    {
        throw new UnsupportedModeException(
            'E-commerce refund queries require Wechat Pay API v3.'
        );
    }

    /** V2 不支持银行卡异常退款。 */
    public function abnormalRefund(AbnormalRefundDto $dto): array
    {
        throw new UnsupportedModeException(
            'Abnormal refunds require Wechat Pay API v3.'
        );
    }

    /** V2 不支持电商收付通异常退款。 */
    public function abnormalEcommerceRefund(AbnormalRefundDto $dto): array
    {
        throw new UnsupportedModeException(
            'E-commerce abnormal refunds require Wechat Pay API v3.'
        );
    }

    /** 发送 V2 XML 退款请求并校验通信及业务状态。 */
    private function postXml(
        string $endpoint,
        array $payload,
        bool $requiresClientCertificate = false,
    ): array
    {
        $options = ['xml' => $payload];

        if ($requiresClientCertificate) {
            if ($this->config->certificate === '' || $this->config->private_key === '') {
                throw new PaymentException(
                    'Wechat Pay API V2 refund requires certificate and private_key.'
                );
            }

            $options['local_cert'] = $this->config->certificate;
            $options['local_pk'] = $this->config->private_key;
        }

        $response = $this->application->getClient()->post(
            PaymentEndpoints::BASE_PAY_URL . $endpoint,
            $options,
        );
        $statusCode = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($statusCode >= 400) {
            $message = is_string($data['return_msg'] ?? null)
                ? $data['return_msg']
                : 'Wechat Pay returned an unsuccessful V2 refund response.';

            throw new PaymentException(
                "Wechat Pay API V2 refund request failed ({$statusCode}): {$message}"
            );
        }

        if (($data['return_code'] ?? 'SUCCESS') !== 'SUCCESS') {
            throw new PaymentException(
                'Wechat Pay API V2 refund request failed: '
                . (string) ($data['return_msg'] ?? 'unknown error')
            );
        }

        if (array_key_exists('result_code', $data) && $data['result_code'] !== 'SUCCESS') {
            throw new PaymentException(
                'Wechat Pay API V2 refund business failed: '
                . (string) ($data['err_code_des'] ?? 'unknown error')
            );
        }

        return $data;
    }
}
