<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Transfer;

use Qinii\WechatPayment\Shared\Traits\Fillable;

/**
 * Request data used by merchant-transfer user-confirmation flows.
 *
 * The same request model covers authorization application, pre-transfer with
 * authorization, and transfer after authorization. Each operation validates
 * only the fields it supports in the V3 builder.
 */
final class AuthorizationDto
{
    use Fillable;

    // Common transfer fields used by pre-transfer and post-authorization APIs.
    public string $out_bill_no = '';
    public string $transfer_scene_id = '';
    public string $openid = '';
    public int $transfer_amount = 0;
    public string $transfer_desc = '';
    public string $transfer_remark = '';
    /** @var array<int, array<string, mixed>> */
    public array $transfer_scene_report_infos = [];
    public string $user_name = '';
    public string $notify_url = '';
    public string $user_recv_perception = '';
    /** @var array<string, mixed> */
    public array $user_recv_style = [];

    // Authorization fields.
    public string $out_authorization_no = '';
    public string $user_display_name = '';
    public string $authorization_notify_url = '';
    /** @var array<string, mixed> */
    public array $authorization_info = [];
    public array $scene_info = [];
    public string $authorization_id = '';
    public string $sponsor_mchid = '';

    /** @var array<string, mixed> */
    public array $extra = [];

    /** 标准化授权转账 DTO 的金额和数组字段。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'transfer_amount' => (int) $value,
            'transfer_scene_report_infos', 'user_recv_style', 'authorization_info', 'scene_info', 'extra'
                => is_array($value) ? $value : [],
            default => $value,
        };
    }
}
