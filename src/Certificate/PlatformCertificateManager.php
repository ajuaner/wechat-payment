<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Certificate;

use EasyWeChat\Kernel\Support\AesGcm;
use EasyWeChat\Pay\Application;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Exception\InvalidConfigException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Qinii\WechatPayment\Endpoints\PaymentEndpoints;

/**
 * 为 API v3 配置准备微信支付平台证书或微信支付公钥。
 */
final class PlatformCertificateManager
{
    /**
     * 平台证书每天刷新一次，避免证书轮换后长时间使用旧缓存。
     */
    private const CACHE_TTL = 86400;

    private string $cacheDir;
    private string $projectRoot;

    /** 最近一次平台证书刷新失败原因，使用有效旧证书降级时保留。 */
    private ?\Throwable $lastRefreshError = null;

    /** 初始化平台证书缓存目录和可选 HTTP 客户端。 */
    public function __construct(
        ?string $cacheDir = null,
        private ?HttpClientInterface $httpClient = null,
    ) {
        $this->projectRoot = $this->resolveProjectRoot();
        $this->cacheDir = $this->resolveCacheDirectory(
            $cacheDir ?? 'storage/wechat-payment/platform-certs',
        );
    }

    /** 设置下载平台证书时使用的 HTTP 客户端。 */
    public function setHttpClient(HttpClientInterface $httpClient): self
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /** 获取最近一次平台证书刷新失败原因。 */
    public function lastRefreshError(): ?\Throwable
    {
        return $this->lastRefreshError;
    }

    /** 为 API v3 配置准备平台证书或微信支付公钥。 */
    public function resolve(
        AbstractWechatPayConfig $config,
    ): AbstractWechatPayConfig {
        $this->lastRefreshError = null;

        if ($config->version === ApiVersion::V2) {
            return $config;
        }

        if (strlen((string) $config->secret_key) !== 32) {
            throw new InvalidConfigException(
                'Wechat payment config [secret_key] must be 32 bytes.'
            );
        }

        if ($config->platform_certs !== []) {
            $config->platform_certs = $this->normalizeConfiguredCertificates(
                $config->platform_certs,
            );

            return $config;
        }

        $hasPublicKeyId = $config->public_key_id !== '';
        $hasPublicKey = $config->public_key !== '';

        if ($hasPublicKeyId !== $hasPublicKey) {
            throw new InvalidConfigException(
                'Wechat payment config [public_key_id] and [public_key] must be configured together.'
            );
        }

        if ($hasPublicKeyId) {
            $config->platform_certs = [
                $config->public_key_id => $config->public_key,
            ];

            return $config;
        }

        $merchantCacheDir = $this->merchantCacheDir(
            $config->mch_id,
            $config->platform_certs_cache_dir,
        );
        $cacheFile = $merchantCacheDir . '/platform-certificates.json';

        $usableCachedCertificates = [];
        if (is_file($cacheFile)) {
            $cached = json_decode(
                (string) file_get_contents($cacheFile),
                true,
            );

            $cachedCertificates = is_array($cached)
                ? $cached['certificates'] ?? null
                : null;

            if (is_array($cachedCertificates)) {
                $usableCachedCertificates = $this->usableCachedCertificates(
                    $cachedCertificates,
                );
            }

            if (
                is_array($cached)
                && ($cached['expires_at'] ?? 0) > time()
                && $usableCachedCertificates !== []
            ) {
                $config->platform_certs = $usableCachedCertificates;

                return $config;
            }
        }

        try {
            $certificates = $this->download($config, $merchantCacheDir);
        } catch (\Throwable $exception) {
            if ($usableCachedCertificates === []) {
                throw $exception;
            }

            $this->lastRefreshError = $exception;
            $config->platform_certs = $usableCachedCertificates;

            return $config;
        }

        $cache = json_encode([
            'expires_at' => time() + self::CACHE_TTL,
            'certificates' => $certificates,
        ], JSON_UNESCAPED_SLASHES);

        if (! is_string($cache) || @file_put_contents($cacheFile, $cache, LOCK_EX) === false) {
            throw new InvalidConfigException(
                "Wechat payment platform certificate cache [{$cacheFile}] cannot be written."
            );
        }

        $config->platform_certs = $certificates;

        return $config;
    }

