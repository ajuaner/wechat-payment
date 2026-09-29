<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Transfer;

use Qinii\WechatPayment\Config\TransferConfigResolver;
use Qinii\WechatPayment\Exception\InvalidConfigException;
use Qinii\WechatPayment\Factory\TransferFactory;
use Qinii\WechatPayment\Shared\WechatContext;
use Qinii\WechatPayment\Transfer\V3\Client;

final class Service
{
    private ?Client $clientInstance = null;
    private ?string $applicationName = null;

    /** 创建商户付款到零钱服务，并绑定当前账号上下文。 */
    public function __construct(
        private WechatContext $context,
        private ?TransferConfigResolver $configResolver = null,
    ) {
    }

    /** 选择收款人 OpenID 所属应用，并返回不影响默认入口的新服务。 */
    public function application(string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidConfigException(
                'Wechat transfer application name is required.'
            );
        }

        $service = clone $this;
        $service->applicationName = $name;
        $service->clientInstance = null;

        return $service;
    }

    /**
     * 发起转账
     * @return array<string, mixed>
     */
    public function create(TransferDto $dto): array
    {
        return $this->client()->createTransfer($dto);
    }

    /**
     * 发起转账
     *
     * @return array<string, mixed>
     */
    public function transfer(TransferDto $dto): array
    {
        return $this->create($dto);
    }

    /**
     * 取消转账
     * @return array<string, mixed>
     */
    public function cancel(string $outBillNo): array
    {
        return $this->client()->cancelTransfer($outBillNo);
    }

    /**
     * 商户单号查询转账结果
     * @return array<string, mixed>
     */
    public function queryByOutBillNo(string $outBillNo): array
    {
        return $this->client()->queryTransferByOutBillNo($outBillNo);
    }

    /**
     * 微信单号查询转账单
     * @return array<string, mixed>
     */
    public function queryByTransferBillNo(string $transferBillNo): array
    {
        return $this->client()->queryTransferByTransferBillNo($transferBillNo);
    }

    /**
     * 商户单号申请电子回单
     * @return array<string, mixed>
     */
    public function applyReceiptByOutBillNo(string $outBillNo): array
    {
        return $this->client()->applyReceiptByOutBillNo($outBillNo);
    }

    /**
     * 商户单号查询电子回单
     * @return array<string, mixed>
     */
    public function queryReceiptByOutBillNo(string $outBillNo): array
    {
        return $this->client()->queryReceiptByOutBillNo($outBillNo);
    }

    /**
     * 微信单号申请电子回单
     * @return array<string, mixed>
     */
    public function applyReceiptByTransferBillNo(string $transferBillNo): array
    {
        return $this->client()->applyReceiptByTransferBillNo($transferBillNo);
    }

    /**
     * 微信单号查询电子回单
     * @return array<string, mixed>
     */
    public function queryReceiptByTransferBillNo(string $transferBillNo): array
    {
        return $this->client()->queryReceiptByTransferBillNo($transferBillNo);
    }

    /**
     * 发起转账并完成免确认收款授权
     * @return array<string, mixed>
     */
    public function preTransferWithAuthorization(AuthorizationDto $dto): array
    {
        return $this->client()->preTransferWithAuthorization($dto);
    }

    /**
     * 发起免确认收款授权
     * @return array<string, mixed>
     */
    public function applyAuthorization(AuthorizationDto $dto): array
    {
        return $this->client()->applyAuthorization($dto);
    }

    /**
     * 商户单号查询授权结果
     * @return array<string, mixed>
     */
    public function queryAuthorization(string $outAuthorizationNo): array
    {
        return $this->client()->queryAuthorization($outAuthorizationNo);
    }

    /**
     * 用免确认收款授权转账
     * @return array<string, mixed>
     */
    public function transferAfterAuthorization(AuthorizationDto $dto): array
    {
        return $this->client()->transferAfterAuthorization($dto);
    }

    /**
     * 解除免确认收款授权
     * @return array<string, mixed>
     */
    public function closeAuthorization(string $outAuthorizationNo): array
    {
        return $this->client()->closeAuthorization($outAuthorizationNo);
    }

    /** 延迟解析配置并缓存当前账号对应的转账客户端。 */
    private function client(): Client
    {
        if ($this->clientInstance !== null) {
            return $this->clientInstance;
        }

        $resolver = $this->configResolver ?? new TransferConfigResolver();
        $config = $resolver->resolve(
            $this->context->accountConfig(),
            $this->applicationName,
        );

        return $this->clientInstance = TransferFactory::transfer(
            $config,
            $this->context->application($config),
        );
    }
}
