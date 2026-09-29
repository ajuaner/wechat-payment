<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\ProfitSharing\V2;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Endpoints\ProfitSharingEndpoints;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\ProfitSharing\ClientInterface;
use Qinii\WechatPayment\ProfitSharing\ProfitSharingDto;
use Qinii\WechatPayment\ProfitSharing\ReceiverDto;
use Qinii\WechatPayment\ProfitSharing\ReturnDto;

/** 微信支付 API V2 分账客户端。 */
final class Client implements ClientInterface
{
    /** 创建 V2 分账客户端。 */
    public function __construct(
        private AbstractWechatPayConfig $config,
        private ApplicationContract $application,
        private Builder $builder,
    ) {
    }

    /** 请求分账，finish=true 使用单次分账，否则使用多次分账。 */
    public function create(ProfitSharingDto $dto): array
    {
        $this->assertMerchantCertificate();

        return $this->postXml(
            $dto->finish
                ? ProfitSharingEndpoints::V2_SINGLE
                : ProfitSharingEndpoints::V2_MULTIPLE,
            $this->builder->buildCreate($dto),
            true,
        );
    }

    /** 查询分账结果。 */
    public function query(ProfitSharingDto $dto): array
    {
        return $this->postXml(
            ProfitSharingEndpoints::V2_QUERY,
            $this->builder->buildQuery($dto),
        );
    }

    /** 完结分账并解冻剩余资金。 */
    public function finish(ProfitSharingDto $dto): array
    {
        $this->assertMerchantCertificate();

        return $this->postXml(
            ProfitSharingEndpoints::V2_FINISH,
            $this->builder->buildFinish($dto),
            true,
        );
    }

    /** 查询订单剩余待分金额。 */
    public function queryAmounts(ProfitSharingDto $dto): array
    {
        return $this->postXml(
            ProfitSharingEndpoints::V2_AMOUNTS,
            $this->builder->buildAmounts($dto),
        );
    }

    /** 请求分账回退。 */
    public function createReturn(ReturnDto $dto): array
    {
        $this->assertMerchantCertificate();

        return $this->postXml(
            ProfitSharingEndpoints::V2_RETURN,
            $this->builder->buildReturn($dto),
            true,
        );
    }

    /** 查询分账回退结果。 */
    public function queryReturn(ReturnDto $dto): array
    {
        return $this->postXml(
            ProfitSharingEndpoints::V2_RETURN_QUERY,
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
        return $this->postXml(
            ProfitSharingEndpoints::V2_RECEIVER_ADD,
            $this->builder->buildReceiverAdd(
                $dto,
                $subMchid,
                $subAppid,
                $brandMchId,
            ),
        );
    }

    /** 删除分账接收方。 */
    public function deleteReceiver(
        ReceiverDto $dto,
        string $subMchid = '',
        string $subAppid = '',
        string $brandMchId = '',
    ): array {
        return $this->postXml(
            ProfitSharingEndpoints::V2_RECEIVER_DELETE,
            $this->builder->buildReceiverDelete(
                $dto,
                $subMchid,
                $subAppid,
                $brandMchId,
            ),
        );
    }

    /** 查询服务商子商户最大分账比例。 */
    public function queryMaxRatio(
        string $subMchid,
        string $brandMchId = '',
    ): array {
        return $this->postXml(
            ProfitSharingEndpoints::V2_MAX_RATIO,
            $this->builder->buildMaxRatio($subMchid, $brandMchId),
        );
    }

    /** V2 不支持申请分账账单。 */
    public function requestBill(
        string $billDate,
        string $tarType = 'GZIP',
        string $subMchid = '',
    ): array {
        throw new UnsupportedModeException(
            'Profit-sharing bills require Wechat Pay API v3.'
        );
    }

    /** 发送 V2 XML 请求并校验通信及业务状态。 */
    private function postXml(
        string $endpoint,
        array $payload,
        bool $withCertificate = false,
    ): array
    {
        $options = ['xml' => $payload];
        if ($withCertificate) {
            $options['local_cert'] = $this->config->certificate;
            $options['local_pk'] = $this->config->private_key;
        }

        $response = $this->application->getClient()->post(
            ProfitSharingEndpoints::BASE_PAY_URL . $endpoint,
            $options,
        );
        $statusCode = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($statusCode >= 400) {
            throw new PaymentException(
                'Wechat Pay API V2 profit-sharing request failed '
                . "({$statusCode}): "
                . (string) ($data['return_msg'] ?? 'unknown error')
            );
        }

        if (($data['return_code'] ?? 'SUCCESS') !== 'SUCCESS') {
            throw new PaymentException(
                'Wechat Pay API V2 profit-sharing request failed: '
                . (string) ($data['return_msg'] ?? 'unknown error')
            );
        }

        if (array_key_exists('result_code', $data) && $data['result_code'] !== 'SUCCESS') {
            throw new PaymentException(
                'Wechat Pay API V2 profit-sharing business failed: '
                . (string) ($data['err_code_des'] ?? 'unknown error')
            );
        }

        return $data;
    }

    /** 校验 V2 安全接口所需的商户私钥和证书。 */
    private function assertMerchantCertificate(): void
    {
        if (
            $this->config->private_key === null
            || $this->config->private_key === ''
            || $this->config->certificate === null
            || $this->config->certificate === ''
        ) {
            throw new PaymentException(
                'Wechat Pay API V2 profit-sharing secure requests require private_key and certificate.'
            );
        }
    }
}
