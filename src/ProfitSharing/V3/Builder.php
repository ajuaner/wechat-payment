<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\ProfitSharing\V3;

use DateTimeImmutable;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\ProfitSharing\ProfitSharingDto;
use Qinii\WechatPayment\ProfitSharing\ReceiverDto;
use Qinii\WechatPayment\ProfitSharing\ReturnDto;

/** 组装普通商户和服务商微信支付 API V3 分账参数。 */
final class Builder
{
    /** 使用普通商户或服务商配置初始化 V3 分账 Builder。 */
    public function __construct(private AbstractWechatPayConfig $config)
    {
    }

    /** 构造请求分账参数。 */
    public function buildCreate(ProfitSharingDto $dto): array
    {
        $this->requireOrderReference($dto);
        $this->requirePartnerSubMchid($dto->sub_mchid);

        $count = count($dto->receivers);
        if ($count < 1 || $count > 50) {
            throw new PaymentException(
                'ProfitSharingDto [receivers] must contain 1-50 receivers.'
            );
        }

        return $this->filter(array_replace($dto->extra, [
            'appid' => $this->config->app_id,
            'sub_mchid' => $this->partnerValue($dto->sub_mchid),
            'sub_appid' => $this->partnerValue($dto->sub_appid),
            'transaction_id' => $dto->transaction_id,
            'out_order_no' => $dto->out_order_no,
            'receivers' => array_map(
                fn (ReceiverDto $receiver): array => $this->orderReceiver($receiver),
                $dto->receivers,
            ),
            'unfreeze_unsplit' => $dto->finish,
        ]));
    }

    /** 构造查询分账结果参数。 */
    public function buildQuery(ProfitSharingDto $dto): array
    {
        $this->requireOrderReference($dto);
        $this->requirePartnerSubMchid($dto->sub_mchid);

        return $this->filter(array_replace($dto->extra, [
            'sub_mchid' => $this->partnerValue($dto->sub_mchid),
            'transaction_id' => $dto->transaction_id,
        ]));
    }

    /** 构造解冻剩余资金参数。 */
    public function buildFinish(ProfitSharingDto $dto): array
    {
        $this->requireOrderReference($dto);
        $this->requireValue($dto->description, 'description');
        $this->requirePartnerSubMchid($dto->sub_mchid);

        return $this->filter(array_replace($dto->extra, [
            'sub_mchid' => $this->partnerValue($dto->sub_mchid),
            'transaction_id' => $dto->transaction_id,
            'out_order_no' => $dto->out_order_no,
            'description' => $dto->description,
        ]));
    }

    /** 构造请求分账回退参数。 */
    public function buildReturn(ReturnDto $dto): array
    {
        $this->requirePartnerSubMchid($dto->sub_mchid);
        $this->requireOneOrderNumber($dto);

        foreach ([
            'out_return_no' => $dto->out_return_no,
            'return_mchid' => $dto->return_mchid,
            'description' => $dto->description,
        ] as $field => $value) {
            $this->requireValue($value, $field);
        }

        if ($dto->amount <= 0) {
            throw new PaymentException(
                'ReturnDto [amount] must be greater than 0.'
            );
        }

        return $this->filter(array_replace($dto->extra, [
            'sub_mchid' => $this->partnerValue($dto->sub_mchid),
            'order_id' => $dto->order_id,
            'out_order_no' => $dto->out_order_no,
            'out_return_no' => $dto->out_return_no,
            'return_mchid' => $dto->return_mchid,
            'amount' => $dto->amount,
            'description' => $dto->description,
        ]));
    }

    /** 构造查询分账回退结果参数。 */
    public function buildReturnQuery(ReturnDto $dto): array
    {
        $this->requirePartnerSubMchid($dto->sub_mchid);
        $this->requireValue($dto->out_return_no, 'out_return_no');
        $this->requireValue($dto->out_order_no, 'out_order_no');

        return $this->filter(array_replace($dto->extra, [
            'sub_mchid' => $this->partnerValue($dto->sub_mchid),
            'out_order_no' => $dto->out_order_no,
        ]));
    }

    /** 构造添加分账接收方参数。 */
    public function buildReceiverAdd(
        ReceiverDto $dto,
        string $subMchid,
        string $subAppid,
    ): array {
        $receiver = $this->receiverRelation($dto, true);
        $this->requirePartnerSubMchid($subMchid);

        return $this->filter(array_replace($dto->extra, [
            'sub_mchid' => $this->partnerValue($subMchid),
            'appid' => $this->config->app_id,
            'sub_appid' => $this->partnerValue($subAppid),
        ], $receiver));
    }

