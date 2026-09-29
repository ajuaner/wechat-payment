<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Config;

use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\InvalidConfigException;

/**
 * 普通商户支付配置，version 只表示微信支付 API 协议版本。
 */
final class PaymentConfig extends AbstractWechatPayConfig
{
    // v2 key 密钥
    public ?string $v2_secret_key;
    //ip
    public ?string $spbill_create_ip;
    /**
     * H5 场景信息默认值，DTO 中的 scene_info.h5_info 可以覆盖。
     *
     * @var array<string, mixed>
     */
    public array $h5;

    /**
     * 创建普通商户支付配置并校验对应版本的凭证。
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $config['version'] ??= ApiVersion::V3;

        parent::__construct($config);

        $this->version = (string) $config['version'];
        $this->v2_secret_key = $this->nullableString(
            $config['v2_secret_key'] ?? null,
        );
        $this->spbill_create_ip = $this->nullableString(
            $config['spbill_create_ip'] ?? null,
        );
        $this->h5 = $config['h5'] ?? [];

        if (! is_array($this->h5)) {
            throw new InvalidConfigException(
                'Wechat payment config [h5] must be array.'
            );
        }

        if (
            $this->spbill_create_ip !== ''
            && filter_var($this->spbill_create_ip, FILTER_VALIDATE_IP) === false
        ) {
            throw new InvalidConfigException(
                'Wechat payment config [spbill_create_ip] must be a valid IPv4 or IPv6 address.'
            );
        }

        $this->validate();
    }

    /** 校验普通商户支付版本和所需凭证。 */
    private function validate(): void
    {
        if (! ApiVersion::supports($this->version)) {
            throw new InvalidConfigException(
                'Wechat payment config [version] must be v2 or v3.'
            );
        }

        if ($this->version === ApiVersion::V2) {
            $this->validateCommon();
            $this->requireProperties([
                'v2_secret_key',
                'spbill_create_ip',
            ]);
            return;
        }

        $this->validateV3();
    }

}
