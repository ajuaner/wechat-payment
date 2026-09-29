<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Partner\V2;

use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Partner\PartnerDto;
use Qinii\WechatPayment\Shared\Abstract\AbstractBuilder;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentDto;

final class Builder extends AbstractBuilder
{
    /** 使用服务商配置初始化 V2 参数构造器。 */
    public function __construct(private PartnerConfig $config)
    {
    }

    /** 构造付款码支付参数，并移除统一下单专属字段。 */
    public function buildMicropay(AbstractPaymentDto $dto): array
    {
        $this->validate($dto);
        $data = $this->toArray($dto);

        unset($data['notify_url'], $data['trade_type']);

        return $data;
    }

    /** 校验服务商 V2 支付的公共字段和支付类型专属字段。 */
    protected function validate(AbstractPaymentDto $dto): void
    {
        $dto = $this->partnerDto($dto);
        $dto->validate();

        $this->validateLength($dto->sub_mchid, 32, 'sub_mch_id');
        $this->validateLength($dto->sub_appid, 32, 'sub_appid');
        $this->validateLength($dto->description, 127, 'body');
        $this->validateLength($dto->attach, 127, 'attach');
        $this->validateLength($dto->fee_type, 16, 'fee_type');
        $this->validateLength($dto->goods_tag, 32, 'goods_tag');
        $this->validateLength($dto->openid, 128, 'openid');
        $this->validateLength($dto->sub_openid, 128, 'sub_openid');

        foreach (['time_start', 'time_expire'] as $field) {
            if (
                $dto->{$field} !== ''
                && preg_match('/^\d{14}$/D', $dto->{$field}) !== 1
            ) {
                throw new PaymentException(
                    "V2 PartnerDto [{$field}] must use yyyyMMddHHmmss format."
                );
            }
        }

        if (! in_array($dto->profit_sharing, ['Y', 'N'], true)) {
            throw new PaymentException(
                'V2 PartnerDto [profit_sharing] must be Y or N.'
            );
        }

        $this->validateJsonField($dto->detail, 'detail', 6144);

        match ($dto->payType()) {
            PayType::OFFICIAL,
            PayType::MINI_PROGRAM => $this->validatePayer($dto),
            PayType::NATIVE => $this->validateNative($dto),
            PayType::MICROPAY => $this->validateMicropay($dto),
            PayType::H5 => $this->validateH5($dto),
            PayType::APP => null,
            default => throw new PaymentException(
                "Unsupported V2 partner pay type: {$dto->payType()}"
            ),
        };
    }

    /** 将服务商 DTO 转换为 V2 统一下单或付款码支付参数。 */
    protected function toArray(AbstractPaymentDto $dto): array
    {
        $dto = $this->partnerDto($dto);
        $data = array_replace($dto->extra, [
            'body' => $dto->description,
            'detail' => $this->encodeJson($dto->detail),
            'attach' => $dto->attach,
            'out_trade_no' => $dto->out_trade_no,
            'fee_type' => $dto->fee_type,
            'total_fee' => $dto->total_fee,
            'time_start' => $dto->time_start,
            'time_expire' => $dto->time_expire,
            'goods_tag' => $dto->goods_tag,
            'notify_url' => $dto->notify_url,
            'trade_type' => $dto->tradeType(),
            'profit_sharing' => $dto->profit_sharing,
            'receipt' => $dto->support_fapiao ? 'Y' : '',
        ]);

        if (in_array(
            $dto->payType(),
            [PayType::OFFICIAL, PayType::MINI_PROGRAM],
            true,
        )) {
            $data['openid'] = $dto->openid;
            $data['sub_openid'] = $dto->sub_openid;
        }

        if ($dto->payType() === PayType::NATIVE) {
            $data['product_id'] = $dto->product_id;
        }

        if ($dto->payType() === PayType::MICROPAY) {
            $data['auth_code'] = $dto->auth_code;
        }

        $sceneInfo = $dto->payType() === PayType::H5
            ? $this->resolveH5SceneInfo($dto)
            : $dto->scene_info;

        if ($sceneInfo !== null && $sceneInfo !== []) {
            $data['scene_info'] = $this->encodeJson($sceneInfo);
        }

        return $data;
    }

    /** 校验 JSAPI 或小程序支付的用户标识。 */
    private function validatePayer(PartnerDto $dto): void
    {
        if ($dto->openid === '' && $dto->sub_openid === '') {
            throw new PaymentException(
                'V2 PartnerDto [openid] or [sub_openid] is required.'
            );
        }

        if ($dto->sub_openid !== '' && $dto->sub_appid === '') {
            throw new PaymentException(
                'V2 PartnerDto [sub_appid] is required when [sub_openid] is used.'
            );
        }
    }

    /** 校验 Native 支付必填的商品 ID。 */
    private function validateNative(PartnerDto $dto): void
    {
        if ($dto->product_id === '') {
            throw new PaymentException(
                'V2 PartnerDto [product_id] is required for Native payment.'
            );
        }

        $this->validateLength($dto->product_id, 32, 'product_id');
    }

    /** 校验付款码支付使用的授权码。 */
    private function validateMicropay(PartnerDto $dto): void
    {
        if (preg_match('/^1[0-5]\d{16}$/D', $dto->auth_code) !== 1) {
            throw new PaymentException(
                'V2 PartnerDto [auth_code] must be an 18-digit payment code with prefix 10-15.'
            );
        }

        $this->validateJsonField($dto->detail, 'detail', 6000);
    }

