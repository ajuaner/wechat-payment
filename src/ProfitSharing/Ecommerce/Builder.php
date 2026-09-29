<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\ProfitSharing\Ecommerce;

use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\ProfitSharing\ProfitSharingDto;
use Qinii\WechatPayment\ProfitSharing\ReceiverDto;
use Qinii\WechatPayment\ProfitSharing\ReturnDto;

/** 组装平台收付通分账参数。 */
final class Builder
{
    /** 使用服务商配置初始化收付通分账 Builder。 */
    public function __construct(private PartnerConfig $config)
    {
    }

    /** 构造收付通请求分账参数。 */
    public function buildCreate(ProfitSharingDto $dto): array
    {
        $this->requireOrderReference($dto);
        $this->requireValue($dto->sub_mchid, 'sub_mchid');

        $count = count($dto->receivers);
        if ($count < 1 || $count > 50) {
            throw new PaymentException(
                'ProfitSharingDto [receivers] must contain 1-50 receivers.'
            );
        }

        return $this->filter(array_replace($dto->extra, [
            'appid' => $this->config->app_id,
            'sub_mchid' => $dto->sub_mchid,
            'transaction_id' => $dto->transaction_id,
            'out_order_no' => $dto->out_order_no,
            'receivers' => array_map(
                fn (ReceiverDto $receiver): array => $this->orderReceiver($receiver),
                $dto->receivers,
            ),
            'finish' => $dto->finish,
        ]));
    }

    /** 构造收付通查询分账结果参数。 */
    public function buildQuery(ProfitSharingDto $dto): array
    {
        $this->requireOrderReference($dto);
        $this->requireValue($dto->sub_mchid, 'sub_mchid');

        return $this->filter(array_replace($dto->extra, [
            'sub_mchid' => $dto->sub_mchid,
            'transaction_id' => $dto->transaction_id,
            'out_order_no' => $dto->out_order_no,
        ]));
    }

    /** 构造收付通解冻剩余资金参数。 */
    public function buildFinish(ProfitSharingDto $dto): array
    {
        $this->requireOrderReference($dto);
        $this->requireValue($dto->sub_mchid, 'sub_mchid');
        $this->requireValue($dto->description, 'description');

        return $this->filter(array_replace($dto->extra, [
            'sub_mchid' => $dto->sub_mchid,
            'transaction_id' => $dto->transaction_id,
            'out_order_no' => $dto->out_order_no,
            'description' => $dto->description,
        ]));
    }

    /** 构造收付通请求分账回退参数。 */
    public function buildReturn(ReturnDto $dto): array
    {
        $this->requireValue($dto->sub_mchid, 'sub_mchid');
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
            'sub_mchid' => $dto->sub_mchid,
            'order_id' => $dto->order_id,
            'out_order_no' => $dto->out_order_no,
            'out_return_no' => $dto->out_return_no,
            'return_mchid' => $dto->return_mchid,
            'amount' => $dto->amount,
            'description' => $dto->description,
        ]));
    }

    /** 构造收付通查询分账回退结果参数。 */
    public function buildReturnQuery(ReturnDto $dto): array
    {
        $this->requireValue($dto->sub_mchid, 'sub_mchid');
        $this->requireValue($dto->out_return_no, 'out_return_no');
        $this->requireOneOrderNumber($dto);

        return $this->filter(array_replace($dto->extra, [
            'sub_mchid' => $dto->sub_mchid,
            'out_return_no' => $dto->out_return_no,
            'order_id' => $dto->order_id,
            'out_order_no' => $dto->out_order_no,
        ]));
    }

    /** 构造收付通添加分账接收方参数。 */
    public function buildReceiverAdd(ReceiverDto $dto): array
    {
        return $this->filter(array_replace(
            $dto->extra,
            ['appid' => $this->config->app_id],
            $this->receiverRelation($dto, true),
        ));
    }

    /** 构造收付通删除分账接收方参数。 */
    public function buildReceiverDelete(ReceiverDto $dto): array
    {
        return $this->filter(array_replace(
            $dto->extra,
            ['appid' => $this->config->app_id],
            $this->receiverRelation($dto, false),
        ));
    }

    /** 校验并组装收付通创建分账使用的接收方。 */
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
            'receiver_account' => $dto->account,
            'amount' => $dto->amount,
            'description' => $dto->description,
            'receiver_name' => $dto->name,
        ]));
    }

    /** 校验并组装收付通接收方关系。 */
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

    /** 校验接口字段不能为空。 */
    private function requireValue(mixed $value, string $field): void
    {
        if ($value === '' || $value === null || $value === []) {
            throw new PaymentException(
                "Wechat ecommerce profit sharing [{$field}] is required."
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
