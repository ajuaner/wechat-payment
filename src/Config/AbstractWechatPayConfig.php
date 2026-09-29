<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Config;

use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\InvalidConfigException;

abstract class AbstractWechatPayConfig
{
    // appID
    public string $app_id;
    // 商户ID
    public string $mch_id;
    // 通知URL
    public string $notify_url;
    // 版本 v2 v3
    public string $version;
    // 支付私钥
    public ?string $private_key;
    // 支付证书
    public ?string $certificate;
    // v3 key 密钥
    public ?string $secret_key;
    // v2 key 密钥
    public ?string $v2_secret_key;

    // 商户证书序列号
    public string $serial_no;
    // 公钥 ID
    public string $public_key_id;
    // 公钥 证书
    public string $public_key;
    // APIv3 平台证书缓存目录，支持相对项目目录或绝对路径
    public string $platform_certs_cache_dir;
    // HTTP 配置
    public array $http;
    /**
     *  平台证书：微信支付 APIv3 平台证书。
     *  未配置平台证书或微信支付公钥时，由证书管理器自动下载并缓存。
     *  如果是「平台证书」模式
     *  使用 Key/Value 结构， key 为 平台证书的序列号，value 为微信支付平台证书的绝对路径
     *  "{SerialNo}" => '/path/to/wechatpay/cert.pem'
     *  如果是「微信支付公钥」模式
     *  使用 Key/Value 结构， key 为微信支付公钥 ID(PUB_KEY_ID 开头)，value 为微信支付公钥文件绝对路径
     *  "{$pubKeyId}" => '/path/to/wechatpay/pubkey.pem',
     */
    public array $platform_certs;

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * 使用原始数组初始化通用微信支付配置字段。
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $http = $config['http'] ?? [];
        $platformCerts = $config['platform_certs'] ?? [];

        if (! is_array($http)) {
            throw new InvalidConfigException(
                'Wechat payment config [http] must be array.'
            );
        }

        if (! is_array($platformCerts)) {
            throw new InvalidConfigException(
                'Wechat payment config [platform_certs] must be array.'
            );
        }

        $http = array_replace([
            'http_version' => '1.1',
            'timeout' => 5,
        ], $http);

        $this->app_id = (string) ($config['app_id'] ?? '');
        $this->mch_id = (string) ($config['mch_id'] ?? '');
        $this->notify_url = (string) ($config['notify_url'] ?? '');

        $this->private_key = $this->nullableString(
            $config['private_key'] ?? '',
        );
        $this->certificate = $this->nullableString(
            $config['certificate'] ?? '',
        );
        $this->secret_key = $this->nullableString(
            $config['secret_key'] ?? '',
        );
        $this->v2_secret_key = $this->nullableString(
            $config['v2_secret_key'] ?? '',
        );
        $this->serial_no = strtoupper(trim((string) ($config['serial_no'] ?? '')));
        $this->public_key_id = (string) ($config['public_key_id'] ?? '');
        $this->public_key = $this->nullableString(
            $config['public_key'] ?? '',
        );
        $this->platform_certs_cache_dir = $this->nullableString(
            $config['platform_certs_cache_dir'] ?? '',
        );

