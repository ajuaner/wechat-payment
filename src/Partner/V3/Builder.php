<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Partner\V3;

use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Partner\PartnerDto;
use Qinii\WechatPayment\Shared\Abstract\AbstractBuilder;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentDto;

class Builder extends AbstractBuilder
{
    /** 使用服务商配置初始化 V3 支付 Builder。 */
    public function __construct(private PartnerConfig $config)
    {
    }

    /** 校验服务商支付的子商户、付款人和 V3 专属字段。 */
    protected function validate(AbstractPaymentDto $dto): void
    {
        $dto = $this->partnerPaymentDto($dto);

        $dto->validate();

        if (! in_array(
            $dto->payType(),
            [
                PayType::OFFICIAL,
                PayType::MINI_PROGRAM,
                PayType::APP,
                PayType::H5,
                PayType::NATIVE,
            ],
            true,
        )) {
            throw new PaymentException(
                'V3 PartnerBuilder does not support this payment type.'
            );
        }

        $this->validateLength($this->config->app_id, 32, 'sp_appid');
        $this->validateLength($this->config->mch_id, 32, 'sp_mchid');
        $this->validateLength($dto->sub_mchid, 32, 'sub_mchid');
        $this->validateLength($dto->sub_appid, 32, 'sub_appid');
        $this->validateLength($dto->description, 127, 'description');
        $this->validateLength($dto->attach, 128, 'attach');
        $this->validateLength($dto->goods_tag, 32, 'goods_tag');
        $this->validateLength($dto->openid, 128, 'openid');
        $this->validateLength($dto->sub_openid, 128, 'sub_openid');

        if ($dto->fee_type !== 'CNY') {
            throw new PaymentException(
                'V3 PartnerDto [fee_type] must be CNY.'
            );
        }

        if (
            $dto->time_expire !== ''
            && \DateTimeImmutable::createFromFormat(
                'Y-m-d\\TH:i:sP',
                $dto->time_expire,
            ) === false
        ) {
            throw new PaymentException(
                'V3 PartnerDto [time_expire] must use RFC3339 format.'
            );
        }

        $this->profitSharing($dto->profit_sharing);
        $this->validateSettleInfo($dto->settle_info);
        $this->validateDetail($dto->detail);
        $this->validateSceneInfo($dto->scene_info, $dto->payType());
    }

    /** 将服务商支付 DTO 组装为包含 sp_appid/sp_mchid 的 V3 参数。 */
    protected function toArray(AbstractPaymentDto $dto): array
    {
        $dto = $this->partnerPaymentDto($dto);

        $data = array_replace($dto->extra, [
            'sp_appid' => $this->config->app_id,
            'sp_mchid' => $this->config->mch_id,
            'sub_appid' => $dto->sub_appid,
            'sub_mchid' => $dto->sub_mchid,
            'description' => $dto->description,
            'out_trade_no' => $dto->out_trade_no,
            'time_expire' => $dto->time_expire,
            'attach' => $dto->attach,
            'notify_url' => $dto->notify_url,
            'goods_tag' => $dto->goods_tag,
            'amount' => [
                'total' => $dto->total_fee,
                'currency' => $dto->fee_type,
            ],
        ]);

        if (in_array(
            $dto->payType(),
            [PayType::OFFICIAL, PayType::MINI_PROGRAM],
            true,
        )) {
            $data['payer'] = array_filter([
                'sp_openid' => $dto->openid,
                'sub_openid' => $dto->sub_openid,
            ], static fn ($value): bool => $value !== '');
        }

        $settleInfo = $dto->settle_info ?? [];
        if (! array_key_exists('profit_sharing', $settleInfo)) {
            $settleInfo['profit_sharing'] = $this->profitSharing(
                $dto->profit_sharing,
            );
        }
        $data['settle_info'] = $settleInfo;

        if ($dto->support_fapiao) {
            $data['support_fapiao'] = true;
        }

        if ($dto->detail !== null && $dto->detail !== []) {
            $data['detail'] = $dto->detail;
        }

        if ($dto->scene_info !== null && $dto->scene_info !== []) {
            $data['scene_info'] = $dto->scene_info;
        }

        return $data;
    }

