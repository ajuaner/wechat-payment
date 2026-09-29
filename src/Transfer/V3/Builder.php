<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Transfer\V3;

use Qinii\WechatPayment\Config\TransferConfig;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Transfer\AuthorizationDto;
use Qinii\WechatPayment\Transfer\TransferDto;

final class Builder
{
    /** 使用商户付款到零钱配置初始化请求参数 Builder。 */
    public function __construct(private TransferConfig $config)
    {
    }

    /** 构造普通商户付款到零钱请求。 */
    public function build(TransferDto $dto): array
    {
        return $this->buildTransfer($dto);
    }

    /** 构造商户付款到零钱主接口请求参数。 */
    public function buildTransfer(TransferDto $dto): array
    {
        $this->validateTransfer($dto);

        return $this->filterPayload(array_replace($dto->extra, $this->commonPayload($dto)));
    }

    /** 构造转账并完成免确认收款授权的请求参数。 */
    public function buildPreTransferWithAuthorization(AuthorizationDto $dto): array
    {
        $this->validateTransfer($dto);
        $this->requireValue($dto->authorization_info, 'authorization_info');

        return $this->filterPayload(array_replace(
            $dto->extra,
            $this->commonPayload($dto),
            [
                'authorization_info' => $dto->authorization_info,
                'sponsor_mchid' => $dto->sponsor_mchid,
            ],
        ));
    }

    /** 构造免确认收款授权申请请求参数。 */
    public function buildAuthorization(AuthorizationDto $dto): array
    {
        $this->requireValue($dto->out_authorization_no, 'out_authorization_no');
        $this->requireValue($dto->openid, 'openid');
        $this->requireValue($dto->transfer_scene_id, 'transfer_scene_id');
        $this->requireValue($dto->user_display_name, 'user_display_name');
        $this->requireValue($dto->authorization_notify_url, 'authorization_notify_url');
        $this->validateLengths($dto);
        $this->validateUrl($dto->authorization_notify_url, 'authorization_notify_url');

        return $this->filterPayload(array_replace($dto->extra, [
            'appid' => $this->config->app_id,
            'out_authorization_no' => $dto->out_authorization_no,
            'openid' => $dto->openid,
            'transfer_scene_id' => $dto->transfer_scene_id,
            'user_display_name' => $dto->user_display_name,
            'user_recv_perception' => $dto->user_recv_perception,
            'authorization_notify_url' => $dto->authorization_notify_url,
            'scene_info' => $dto->scene_info,
        ]));
    }

    /** 构造使用免确认授权完成转账的请求参数。 */
    public function buildTransferAfterAuthorization(AuthorizationDto $dto): array
    {
        $this->validateTransfer($dto);

        if ($dto->authorization_id === '' && $dto->out_authorization_no === '') {
            throw new PaymentException(
                'AuthorizationDto requires authorization_id or out_authorization_no.'
            );
        }

        return $this->filterPayload(array_replace(
            $dto->extra,
            $this->commonPayload($dto),
            [
                'authorization_id' => $dto->authorization_id,
                'out_authorization_no' => $dto->out_authorization_no,
                'sponsor_mchid' => $dto->sponsor_mchid,
            ],
        ));
    }

    /** 组装普通转账和授权转账共用的请求字段。 */
    private function commonPayload(TransferDto|AuthorizationDto $dto): array
    {
        $remark = $dto->transfer_remark !== ''
            ? $dto->transfer_remark
            : $dto->transfer_desc;

        return [
            'appid' => $this->config->app_id,
            'out_bill_no' => $dto->out_bill_no,
            'transfer_scene_id' => $dto->transfer_scene_id,
            'openid' => $dto->openid,
            'user_name' => $dto->user_name,
            'transfer_amount' => $dto->transfer_amount,
            'transfer_remark' => $remark,
            'notify_url' => $dto->notify_url !== ''
                ? $dto->notify_url
                : $this->config->notify_url,
            'user_recv_perception' => $dto->user_recv_perception,
            'user_recv_style' => $dto->user_recv_style,
            'transfer_scene_report_infos' => $dto->transfer_scene_report_infos,
        ];
    }