        $this->http = $http;
        $this->platform_certs = $platformCerts;
    }

    /** 校验普通配置必填字段和本地证书文件。 */
    protected function validateCommon(): void
    {
        $this->requireProperties([
            'app_id',
            'mch_id',
        ]);
        $this->validateCertificateFiles();
    }

    /** 校验证书、公钥路径均为 PHP 可读文件，并转换为绝对路径。 */
    public function validateCertificateFiles(): void
    {
        foreach ([
            'private_key' => $this->private_key,
            'certificate' => $this->certificate,
            'public_key' => $this->public_key,
        ] as $property => $path) {
            if ($path === '') {
                continue;
            }

            $this->{$property} = $this->normalizeReadableFile(
                $property,
                $path,
            );
        }

        $this->validatePrivateKey();
        $this->validateMerchantCertificate();
        $this->validatePublicKey();
        $this->validateMerchantKeyPair();
        $this->validateMerchantSerialNumber();
    }

    /** 将证书配置标准化为可读的真实文件路径。 */
    private function normalizeReadableFile(
        string $property,
        string $path,
    ): string {
        $path = trim($path);

        if (! is_file($path)) {
            throw new InvalidConfigException(
                "Wechat payment config [{$property} => {$path}] is not a file."
            );
        }

        if (! is_readable($path)) {
            throw new InvalidConfigException(
                "Wechat payment config [{$property} => {$path}] is not readable by PHP."
            );
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            throw new InvalidConfigException(
                "Wechat payment config [{$property} => {$path}] cannot be resolved."
            );
        }

        return $realPath;
    }


    /** 校验 API v3 所需的密钥、私钥和商户证书。 */
    protected function validateV3(): void
    {
        $this->validateCommon();
        $this->requireProperties([
            'secret_key',
            'private_key',
            'certificate',
            'serial_no',
        ]);
    }

    /** 校验商户私钥文件确实包含 OpenSSL 可识别的私钥。 */
    private function validatePrivateKey(): void
    {
        if ($this->private_key === '') {
            return;
        }

        $contents = @file_get_contents($this->private_key);
        if (! is_string($contents) || @openssl_pkey_get_private($contents) === false) {
            throw new InvalidConfigException(
                'Wechat payment config [private_key] is not a valid readable private key.'
            );
        }
    }

    /** 校验商户证书文件确实包含 X.509 证书。 */
    private function validateMerchantCertificate(): void
    {
        if ($this->certificate === '') {
            return;
        }

        $contents = @file_get_contents($this->certificate);
        if (! is_string($contents) || @openssl_x509_read($contents) === false) {
            throw new InvalidConfigException(
                'Wechat payment config [certificate] is not a valid X.509 certificate.'
            );
        }
    }

    /** 校验配置的微信支付公钥可以用于 RSA 加密和验签。 */
    private function validatePublicKey(): void
    {
        if ($this->public_key === '') {
            return;
        }

        $contents = @file_get_contents($this->public_key);
        if (! is_string($contents) || @openssl_pkey_get_public($contents) === false) {
            throw new InvalidConfigException(
                'Wechat payment config [public_key] is not a valid readable public key.'
            );
        }
    }

    /** 校验商户私钥和商户证书属于同一密钥对。 */
    private function validateMerchantKeyPair(): void
    {
        if ($this->private_key === '' || $this->certificate === '') {
            return;
        }

        $privateKey = @openssl_pkey_get_private(
            (string) @file_get_contents($this->private_key),
        );
        $certificate = @openssl_x509_read(
            (string) @file_get_contents($this->certificate),
        );

        if (
            $privateKey === false
            || $certificate === false
            || ! @openssl_x509_check_private_key($certificate, $privateKey)
        ) {
            throw new InvalidConfigException(
                'Wechat payment config [private_key] does not match [certificate].'
            );
        }
    }

    /** 校验配置的商户证书序列号与证书内容一致。 */
    private function validateMerchantSerialNumber(): void
    {
        if ($this->certificate === '' || $this->serial_no === '') {
            return;
        }

        $certificate = @openssl_x509_read(
            (string) @file_get_contents($this->certificate),
        );
        $info = $certificate === false
            ? false
            : openssl_x509_parse($certificate);
        $serial = is_array($info)
            ? strtoupper((string) ($info['serialNumberHex'] ?? ''))
            : '';

        if ($serial === '' || $serial !== $this->serial_no) {
            throw new InvalidConfigException(
                'Wechat payment config [serial_no] does not match [certificate].'
            );
        }
    }

    /** 校验配置对象中指定的属性均已填写。 */
    protected function requireProperties(array $properties): void
    {
        foreach ($properties as $property) {
            if (
                ! property_exists($this, $property)
                || $this->{$property} === null
                || $this->{$property} === ''
            ) {
                throw new InvalidConfigException(
                    "Wechat payment config [{$property}] is required."
                );
            }
        }
    }

    /** 将可选配置值统一转换为字符串或空字符串。 */
    protected function nullableString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) $value;
    }

    /**
     * 返回已经标准化并通过本地校验的 EasyWeChat 配置。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $config = [
            'app_id' => $this->app_id,
            'mch_id' => $this->mch_id,
            'notify_url' => $this->notify_url,
            'version' => $this->version,
            'private_key' => $this->private_key,
            'certificate' => $this->certificate,
            'http' => $this->http,
        ];

        if ($this->version === ApiVersion::V2) {
            $config['v2_secret_key'] = $this->v2_secret_key;
        } else {
            $config['secret_key'] = $this->secret_key;
            $config['serial_no'] = $this->serial_no;
            $config['public_key_id'] = $this->public_key_id;
            $config['public_key'] = $this->public_key;
            $config['platform_certs'] = $this->platform_certs;
        }

        return $config;
    }

}