    /** 构造删除分账接收方参数。 */
    public function buildReceiverDelete(
        ReceiverDto $dto,
        string $subMchid,
        string $subAppid,
    ): array {
        $receiver = $this->receiverRelation($dto, false);
        $this->requirePartnerSubMchid($subMchid);

        return $this->filter(array_replace($dto->extra, [
            'sub_mchid' => $this->partnerValue($subMchid),
            'appid' => $this->config->app_id,
            'sub_appid' => $this->partnerValue($subAppid),
        ], $receiver));
    }

    /** 构造服务商查询最大分账比例所需的子商户号。 */
    public function buildMaxRatio(string $subMchid): string
    {
        if (! $this->config instanceof PartnerConfig) {
            throw new UnsupportedModeException(
                'Maximum profit-sharing ratio queries require PartnerConfig.'
            );
        }

        $this->requireValue($subMchid, 'sub_mchid');

        return $subMchid;
    }

    /** 构造申请分账账单查询参数。 */
    public function buildBill(
        string $billDate,
        string $tarType,
        string $subMchid,
    ): array {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $billDate);
        if ($date === false || $date->format('Y-m-d') !== $billDate) {
            throw new PaymentException(
                'Profit-sharing bill [bill_date] must use YYYY-MM-DD format.'
            );
        }

        $this->requirePartnerSubMchid($subMchid, false);

        return $this->filter([
            'sub_mchid' => $this->partnerValue($subMchid),
            'bill_date' => $billDate,
            'tar_type' => $tarType,
        ]);
    }

    /** 校验并组装创建分账使用的接收方。 */
    private function orderReceiver(ReceiverDto $dto): array
    {
        foreach ([
            'type' => $dto->type,
            'account' => $dto->account,
            'description' => $dto->description,
        ] as $field => $value) {
            $this->requireValue($value, "receivers.{$field}");
        }

        if ($dto->amount <= 0) {
            throw new PaymentException(
                'ReceiverDto [amount] must be greater than 0.'
            );
        }

        return $this->filter(array_replace($dto->extra, [
            'type' => $dto->type,
            'account' => $dto->account,
            'amount' => $dto->amount,
            'description' => $dto->description,
            'name' => $dto->name,
        ]));
    }

    /** 校验并组装接收方关系。 */
    private function receiverRelation(ReceiverDto $dto, bool $withRelation): array
    {
        $this->requireValue($dto->type, 'receiver.type');
        $this->requireValue($dto->account, 'receiver.account');

        if ($withRelation) {
            $this->requireValue($dto->relation_type, 'receiver.relation_type');

            if ($dto->relation_type === 'CUSTOM') {
                $this->requireValue(
                    $dto->custom_relation,
                    'receiver.custom_relation',
                );
            }
        }

        return $this->filter([
            'type' => $dto->type,
            'account' => $dto->account,
            'name' => $dto->name,
            'relation_type' => $withRelation ? $dto->relation_type : '',
            'custom_relation' => $withRelation ? $dto->custom_relation : '',
        ]);
    }

    /** 校验微信订单号和商户分账单号。 */
    private function requireOrderReference(ProfitSharingDto $dto): void
    {
        $this->requireValue($dto->transaction_id, 'transaction_id');
        $this->requireValue($dto->out_order_no, 'out_order_no');
    }

    /** 校验回退请求至少提供一种分账单号。 */
    private function requireOneOrderNumber(ReturnDto $dto): void
    {
        if ($dto->order_id === '' && $dto->out_order_no === '') {
            throw new PaymentException(
                'ReturnDto requires order_id or out_order_no.'
            );
        }
    }

    /** 在服务商模式下校验子商户号。 */
    private function requirePartnerSubMchid(
        string $subMchid,
        bool $required = true,
    ): void {
        if (
            $required
            && $this->config instanceof PartnerConfig
            && $subMchid === ''
        ) {
            throw new PaymentException(
                'Wechat partner profit sharing [sub_mchid] is required.'
            );
        }
    }

    /** 仅在服务商模式返回请求字段值。 */
    private function partnerValue(string $value): string
    {
        return $this->config instanceof PartnerConfig ? $value : '';
    }

    /** 校验接口字段不能为空。 */
    private function requireValue(mixed $value, string $field): void
    {
        if ($value === '' || $value === null || $value === []) {
            throw new PaymentException(
                "Wechat profit sharing [{$field}] is required."
            );
        }
    }

    /** 移除空的可选参数。 */
    private function filter(array $payload): array
    {
        return array_filter(
            $payload,
            static fn (mixed $value): bool => $value !== ''
                && $value !== null
                && $value !== [],
        );
    }
}