    /** 校验并合并 H5 支付场景参数。 */
    private function validateH5(PartnerDto $dto): void
    {
        $sceneInfo = $this->resolveH5SceneInfo($dto);
        $h5Info = $sceneInfo['h5_info'];
        $type = strtolower((string) ($h5Info['type'] ?? ''));

        match ($type) {
            'wap' => $this->requireH5Fields(
                $h5Info,
                ['wap_url', 'wap_name'],
            ),
            'ios' => $this->requireH5Fields(
                $h5Info,
                ['app_name', 'bundle_id'],
            ),
            'android' => $this->requireH5Fields(
                $h5Info,
                ['app_name', 'package_name'],
            ),
            default => throw new PaymentException(
                'V2 PartnerDto [scene_info.h5_info.type] must be Wap, IOS or Android.'
            ),
        };

        if (
            $type === 'wap'
            && filter_var($h5Info['wap_url'], FILTER_VALIDATE_URL) === false
        ) {
            throw new PaymentException(
                'V2 PartnerDto [scene_info.h5_info.wap_url] must be a valid URL.'
            );
        }

        $this->validateJsonField($sceneInfo, 'scene_info', 256);
    }

    /** 合并 DTO、模块配置和通知地址生成 H5 场景信息。 */
    private function resolveH5SceneInfo(PartnerDto $dto): array
    {
        $sceneInfo = $dto->scene_info ?? [];
        $dtoH5Info = $sceneInfo['h5_info'] ?? [];
        $configuredH5Info = $this->config->h5['h5_info']
            ?? $this->config->h5;

        if (! is_array($dtoH5Info) || ! is_array($configuredH5Info)) {
            throw new PaymentException(
                'V2 PartnerDto [scene_info.h5_info] must be array.'
            );
        }

        $h5Info = array_replace(
            ['type' => 'Wap'],
            $configuredH5Info,
            $dtoH5Info,
        );

        if (strtolower((string) $h5Info['type']) === 'wap') {
            $origin = $this->siteOrigin(
                $dto->notify_url !== ''
                    ? $dto->notify_url
                    : $this->config->notify_url,
            );
            $h5Info['wap_url'] = $this->firstString(
                $h5Info['wap_url'] ?? null,
                $h5Info['app_url'] ?? null,
                $origin,
            );
            $h5Info['wap_name'] = $this->firstString(
                $h5Info['wap_name'] ?? null,
                $h5Info['app_name'] ?? null,
                $origin === null
                    ? null
                    : parse_url($origin, PHP_URL_HOST),
            );
        }

        $allowedFields = match (strtolower((string) ($h5Info['type'] ?? ''))) {
            'wap' => ['type', 'wap_url', 'wap_name'],
            'ios' => ['type', 'app_name', 'bundle_id'],
            'android' => ['type', 'app_name', 'package_name'],
            default => ['type'],
        };
        $sceneInfo['h5_info'] = array_intersect_key(
            $h5Info,
            array_flip($allowedFields),
        );

        return $sceneInfo;
    }

    /** 校验 H5 场景中的指定字段均为非空字符串。 */
    private function requireH5Fields(array $h5Info, array $fields): void
    {
        foreach ($fields as $field) {
            if (! is_string($h5Info[$field] ?? null) || trim($h5Info[$field]) === '') {
                throw new PaymentException(
                    "V2 PartnerDto requires scene_info.h5_info.{$field}."
                );
            }
        }
    }

    /** 校验字符串字段不超过微信接口限制。 */
    private function validateLength(
        string $value,
        int $maxLength,
        string $field,
    ): void {
        if (strlen($value) > $maxLength) {
            throw new PaymentException(
                "V2 PartnerDto [{$field}] must not exceed {$maxLength} bytes."
            );
        }
    }

    /** 校验数组字段编码后的 JSON 长度。 */
    private function validateJsonField(
        ?array $value,
        string $field,
        int $maxLength,
    ): void {
        if ($value === null || $value === []) {
            return;
        }

        if (strlen($this->encodeJson($value)) > $maxLength) {
            throw new PaymentException(
                "V2 PartnerDto [{$field}] must not exceed {$maxLength} bytes."
            );
        }
    }

    /** 将数组编码为微信 V2 接口需要的 JSON 字符串。 */
    private function encodeJson(?array $value): string
    {
        if ($value === null || $value === []) {
            return '';
        }

        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR,
        );
    }

    /** 返回候选值中的第一个非空字符串。 */
    private function firstString(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /** 从通知地址中提取站点根地址。 */
    private function siteOrigin(string $url): ?string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host)) {
            return null;
        }

        $port = parse_url($url, PHP_URL_PORT);

        return sprintf(
            '%s://%s%s',
            $scheme,
            $host,
            is_int($port) ? ':' . $port : '',
        );
    }

    /** 确认参数构造器接收的是服务商支付 DTO。 */
    private function partnerDto(AbstractPaymentDto $dto): PartnerDto
    {
        if (! $dto instanceof PartnerDto) {
            throw new PaymentException(
                'V2 PartnerBuilder only supports PartnerDto.'
            );
        }

        return $dto;
    }
}