    /**
     * 调用微信支付证书接口并解密、校验、保存平台证书。
     *
     * @return array<string, string>
     */
    private function download(
        AbstractWechatPayConfig $config,
        string $merchantCacheDir,
    ): array {
        $applicationConfig = $config->toArray();
        $applicationConfig['platform_certs'] = [];

        $application = new Application($applicationConfig);

        if ($this->httpClient !== null) {
            $application->setHttpClient($this->httpClient);
        }

        $response = $application->getClient()
            ->get(PaymentEndpoints::BASE_PAY_URL . PaymentEndpoints::CERTIFICATES_ENDPOINT)
            ->toArray();

        $certificates = [];

        foreach ($response['data'] ?? [] as $item) {
            if (
                ! is_array($item)
                || ! is_string($item['serial_no'] ?? null)
                || ! is_array($item['encrypt_certificate'] ?? null)
            ) {
                continue;
            }

            $encrypted = $item['encrypt_certificate'];
            $ciphertext = $encrypted['ciphertext'] ?? null;
            $nonce = $encrypted['nonce'] ?? null;
            $associatedData = $encrypted['associated_data'] ?? null;

            if (
                ! is_string($ciphertext)
                || ! is_string($nonce)
                || ! is_string($associatedData)
            ) {
                continue;
            }

            $certificate = AesGcm::decrypt(
                $ciphertext,
                (string) $config->secret_key,
                $nonce,
                $associatedData,
            );

            $certificateResource = @openssl_x509_read($certificate);
            $certificateInfo = $certificateResource === false
                ? false
                : openssl_x509_parse($certificateResource);
            $certificateSerial = is_array($certificateInfo)
                ? strtoupper((string) ($certificateInfo['serialNumberHex'] ?? ''))
                : '';
            $serial = strtoupper($item['serial_no']);

            if (
                $certificateResource === false
                || $certificateSerial === ''
                || $certificateSerial !== $serial
            ) {
                throw new InvalidConfigException(
                    "Invalid Wechat payment platform certificate [{$item['serial_no']}]."
                );
            }

            $certificates[$serial] = $this->storeCertificate(
                $merchantCacheDir,
                $serial,
                $certificate,
            );
        }

        if ($certificates === []) {
            throw new InvalidConfigException(
                'Wechat payment platform certificate download failed.'
            );
        }

        return $certificates;
    }

    /** 根据商户号确定平台证书缓存目录，并确保目录可写。 */
    private function merchantCacheDir(
        string $merchantId,
        string $configuredDirectory,
    ): string
    {
        $baseDirectory = trim($configuredDirectory) !== ''
            ? $this->resolveCacheDirectory(trim($configuredDirectory))
            : $this->cacheDir;
        $directory = rtrim($baseDirectory, '/\\') . '/'
            . hash('sha256', $merchantId);

        if (
            (! is_dir($directory) && ! @mkdir($directory, 0755, true))
            || ! is_writable($directory)
        ) {
            throw new InvalidConfigException(
                "Wechat payment platform certificate directory [{$directory}] is not writable."
            );
        }

        return $directory;
    }

    /** 标准化用户配置的平台证书路径。 */
    private function normalizeConfiguredCertificates(array $certificates): array
    {
        $normalized = [];

        foreach ($certificates as $serial => $path) {
            if (
                (! is_int($serial) && ! is_string($serial))
                || ! is_string($path)
            ) {
                throw new InvalidConfigException(
                    'Wechat payment config [platform_certs] must map serial numbers to file paths.'
                );
            }

            $serial = strtoupper(trim((string) $serial));
            if ($serial === '') {
                throw new InvalidConfigException(
                    'Wechat payment config [platform_certs] contains an empty certificate identifier.'
                );
            }

            $path = trim($path);
            if (! is_file($path) || ! is_readable($path)) {
                throw new InvalidConfigException(
                    "Wechat payment config [platform_certs => {$serial}] is not a readable file."
                );
            }

            $realPath = realpath($path);
            if ($realPath === false) {
                throw new InvalidConfigException(
                    "Wechat payment config [platform_certs => {$serial}] cannot be resolved."
                );
            }

            $contents = @file_get_contents($realPath);
            if (! is_string($contents)) {
                throw new InvalidConfigException(
                    "Wechat payment config [platform_certs => {$serial}] cannot be read."
                );
            }

            if (str_starts_with($serial, 'PUB_KEY_ID_')) {
                if (@openssl_pkey_get_public($contents) === false) {
                    throw new InvalidConfigException(
                        "Wechat payment public key [{$serial}] is invalid."
                    );
                }
            } else {
                $certificate = @openssl_x509_read($contents);
                $info = $certificate === false
                    ? false
                    : openssl_x509_parse($certificate);
                $actualSerial = is_array($info)
                    ? strtoupper((string) ($info['serialNumberHex'] ?? ''))
                    : '';

                if ($actualSerial === '' || $actualSerial !== strtoupper($serial)) {
                    throw new InvalidConfigException(
                        "Wechat payment platform certificate [{$serial}] is invalid or has a mismatched serial number."
                    );
                }
            }

            $normalized[$serial] = $realPath;
        }

        return $normalized;
    }

