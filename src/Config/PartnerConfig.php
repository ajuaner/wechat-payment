<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Config;

use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\InvalidConfigException;

/** 服务商支付配置，version 只表示微信支付 API 协议版本。 */
final class PartnerConfig extends AbstractWechatPayConfig
{
    public ?string $v2_secret_key;
    public ?string $spbill_create_ip;
    public array $h5;

    /**
     * 创建服务商支付配置并校验对应版本的凭证。
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $config['version'] ??= ApiVersion::V3;

        parent::__construct($config);

        $this->version = (string) $config['version'];
        $this->v2_secret_key = $this->nullableString($config['v2_secret_key'] ?? null);
        $this->spbill_create_ip = $this->nullableString($config['spbill_create_ip'] ?? null);
        $this->h5 = $config['h5'] ?? [];

        if (! is_array($this->h5)) {
            throw new InvalidConfigException('Wechat partner config [h5] must be array.');
        }

        if (
            $this->spbill_create_ip !== ''
            && filter_var($this->spbill_create_ip, FILTER_VALIDATE_IP) === false
        ) {
            throw new InvalidConfigException(
                'Wechat partner config [spbill_create_ip] must be a valid IPv4 or IPv6 address.'
            );
        }

        if ($this->version === ApiVersion::V2) {
            $this->validateCommon();
            $this->requireProperties(['v2_secret_key', 'spbill_create_ip']);
            return;
        }

        if ($this->version !== ApiVersion::V3) {
            throw new InvalidConfigException('Wechat partner config [version] must be v2 or v3.');
        }

        $this->validateV3();
    }

}
