<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Combine\V3;

use Qinii\WechatPayment\Combine\CombineDto;
use Qinii\WechatPayment\Combine\SubOrderDto;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\Abstract\AbstractBuilder;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentDto;

final class Builder extends AbstractBuilder
{
    /** 使用普通商户或服务商配置初始化合单 Builder。 */
    public function __construct(private AbstractWechatPayConfig $config)
    {
    }

    /** 校验合单 DTO 及合单商户配置的长度和必填项。 */
    protected function validate(AbstractPaymentDto $dto): void
    {
        $dto = $this->combineDto($dto);
        $dto->validate();

        $this->validateLength($this->config->app_id, 32, 'combine_appid');
        $this->validateLength($this->config->mch_id, 32, 'combine_mchid');

        if ($this->config->app_id === '') {
            throw new PaymentException(
                'Combined payment config [app_id] is required.'
            );
        }

        if ($this->config->mch_id === '') {
            throw new PaymentException(
                'Combined payment config [mch_id] is required.'
            );
        }

        $this->validateSubOrders($dto);
        $this->validateSceneInfo($this->sceneInfo($dto), $dto->payType());
    }

    /** 将合单 DTO 组装为微信 V3 合单支付请求。 */
    protected function toArray(AbstractPaymentDto $dto): array
    {
        $dto = $this->combineDto($dto);

        $data = array_replace($dto->extra, [
            'combine_appid' => $this->config->app_id,
            'combine_mchid' => $this->config->mch_id,
            'combine_out_trade_no' => $dto->combine_out_trade_no,
            'sub_orders' => array_map(
                fn (SubOrderDto $subOrder): array => $this->subOrder($subOrder),
                $dto->sub_orders,
            ),
            'time_expire' => $dto->time_expire,
            'notify_url' => $dto->notify_url,
        ]);

        if ($dto->payType() !== PayType::NATIVE && $dto->time_start !== '') {
            $data['time_start'] = $dto->time_start;
        }

        $sceneInfo = $this->sceneInfo($dto);
        if ($sceneInfo !== []) {
            $data['scene_info'] = $sceneInfo;
        }

        if (in_array(
            $dto->payType(),
            [PayType::OFFICIAL, PayType::MINI_PROGRAM],
            true,
        )) {
            $data['combine_payer_info'] = [
                'openid' => $dto->openid,
            ];
        }

        return array_filter(
            $data,
            static fn ($value): bool => $value !== '' && $value !== null,
        );
    }

    /** 将单个子订单转换为微信合单接口结构。 */
    private function subOrder(SubOrderDto $dto): array
    {
        $data = array_replace($dto->extra, [
            'mchid' => $dto->mchid,
            'attach' => $dto->attach,
            'amount' => [
                'total_amount' => $dto->total_amount,
                'currency' => $dto->currency,
            ],
            'out_trade_no' => $dto->out_trade_no,
            'description' => $dto->description,
            'settle_info' => $dto->settle_info,
            'goods_tag' => $dto->goods_tag,
        ]);

        if ($this->config instanceof PartnerConfig) {
            $data['sub_mchid'] = $dto->sub_mchid;
            $data['sub_appid'] = $dto->sub_appid;
        }

        return array_filter(
            $data,
            static fn ($value): bool => $value !== '' && $value !== null,
        );
    }

    /** 确认 Builder 接收的是 CombineDto。 */
    private function combineDto(AbstractPaymentDto $dto): CombineDto
    {
        if (! $dto instanceof CombineDto) {
            throw new PaymentException(
                'V3 combined PaymentBuilder only supports CombineDto.'
            );
        }

        return $dto;
    }

    /** 校验合单字段不超过微信接口限制。 */
    private function validateLength(
        string $value,
        int $maxLength,
        string $field,
    ): void {
        if (strlen($value) > $maxLength) {
            throw new PaymentException(
                "Combined payment [{$field}] must not exceed {$maxLength} bytes."
            );
        }
    }

