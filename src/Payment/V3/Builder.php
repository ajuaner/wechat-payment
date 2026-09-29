<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Payment\V3;

use Qinii\WechatPayment\Payment\PaymentDto;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\Abstract\AbstractBuilder;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentDto;

class Builder extends AbstractBuilder
{
    /**
     * @param array<string, mixed> $h5Config
     */
    /** 初始化 V3 支付 Builder 的 H5 默认场景和客户端 IP。 */
    public function __construct(
        private array $h5Config = [],
        private string $defaultClientIp = '',
    ) {
    }

    /** 校验 V3 普通支付字段和支付类型限制。 */
    protected function validate(AbstractPaymentDto $dto): void
    {
        $dto = $this->paymentDto($dto);
        $this->validateCommonFields($dto);

        match ($dto->payType()) {
            PayType::OFFICIAL,
            PayType::MINI_PROGRAM => $this->requireOpenid($dto),
            PayType::H5 => $this->validateH5($dto),
            PayType::APP,
            PayType::NATIVE => null,
            PayType::MICROPAY => throw new PaymentException(
                'Wechat Pay API V3 does not support micropay in this driver.'
            ),
            default => throw new PaymentException(
                "Unsupported V3 pay type: {$dto->payType()}"
            ),
        };
    }

    /** 将普通支付 DTO 组装为 V3 JSON 请求参数。 */
    protected function toArray(AbstractPaymentDto $dto): array
    {
        $dto = $this->paymentDto($dto);
        $data = array_replace($dto->extra, [
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

        if ($dto->support_fapiao) {
            $data['support_fapiao'] = true;
        }

        if ($dto->openid !== '') {
            $data['payer'] = [
                'openid' => $dto->openid,
            ];
        }

        if ($dto->detail !== null && $dto->detail !== []) {
            $data['detail'] = $dto->detail;
        }

        $sceneInfo = $dto->payType() === PayType::H5
            ? $this->resolveH5SceneInfo($dto)
            : $dto->scene_info;

        if ($sceneInfo !== null && $sceneInfo !== []) {
            $data['scene_info'] = $sceneInfo;
        }

        $settleInfo = $dto->settle_info ?? [];
        if ($settleInfo !== [] || $dto->profit_sharing === 'Y') {
            if (! array_key_exists('profit_sharing', $settleInfo)) {
                $settleInfo['profit_sharing'] = $dto->profit_sharing === 'Y';
            }

            $data['settle_info'] = $settleInfo;
        }

        return $data;
    }

    /** 校验 JSAPI 或小程序支付所需的 openid。 */
    private function requireOpenid(PaymentDto $dto): void
    {
        if ($dto->openid === '') {
            throw new PaymentException(
                'V3 JSAPI payment requires PaymentDto [openid].'
            );
        }
    }

    /** 校验 V3 H5 支付的客户端 IP 和场景类型。 */
    private function validateH5(PaymentDto $dto): void
    {
        $sceneInfo = $this->resolveH5SceneInfo($dto);

        if (empty($sceneInfo['payer_client_ip'])) {
            throw new PaymentException(
                'V3 H5 payment requires scene_info.payer_client_ip.'
            );
        }

        if (
            filter_var($sceneInfo['payer_client_ip'], FILTER_VALIDATE_IP)
            === false
        ) {
            throw new PaymentException(
                'V3 H5 payment [scene_info.payer_client_ip] must be a valid IPv4 or IPv6 address.'
            );
        }

        $type = strtolower((string) ($sceneInfo['h5_info']['type'] ?? ''));

        if (! in_array($type, ['wap', 'ios', 'android'], true)) {
            throw new PaymentException(
                'V3 H5 payment [scene_info.h5_info.type] must be Wap, iOS or Android.'
            );
        }
    }

    /** 合并 DTO、配置默认值和 V3 H5 场景信息。 */
    private function resolveH5SceneInfo(PaymentDto $dto): array
    {
        $sceneInfo = $dto->scene_info ?? [];
        $dtoH5Info = $sceneInfo['h5_info'] ?? [];
        $configuredH5Info = $this->h5Config['h5_info']
            ?? $this->h5Config;

        if (! is_array($dtoH5Info) || ! is_array($configuredH5Info)) {
            throw new PaymentException(
                'V3 PaymentDto [scene_info.h5_info] must be array.'
            );
        }

        $sceneInfo['payer_client_ip'] = $this->firstString(
            $dto->payer_client_ip,
            $sceneInfo['payer_client_ip'] ?? null,
            $this->defaultClientIp,
        );
        $h5Info = array_replace(
            ['type' => 'Wap'],
            $configuredH5Info,
            $dtoH5Info,
        );
        $h5Info['app_name'] = $this->firstString(
            $h5Info['app_name'] ?? null,
            $h5Info['wap_name'] ?? null,
        );
        $h5Info['app_url'] = $this->firstString(
            $h5Info['app_url'] ?? null,
            $h5Info['wap_url'] ?? null,
        );
        $sceneInfo['h5_info'] = array_filter(
            array_intersect_key($h5Info, array_flip([
                'type',
                'app_name',
                'app_url',
                'bundle_id',
                'package_name',
            ])),
            static fn ($value): bool => $value !== '' && $value !== null,
        );

        return $sceneInfo;
    }

    /** 校验 V3 支付通用文本、币种和过期时间。 */
    private function validateCommonFields(PaymentDto $dto): void
    {
        if (strlen($dto->description) > 127) {
            throw new PaymentException(
                'V3 PaymentDto [description] must not exceed 127 bytes.'
            );
        }

        if (strlen($dto->attach) > 128) {
            throw new PaymentException(
                'V3 PaymentDto [attach] must not exceed 128 bytes.'
            );
        }

        if ($dto->fee_type !== 'CNY') {
            throw new PaymentException(
                'V3 PaymentDto [fee_type] must be CNY.'
            );
        }

        if (! in_array($dto->profit_sharing, ['Y', 'N'], true)) {
            throw new PaymentException(
                'V3 PaymentDto [profit_sharing] must be Y or N.'
            );
        }

        if (
            $dto->settle_info !== null
            && array_key_exists('profit_sharing', $dto->settle_info)
            && ! is_bool($dto->settle_info['profit_sharing'])
        ) {
            throw new PaymentException(
                'V3 PaymentDto [settle_info.profit_sharing] must be boolean.'
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
                'V3 PaymentDto [time_expire] must use RFC3339 format.'
            );
        }
    }

    /** 返回多个候选值中第一个非空字符串。 */
    private function firstString(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /** 确认 Builder 接收的是普通商户 PaymentDto。 */
    private function paymentDto(AbstractPaymentDto $dto): PaymentDto
    {
        if (! $dto instanceof PaymentDto) {
            throw new PaymentException(
                'V3 PaymentBuilder only supports PaymentDto.'
            );
        }

        return $dto;
    }
}