    /** 校验服务商支付字段不超过微信接口限制。 */
    private function validateLength(
        string $value,
        int $maxLength,
        string $field,
    ): void {
        if (strlen($value) > $maxLength) {
            throw new PaymentException(
                "V3 PartnerDto [{$field}] must not exceed {$maxLength} bytes."
            );
        }
    }

    /** 校验结算信息中的分账字段类型。 */
    private function validateSettleInfo(?array $settleInfo): void
    {
        if (
            $settleInfo !== null
            && array_key_exists('profit_sharing', $settleInfo)
            && ! is_bool($settleInfo['profit_sharing'])
        ) {
            throw new PaymentException(
                'V3 PartnerDto [settle_info.profit_sharing] must be boolean.'
            );
        }
    }

    /** 校验商品详情数组结构和字段长度。 */
    private function validateDetail(?array $detail): void
    {
        if ($detail === null || $detail === []) {
            return;
        }

        $json = json_encode(
            $detail,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR,
        );

        if (strlen($json) > 6144) {
            throw new PaymentException(
                'V3 PartnerDto [detail] must not exceed 6144 bytes.'
            );
        }
    }

    /** 校验服务商支付场景信息和 H5 场景字段。 */
    private function validateSceneInfo(
        ?array $sceneInfo,
        string $payType,
    ): void
    {
        if ($payType === PayType::H5 && ($sceneInfo === null || $sceneInfo === [])) {
            throw new PaymentException(
                'V3 PartnerDto [scene_info] is required for H5 payment.'
            );
        }

        if ($sceneInfo === null || $sceneInfo === []) {
            return;
        }

        $clientIp = $sceneInfo['payer_client_ip'] ?? null;
        if (
            ! is_string($clientIp)
            || filter_var($clientIp, FILTER_VALIDATE_IP) === false
        ) {
            throw new PaymentException(
                'V3 PartnerDto [scene_info.payer_client_ip] must be a valid IPv4 or IPv6 address.'
            );
        }

        if ($payType !== PayType::H5) {
            return;
        }

        $h5Info = $sceneInfo['h5_info'] ?? null;
        if (! is_array($h5Info) || $h5Info === []) {
            throw new PaymentException(
                'V3 PartnerDto [scene_info.h5_info] is required for H5 payment.'
            );
        }

        $type = $h5Info['type'] ?? null;
        if (! is_string($type) || ! in_array(
            $type,
            ['Wap', 'iOS', 'Android'],
            true,
        )) {
            throw new PaymentException(
                'V3 PartnerDto [scene_info.h5_info.type] must be Wap, iOS or Android.'
            );
        }

        foreach ([
            'app_name' => 64,
            'app_url' => 128,
            'bundle_id' => 128,
            'package_name' => 128,
        ] as $field => $maxLength) {
            $value = $h5Info[$field] ?? '';
            if (! is_string($value)) {
                throw new PaymentException(
                    "V3 PartnerDto [scene_info.h5_info.{$field}] must be string."
                );
            }

            $this->validateLength(
                $value,
                $maxLength,
                "scene_info.h5_info.{$field}",
            );
        }
    }

    /** 校验并确认分账标识只能为 Y 或 N。 */
    private function profitSharing(string $value): bool
    {
        return match (strtoupper(trim($value))) {
            'Y', 'YES', 'TRUE', '1' => true,
            'N', 'NO', 'FALSE', '0', '' => false,
            default => throw new PaymentException(
                'PartnerDto [profit_sharing] must be Y or N.'
            ),
        };
    }

    /** 确认 Builder 接收的是服务商 PartnerDto。 */
    private function partnerPaymentDto(AbstractPaymentDto $dto): PartnerDto
    {
        if (! $dto instanceof PartnerDto) {
            throw new PaymentException(
                'V3 PartnerBuilder only supports PartnerDto.'
            );
        }

        return $dto;
    }
}