    /** 按普通商户或服务商协议校验商品单数量和商户字段。 */
    private function validateSubOrders(CombineDto $dto): void
    {
        $partner = $this->config instanceof PartnerConfig;
        $maxOrders = $partner ? 50 : 10;
        $count = count($dto->sub_orders);

        if ($count < 2 || $count > $maxOrders) {
            throw new PaymentException(
                "Combined payment [sub_orders] must contain 2-{$maxOrders} orders."
            );
        }

        foreach ($dto->sub_orders as $subOrder) {
            if ($partner && $subOrder->sub_mchid === '') {
                throw new PaymentException(
                    'Partner combined payment [sub_orders.sub_mchid] is required.'
                );
            }

            if (! $partner && ! preg_match(
                '/^[0-9A-Za-z_\-|*]{6,32}$/D',
                $subOrder->out_trade_no,
            )) {
                throw new PaymentException(
                    'Ordinary combined payment [sub_orders.out_trade_no] must be 6-32 valid characters.'
                );
            }
        }

        if (! $partner && ! preg_match(
            '/^[0-9A-Za-z_\-|*]{6,32}$/D',
            $dto->combine_out_trade_no,
        )) {
            throw new PaymentException(
                'Ordinary combined payment [combine_out_trade_no] must be 6-32 valid characters.'
            );
        }
    }

    /** 合并 DTO、配置默认值和 H5 场景信息。 */
    private function sceneInfo(CombineDto $dto): array
    {
        $sceneInfo = $dto->scene_info ?? [];
        $sceneInfo['payer_client_ip'] = $this->firstString(
            $dto->payer_client_ip,
            $sceneInfo['payer_client_ip'] ?? null,
            $this->config->spbill_create_ip ?? null,
        );
        $sceneInfo['device_id'] = $this->firstString(
            $dto->device_id,
            $sceneInfo['device_id'] ?? null,
        );

        if ($dto->payType() === PayType::H5) {
            $dtoH5Info = $sceneInfo['h5_info'] ?? [];
            $configH5Info = $this->config->h5['h5_info']
                ?? $this->config->h5
                ?? [];

            if (! is_array($dtoH5Info) || ! is_array($configH5Info)) {
                throw new PaymentException(
                    'CombineDto [scene_info.h5_info] must be array.'
                );
            }

            $h5Info = array_replace(
                ['type' => 'Wap'],
                $configH5Info,
                $dtoH5Info,
            );
            $h5Info['type'] = match (strtolower((string) $h5Info['type'])) {
                'wap' => 'Wap',
                'ios' => 'iOS',
                'android' => 'Android',
                default => $h5Info['type'],
            };
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
        }

        return array_filter(
            $sceneInfo,
            static fn ($value): bool => $value !== ''
                && $value !== null
                && $value !== [],
        );
    }

    /** 校验当前支付类型所需的场景信息。 */
    private function validateSceneInfo(array $sceneInfo, string $payType): void
    {
        if ($payType === PayType::H5) {
            if (($sceneInfo['device_id'] ?? '') === '') {
                throw new PaymentException(
                    'CombineDto [device_id] is required for H5 payment.'
                );
            }

            $this->validateH5Info($sceneInfo['h5_info'] ?? []);
        }

        if ($sceneInfo === []) {
            return;
        }

        $clientIp = $sceneInfo['payer_client_ip'] ?? null;
        if (
            ! is_string($clientIp)
            || filter_var($clientIp, FILTER_VALIDATE_IP) === false
        ) {
            throw new PaymentException(
                'CombineDto [payer_client_ip] must be a valid IPv4 or IPv6 address.'
            );
        }

        $deviceId = $sceneInfo['device_id'] ?? '';
        $maxLength = $payType === PayType::H5 ? 32 : 16;
        if (! is_string($deviceId) || strlen($deviceId) > $maxLength) {
            throw new PaymentException(
                "CombineDto [device_id] must not exceed {$maxLength} bytes."
            );
        }
    }

    /** 校验合单 H5 场景类型和可选字段长度。 */
    private function validateH5Info(mixed $h5Info): void
    {
        if (! is_array($h5Info)) {
            throw new PaymentException(
                'CombineDto [scene_info.h5_info] must be array.'
            );
        }

        $type = strtolower((string) ($h5Info['type'] ?? ''));
        if (! in_array($type, ['wap', 'ios', 'android'], true)) {
            throw new PaymentException(
                'CombineDto [scene_info.h5_info.type] must be Wap, iOS or Android.'
            );
        }

        foreach ([
            'app_name' => 64,
            'app_url' => 128,
            'bundle_id' => 128,
            'package_name' => 128,
        ] as $field => $maxLength) {
            $value = $h5Info[$field] ?? '';
            if (! is_string($value) || strlen($value) > $maxLength) {
                throw new PaymentException(
                    "CombineDto [scene_info.h5_info.{$field}] must be a string no longer than {$maxLength} bytes."
                );
            }
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
}
