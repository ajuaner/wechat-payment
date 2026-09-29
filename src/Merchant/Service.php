<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Merchant;

use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Config\PaymentConfigResolver;
use Qinii\WechatPayment\Enum\PayMode;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Factory\MerchantFactory;
use Qinii\WechatPayment\Merchant\V3\Client;
use Qinii\WechatPayment\Shared\WechatContext;

/** 子商户入驻与管理服务。 */
final class Service
{
    private ?Client $clientInstance = null;

    /** 创建子商户管理服务。 */
    public function __construct(
        private WechatContext $context,
        private ?PaymentConfigResolver $configResolver = null,
    ) {
    }

    /** 提交普通服务商特约商户入驻申请。 */
    public function applySpecial(SpecialApplymentDto $dto): array
    {
        return $this->client()->applySpecial($dto);
    }

    /** 提交电商收付通二级商户入驻申请。 */
    public function applyEcommerce(EcommerceApplymentDto $dto): array
    {
        return $this->client()->applyEcommerce($dto);
    }

    /** 按申请单号查询特约商户入驻状态。 */
    public function querySpecialByApplymentId(int|string $applymentId): array
    {
        return $this->client()->querySpecialByApplymentId($applymentId);
    }

    /** 按业务申请编号查询特约商户入驻状态。 */
    public function querySpecialByBusinessCode(string $businessCode): array
    {
        return $this->client()->querySpecialByBusinessCode($businessCode);
    }

    /** 按申请单号查询电商收付通二级商户入驻状态。 */
    public function queryEcommerceByApplymentId(int|string $applymentId): array
    {
        return $this->client()->queryEcommerceByApplymentId($applymentId);
    }

    /** 按业务申请编号查询电商收付通二级商户入驻状态。 */
    public function queryEcommerceByOutRequestNo(string $outRequestNo): array
    {
        return $this->client()->queryEcommerceByOutRequestNo($outRequestNo);
    }

    /** 修改特约商户或二级商户结算账户。 */
    public function modifySettlement(SettlementDto $dto): array
    {
        return $this->client()->modifySettlement($dto);
    }

    /** 查询特约商户或二级商户当前结算账户。 */
    public function querySettlement(
        string $subMchid,
        string $accountNumberRule = 'ACCOUNT_NUMBER_RULE_MASK_V1',
    ): array {
        return $this->client()->querySettlement($subMchid, $accountNumberRule);
    }

    /** 查询结算账户修改申请状态。 */
    public function querySettlementApplication(
        string $subMchid,
        string $applicationNo,
        string $accountNumberRule = 'ACCOUNT_NUMBER_RULE_MASK_V1',
    ): array {
        return $this->client()->querySettlementApplication(
            $subMchid,
            $applicationNo,
            $accountNumberRule,
        );
    }

    /** 查询支持个人或企业业务的银行列表。 */
    public function banks(
        int|string $bankType,
        int $offset = 0,
        int $limit = 100,
    ): array {
        return $this->client()->banks($bankType, $offset, $limit);
    }

    /** 查询指定银行和城市下的支行列表。 */
    public function branches(
        string $bankAliasCode,
        string $cityCode,
        int $offset = 0,
        int $limit = 100,
    ): array {
        return $this->client()->branches($bankAliasCode, $cityCode, $offset, $limit);
    }

    /** 查询省份列表，或查询指定省份下的城市列表。 */
    public function areas(?string $provinceCode = null): array
    {
        return $this->client()->areas($provinceCode);
    }

    /** 上传进件图片或 PDF 文件。 */
    public function upload(string $filePath, ?string $filename = null): array
    {
        return $this->client()->upload($filePath, $filename);
    }

    /** 上传普通服务商特约商户进件视频。 */
    public function uploadVideo(string $filePath, ?string $filename = null): array
    {
        return $this->client()->uploadVideo($filePath, $filename);
    }

    /** 延迟创建并缓存当前账号的子商户管理客户端。 */
    private function client(): Client
    {
        if ($this->clientInstance !== null) {
            return $this->clientInstance;
        }

        $config = ($this->configResolver ?? new PaymentConfigResolver())->resolveMode(
            $this->context->accountConfig(),
            PayMode::PARTNER,
        );

        if (! $config instanceof PartnerConfig) {
            throw new UnsupportedModeException(
                'Wechat merchant applyment requires PartnerConfig.'
            );
        }

        return $this->clientInstance = MerchantFactory::merchant(
            $config,
            $this->context->application($config),
        );
    }
}