    /** 获取 Composer 根项目目录，源码运行时回退到扩展包根目录。 */
    private function resolveProjectRoot(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $rootPackage = \Composer\InstalledVersions::getRootPackage();
                $installPath = $rootPackage['install_path'] ?? null;

                if (is_string($installPath) && is_dir($installPath)) {
                    $realPath = realpath($installPath);
                    if ($realPath !== false) {
                        return $realPath;
                    }
                }
            } catch (\Throwable) {
                // Composer 运行时信息不可用时使用扩展包根目录。
            }
        }

        return dirname(__DIR__, 2);
    }

    /** 将相对缓存目录稳定地解析到 Composer 根项目目录。 */
    private function resolveCacheDirectory(string $directory): string
    {
        $directory = trim($directory);
        if ($directory === '') {
            $directory = 'storage/wechat-payment/platform-certs';
        }

        if (
            str_starts_with($directory, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $directory) === 1
        ) {
            return rtrim($directory, '/\\');
        }

        return rtrim($this->projectRoot, '/\\') . '/'
            . trim($directory, '/\\');
    }

    /** 返回文件、序列号和 X.509 有效期均可用的缓存平台证书。 */
    private function usableCachedCertificates(array $certificates): array
    {
        $usable = [];
        $now = time();

        foreach ($certificates as $serial => $path) {
            if (
                (! is_string($serial) && ! is_int($serial))
                || ! is_string($path)
                || ! is_file($path)
                || ! is_readable($path)
            ) {
                continue;
            }

            $certificate = @openssl_x509_read((string) @file_get_contents($path));
            $info = $certificate === false
                ? false
                : openssl_x509_parse($certificate);
            $actualSerial = is_array($info)
                ? strtoupper((string) ($info['serialNumberHex'] ?? ''))
                : '';
            $validFrom = is_array($info)
                ? (int) ($info['validFrom_time_t'] ?? 0)
                : 0;
            $validTo = is_array($info)
                ? (int) ($info['validTo_time_t'] ?? 0)
                : 0;

            if (
                $actualSerial === ''
                || $actualSerial !== strtoupper((string) $serial)
                || $validFrom > $now
                || $validTo <= $now
            ) {
                continue;
            }

            $usable[(string) $serial] = $path;
        }

        return $usable;
    }

    /** 将解密后的平台证书原子写入商户缓存目录。 */
    private function storeCertificate(
        string $directory,
        string $serial,
        string $certificate,
    ): string {
        if (! preg_match('/^[A-F0-9]+$/', $serial)) {
            throw new InvalidConfigException(
                "Invalid Wechat payment platform certificate serial [{$serial}]."
            );
        }

        $path = $directory . '/wechatpay-' . $serial . '.pem';
        $temporaryPath = @tempnam($directory, '.wechatpay-');

        if (
            $temporaryPath === false
            || @file_put_contents($temporaryPath, $certificate, LOCK_EX) !== strlen($certificate)
            || ! @rename($temporaryPath, $path)
        ) {
            if (is_string($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw new InvalidConfigException(
                "Wechat payment platform certificate [{$path}] cannot be written."
            );
        }

        @chmod($path, 0644);

        $realPath = realpath($path);

        if ($realPath === false) {
            throw new InvalidConfigException(
                "Wechat payment platform certificate [{$path}] cannot be resolved."
            );
        }

        return $realPath;
    }
}
