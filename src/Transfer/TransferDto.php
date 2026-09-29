<?php
declare(strict_types=1);

namespace Qinii\WechatPayment\Transfer;

use Qinii\WechatPayment\Shared\Traits\Fillable;

class TransferDto
{

    use Fillable;

    //商户单号
    public string $out_bill_no = '';
    //转账场景ID
    public string $transfer_scene_id = '';
    //收款用户OpenID
    public string $openid = '';
    //转账金额 单位为“分”。
    public int $transfer_amount = 0;
    //转账备注，用户收款时可见该备注信息
    public string $transfer_desc = '';
    // API v3 field name; transfer_desc remains a compatibility alias.
    public string $transfer_remark = '';
    /**
     * 需按转账场景准确填写报备信息
     * [
     * //信息类型
     *  info_type => 'transfer_scene_id',
     * //信息内容
     *  info_content => 'transfer_scene_id',
     * ]
     */
    public array $transfer_scene_report_infos = [];

    /**
     * 选填
     */
    //收款用户名
    public string $user_name = '';
    //通知URL
    public string $notify_url = '';
    //用户接收感知
    public string $user_recv_perception = '';
    //用户接收样式 [type : CONFIRM_PAGE || RED_PACKET]
    public array $user_recv_style = [];
    /** @var array<string, mixed> */
    public array $extra = [];

    /** 创建空的普通商户付款到零钱 DTO。 */
    public function __construct()
    {
    }

    /** 标准化金额和转账接口中的数组字段。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'transfer_amount' => (int) $value,
            'transfer_scene_report_infos', 'user_recv_style', 'extra'
                => is_array($value) ? $value : [],
            default => $value,
        };
    }
}
