<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Payment\V2;

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
    /** 初始化 V2 支付 Builder 的 H5 默认场景和通知地址。 */
    public function __construct(
        private array $h5Config = [],
        private string $notifyUrl = '',
    ) {
    }

    /**
     * 付款码支付不使用统一下单的 trade_type 和 notify_url。
     *
     * @return array<string, mixed>
     */
    public function buildMicropay(AbstractPaymentDto $dto): array
    {
        $this->validate($dto);
        $data = $this->toArray($dto);

        unset(
            $data['notify_url'],
            $data['trade_type'],
        );

        return $data;
    }

    /** 校验 V2 普通支付字段，并按支付类型检查专属参数。 */
    protected function validate(AbstractPaymentDto $dto): void
    {
        $dto = $this->paymentDto($dto);
        $this->validateCommonFields($dto);

        match ($dto->payType()) {
            PayType::OFFICIAL,
            PayType::MINI_PROGRAM => $this->requireFields($dto, ['openid']),
            PayType::NATIVE => $this->requireFields($dto, ['product_id']),
            PayType::MICROPAY => $this->validateMicropay($dto),
            PayType::H5 => $this->validateH5($dto),
            PayType::APP => null,
            default => throw new PaymentException(
                "Unsupported V2 pay type: {$dto->payType()}"
            ),
        };
    }

    /** 将普通支付 DTO 组装为 V2 统一下单参数。 */
    protected function toArray(AbstractPaymentDto $dto): array
    {
        $dto = $this->paymentDto($dto);
        $data = array_replace($dto->extra, [
            'body'      => $dto->description,
            'total_fee' => $dto->total_fee,
            'attach'    => $dto->attach,
            'out_trade_no' => $dto->out_trade_no,
            'fee_type'  => $dto->fee_type,
            'time_start'  => $dto->time_start,
            'time_expire' => $dto->time_expire,
            'goods_tag'   => $dto->goods_tag,
            'profit_sharing' => $dto->profit_sharing,
            'trade_type' => $dto->tradeType(),
            'notify_url' => $dto->notify_url,
        ]);

        if ($dto->detail !== null && $dto->detail !== []) {
            $data['detail'] = $this->encodeJson($dto->detail);
        }

        $sceneInfo = $dto->payType() === PayType::H5
            ? $this->resolveH5SceneInfo($dto)
            : $dto->scene_info;

        if ($sceneInfo !== null && $sceneInfo !== []) {
            $data['scene_info'] = $this->encodeJson($sceneInfo);
        }

        match ($dto->payType()) {
            PayType::OFFICIAL,
            PayType::MINI_PROGRAM => $data['openid'] = $dto->openid,
            PayType::NATIVE => $data['product_id'] = $dto->product_id,
            PayType::MICROPAY => $data['auth_code'] = $dto->auth_code,
            PayType::H5,
            PayType::APP => null,
            default => null,
        };

        return $data;
    }

    /** 校验 V2 H5 支付的场景类型和对应场景字段。 */
    private function validateH5(PaymentDto $dto): void
    {
        $h5Info = $this->resolveH5SceneInfo($dto)['h5_info'];
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
                'V2 H5 payment [scene_info.h5_info.type] must be Wap, IOS or Android.'
            ),
        };

        if (
            $type === 'wap'
            && filter_var($h5Info['wap_url'], FILTER_VALIDATE_URL) === false
        ) {
            throw new PaymentException(
                'V2 H5 payment [scene_info.h5_info.wap_url] must be a valid URL.'
            );
        }
    }

    /** 合并 DTO、模块配置和默认值，生成 V2 H5 场景信息。 */
    private function resolveH5SceneInfo(PaymentDto $dto): array
    {
        $sceneInfo = $dto->scene_info ?? [];
        $dtoH5Info = $sceneInfo['h5_info'] ?? [];
        $configuredH5Info = $this->h5Config['h5_info']
            ?? $this->h5Config;

        if (! is_array($dtoH5Info) || ! is_array($configuredH5Info)) {
            throw new PaymentException(
                'V2 PaymentDto [scene_info.h5_info] must be array.'
            );
        }

        $h5Info = array_replace(
            ['type' => 'Wap'],
            $configuredH5Info,
            $dtoH5Info,
        );

        if (strtolower((string) $h5Info['type']) === 'wap') {
            $h5Info['wap_url'] = $this->firstString(
                $h5Info['wap_url'] ?? null,
                $h5Info['app_url'] ?? null,
            );
            $h5Info['wap_name'] = $this->firstString(
                $h5Info['wap_name'] ?? null,
                $h5Info['app_name'] ?? null,
            );
            $defaults = $this->defaultH5Info($dto);

            foreach (array_keys($defaults) as $key) {
                $h5Info[$key] = $this->firstString(
                    $h5Info[$key] ?? null,
                    $defaults[$key],
                );
            }
        }

        $h5Info = $this->filterH5Info($h5Info);

        $sceneInfo['h5_info'] = $h5Info;

        return $sceneInfo;
    }

    /** 根据通知地址生成默认 WAP 场景信息。 */
    private function defaultH5Info(PaymentDto $dto): array
    {
        $wapUrl = $this->siteOrigin(
            $dto->notify_url !== '' ? $dto->notify_url : $this->notifyUrl,
        );

        return [
            'type' => 'Wap',
            'wap_url' => $wapUrl ?? '',
            'wap_name' => $wapUrl !== null
                ? (string) parse_url($wapUrl, PHP_URL_HOST)
                : '',
        ];
    }

    /** 从 URL 中提取协议、域名和端口组成站点根地址。 */
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

    /** 按 H5 类型过滤微信 V2 接口允许的场景字段。 */
    private function filterH5Info(array $h5Info): array
    {
        $fields = match (strtolower((string) ($h5Info['type'] ?? ''))) {
            'wap' => ['type', 'wap_url', 'wap_name'],
            'ios' => ['type', 'app_name', 'bundle_id'],
            'android' => ['type', 'app_name', 'package_name'],
            default => ['type'],
        };

        return array_intersect_key($h5Info, array_flip($fields));
    }

    /** 校验 V2 支付通用文本字段和时间格式。 */
    private function validateCommonFields(PaymentDto $dto): void
    {
        $maxDescriptionLength = $dto->payType() === PayType::MICROPAY
            ? 127
            : 128;

        if (strlen($dto->description) > $maxDescriptionLength) {
            throw new PaymentException(
                "V2 PaymentDto [description] must not exceed {$maxDescriptionLength} bytes."
            );
        }

        if (strlen($dto->attach) > 127) {
            throw new PaymentException(
                'V2 PaymentDto [attach] must not exceed 127 bytes.'
            );
        }

        foreach (['time_start', 'time_expire'] as $field) {
            if (
                $dto->{$field} !== ''
                && preg_match('/^\d{14}$/D', $dto->{$field}) !== 1
            ) {
                throw new PaymentException(
                    "V2 PaymentDto [{$field}] must use yyyyMMddHHmmss format."
                );
            }
        }
    }

    /** 校验 V2 付款码支付的授权码。 */
    private function validateMicropay(PaymentDto $dto): void
    {
        $this->requireFields($dto, ['auth_code']);

        if (preg_match('/^1[0-5]\d{16}$/D', $dto->auth_code) !== 1) {
            throw new PaymentException(
                'V2 PaymentDto [auth_code] must be an 18-digit payment code with prefix 10-15.'
            );
        }
    }

    /** 校验 H5 场景中指定字段不为空。 */
    private function requireH5Fields(array $h5Info, array $fields): void
    {
        foreach ($fields as $field) {
            if (! is_string($h5Info[$field] ?? null) || trim($h5Info[$field]) === '') {
                throw new PaymentException(
                    "V2 H5 payment requires scene_info.h5_info.{$field}."
                );
            }
        }
    }

    /** 校验支付 DTO 中指定字段不为空。 */
    private function requireFields(PaymentDto $dto, array $fields): void
    {
        foreach ($fields as $field) {
            if ($dto->{$field} === '' || $dto->{$field} === null) {
                throw new PaymentException(
                    "V2 PaymentDto [{$field}] is required."
                );
            }
        }
    }

    /** 确认 Builder 接收的是普通商户 PaymentDto。 */
    private function paymentDto(AbstractPaymentDto $dto): PaymentDto
    {
        if (! $dto instanceof PaymentDto) {
            throw new PaymentException(
                'V2 PaymentBuilder only supports PaymentDto.'
            );
        }

        return $dto;
    }

    /** 将 V2 复杂字段编码为微信接口需要的 JSON 字符串。 */
    private function encodeJson(array $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR,
        );
    }
}
