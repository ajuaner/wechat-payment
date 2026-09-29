<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\ProfitSharing\V2;

use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\ProfitSharing\ProfitSharingDto;
use Qinii\WechatPayment\ProfitSharing\ReceiverDto;
use Qinii\WechatPayment\ProfitSharing\ReturnDto;

/** 组装微信支付 API V2 分账参数。 */
final class Builder
{
    /** 使用普通商户或服务商配置初始化 V2 分账 Builder。 */
    public function __construct(private AbstractWechatPayConfig $config)
    {
    }

    /** 构造请求分账参数。 */
    public function buildCreate(ProfitSharingDto $dto): array
    {
        $this->requireOrderReference($dto);

        $count = count($dto->receivers);
        if ($count < 1 || $count > 50) {
            throw new PaymentException(
                'ProfitSharingDto [receivers] must contain 1-50 receivers.'
            );
        }

        $receivers = array_map(
            fn (ReceiverDto $receiver): array => $this->orderReceiver($receiver),
            $dto->receivers,
        );

        return $this->filter(array_replace(
            $dto->extra,
            $this->identity(
                $dto->sub_mchid,
                $dto->sub_appid,
                $dto->brand_mch_id,
                true,
            ),
            [
                'transaction_id' => $dto->transaction_id,
                'out_order_no' => $dto->out_order_no,
                'receivers' => $this->encodeJson($receivers),
            ],
        ));
    }

    /** 构造查询分账结果参数。 */
    public function buildQuery(ProfitSharingDto $dto): array
    {
        $this->requireOrderReference($dto);

        return $this->filter(array_replace(
            $dto->extra,
            $this->identity($dto->sub_mchid),
            [
                'transaction_id' => $dto->transaction_id,
                'out_order_no' => $dto->out_order_no,
            ],
        ));
    }

    /** 构造完结分账参数。 */
    public function buildFinish(ProfitSharingDto $dto): array
    {
        $this->requireOrderReference($dto);
        $this->requireValue($dto->description, 'description');

        return $this->filter(array_replace(
            $dto->extra,
            $this->identity(
                $dto->sub_mchid,
                '',
                $dto->brand_mch_id,
                true,
            ),
            [
                'transaction_id' => $dto->transaction_id,
                'out_order_no' => $dto->out_order_no,
                'description' => $dto->description,
            ],
        ));
    }

    /** 构造查询订单待分账金额参数。 */
    public function buildAmounts(ProfitSharingDto $dto): array
    {
        $this->requireValue($dto->transaction_id, 'transaction_id');

        return $this->filter(array_replace(
            $dto->extra,
            $this->identity('', '', '', false, false),
            ['transaction_id' => $dto->transaction_id],
        ));
    }

    /** 构造请求分账回退参数。 */
    public function buildReturn(ReturnDto $dto): array
    {
        foreach ([
            'out_order_no' => $dto->out_order_no,
            'out_return_no' => $dto->out_return_no,
            'return_account_type' => $dto->return_account_type,
            'return_account' => $dto->return_account,
            'description' => $dto->description,
        ] as $field => $value) {
            $this->requireValue($value, $field);
        }

        if ($dto->amount <= 0) {
            throw new PaymentException(
                'ReturnDto [amount] must be greater than 0.'
            );
        }

        return $this->filter(array_replace(
            $dto->extra,
            $this->identity($dto->sub_mchid, $dto->sub_appid, '', true),
            [
                'out_order_no' => $dto->out_order_no,
                'out_return_no' => $dto->out_return_no,
                'return_account_type' => $dto->return_account_type,
                'return_account' => $dto->return_account,
                'return_amount' => $dto->amount,
                'description' => $dto->description,
            ],
        ));
    }

    /** 构造查询分账回退结果参数。 */
    public function buildReturnQuery(ReturnDto $dto): array
    {
        $this->requireValue($dto->out_order_no, 'out_order_no');
        $this->requireValue($dto->out_return_no, 'out_return_no');

        return $this->filter(array_replace(
            $dto->extra,
            $this->identity($dto->sub_mchid, '', '', true),
            [
                'out_order_no' => $dto->out_order_no,
                'out_return_no' => $dto->out_return_no,
            ],
        ));
    }

    /** 构造添加分账接收方参数。 */
    public function buildReceiverAdd(
        ReceiverDto $dto,
        string $subMchid,
        string $subAppid,
        string $brandMchId,
    ): array {
        $receiver = $this->receiverRelation($dto, true);

        return $this->filter(array_replace(
            $dto->extra,
            $this->identity($subMchid, $subAppid, $brandMchId, true),
            ['receiver' => $this->encodeJson($receiver)],
        ));
    }

    /** 构造删除分账接收方参数。 */
    public function buildReceiverDelete(
        ReceiverDto $dto,
        string $subMchid,
        string $subAppid,
        string $brandMchId,
    ): array {
        $receiver = $this->receiverRelation($dto, false);

        return $this->filter(array_replace(
            $dto->extra,
            $this->identity($subMchid, $subAppid, $brandMchId, true),
            ['receiver' => $this->encodeJson($receiver)],
        ));
    }

    /** 构造服务商查询最大分账比例参数。 */
    public function buildMaxRatio(
        string $subMchid,
        string $brandMchId,
    ): array {
        if (! $this->config instanceof PartnerConfig) {
            throw new UnsupportedModeException(
                'Maximum profit-sharing ratio queries require PartnerConfig.'
            );
        }

        $this->requireValue($subMchid, 'sub_mchid');

        return $this->identity($subMchid, '', $brandMchId);
    }

    /** 组装 V2 商户身份、随机串和签名类型。 */
    private function identity(
        string $subMchid = '',
        string $subAppid = '',
        string $brandMchId = '',
        bool $withAppId = false,
        bool $requireSubMchid = true,
    ): array {
        $identity = [
            'mch_id' => $this->config->mch_id,
            'nonce_str' => bin2hex(random_bytes(16)),
            'sign_type' => 'HMAC-SHA256',
        ];

        if ($withAppId) {
            $identity['appid'] = $this->config->app_id;
        }

        if ($this->config instanceof PartnerConfig) {
            if ($requireSubMchid) {
                $this->requireValue($subMchid, 'sub_mchid');
            }

            if ($subMchid !== '') {
                $identity['sub_mch_id'] = $subMchid;
            }
            $identity['sub_appid'] = $subAppid;
            $identity['brand_mch_id'] = $brandMchId;
        }

        return $this->filter($identity);
    }

    /** 校验创建分账时的接收方数据。 */
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

        return $this->filter(array_replace($dto->extra, [
            'type' => $dto->type,
            'account' => $dto->account,
            'name' => $dto->name,
            'relation_type' => $withRelation ? $dto->relation_type : '',
            'custom_relation' => $withRelation ? $dto->custom_relation : '',
        ]));
    }

    /** 校验微信订单号和商户分账单号。 */
    private function requireOrderReference(ProfitSharingDto $dto): void
    {
        $this->requireValue($dto->transaction_id, 'transaction_id');
        $this->requireValue($dto->out_order_no, 'out_order_no');
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

    /** 将 V2 接收方结构编码为 JSON 字符串。 */
    private function encodeJson(array $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR,
        );
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