    /** 校验转账请求的必填字段、报备信息和通知地址。 */
    private function validateTransfer(TransferDto|AuthorizationDto $dto): void
    {
        foreach ([
            'out_bill_no' => $dto->out_bill_no,
            'transfer_scene_id' => $dto->transfer_scene_id,
            'openid' => $dto->openid,
        ] as $field => $value) {
            $this->requireValue($value, $field);
        }

        $remark = $dto->transfer_remark !== ''
            ? $dto->transfer_remark
            : $dto->transfer_desc;
        $this->requireValue($remark, 'transfer_remark');

        if ($dto->transfer_amount <= 0) {
            throw new PaymentException(
                'TransferDto [transfer_amount] must be greater than 0.'
            );
        }

        if ($dto->transfer_amount >= 200000 && $dto->user_name === '') {
            throw new PaymentException(
                'TransferDto [user_name] is required when transfer_amount is at least 200000.'
            );
        }

        if (preg_match('/^[0-9A-Za-z]+$/D', $dto->out_bill_no) !== 1) {
            throw new PaymentException(
                'TransferDto [out_bill_no] may contain only numbers and letters.'
            );
        }

        $this->validateLengths($dto);
        $this->requireValue(
            $dto->transfer_scene_report_infos,
            'transfer_scene_report_infos',
        );

        foreach ($dto->transfer_scene_report_infos as $index => $item) {
            if (! is_array($item)) {
                throw new PaymentException(
                    "TransferDto [transfer_scene_report_infos.{$index}] must be array."
                );
            }

            if (
                ! is_string($item['info_type'] ?? null)
                || ! is_string($item['info_content'] ?? null)
                || trim($item['info_type']) === ''
                || trim($item['info_content']) === ''
            ) {
                throw new PaymentException(
                    "TransferDto [transfer_scene_report_infos.{$index}] requires info_type and info_content."
                );
            }

            if ($this->utf8Length(
                $item['info_type'],
                "transfer_scene_report_infos.{$index}.info_type",
            ) > 15) {
                throw new PaymentException(
                    "TransferDto [transfer_scene_report_infos.{$index}.info_type] must not exceed 15 characters."
                );
            }

            if ($this->utf8Length(
                $item['info_content'],
                "transfer_scene_report_infos.{$index}.info_content",
            ) > 32) {
                throw new PaymentException(
                    "TransferDto [transfer_scene_report_infos.{$index}.info_content] must not exceed 32 characters."
                );
            }
        }

        $notifyUrl = $dto->notify_url !== ''
            ? $dto->notify_url
            : $this->config->notify_url;

        if ($notifyUrl !== '') {
            $this->validateUrl($notifyUrl, 'notify_url');
        }
    }

    /** 校验转账和授权字段的 UTF-8 字符长度。 */
    private function validateLengths(TransferDto|AuthorizationDto $dto): void
    {
        $fields = [
            'out_bill_no' => [$dto->out_bill_no, 32],
            'transfer_scene_id' => [$dto->transfer_scene_id, 36],
            'openid' => [$dto->openid, 64],
            'transfer_remark' => [
                $dto->transfer_remark !== '' ? $dto->transfer_remark : $dto->transfer_desc,
                32,
            ],
            'user_name' => [$dto->user_name, 128],
            'user_recv_perception' => [$dto->user_recv_perception, 256],
        ];

        if ($dto instanceof AuthorizationDto) {
            $fields += [
                'out_authorization_no' => [$dto->out_authorization_no, 32],
                'user_display_name' => [$dto->user_display_name, 32],
                'sponsor_mchid' => [$dto->sponsor_mchid, 32],
            ];
        }

        foreach ($fields as $field => [$value, $maxLength]) {
            if ($this->utf8Length((string) $value, $field) > $maxLength) {
                throw new PaymentException(
                    "TransferDto [{$field}] must not exceed {$maxLength} characters."
                );
            }
        }
    }

    /** 计算 UTF-8 字符数，并拒绝无法按 UTF-8 解析的文本。 */
    private function utf8Length(string $value, string $field): int
    {
        $length = preg_match_all('/./us', $value);

        if ($length === false) {
            throw new PaymentException(
                "TransferDto [{$field}] must be valid UTF-8 text."
            );
        }

        return $length;
    }

    /** 校验转账请求字段不能为空。 */
    private function requireValue(mixed $value, string $field): void
    {
        if ($value === '' || $value === null || $value === []) {
            throw new PaymentException("TransferDto [{$field}] is required.");
        }
    }

    /** 校验通知地址必须为不携带参数的 HTTPS URL。 */
    private function validateUrl(string $url, string $field): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new PaymentException("TransferDto [{$field}] must be a valid URL.");
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'https') {
            throw new PaymentException("TransferDto [{$field}] must use HTTPS.");
        }

        if (
            parse_url($url, PHP_URL_QUERY) !== null
            || parse_url($url, PHP_URL_FRAGMENT) !== null
        ) {
            throw new PaymentException(
                "TransferDto [{$field}] must not contain query parameters or fragments."
            );
        }
    }

    /** 移除空字段，避免向微信发送无效的可选参数。 */
    private function filterPayload(array $payload): array
    {
        return array_filter(
            $payload,
            static fn ($value): bool => $value !== '' && $value !== null && $value !== [],
        );
    }
}
