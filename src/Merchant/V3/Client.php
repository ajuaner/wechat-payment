<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Merchant\V3;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use EasyWeChat\Pay\Utils;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Endpoints\MerchantEndpoints;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Merchant\EcommerceApplymentDto;
use Qinii\WechatPayment\Merchant\SettlementDto;
use Qinii\WechatPayment\Merchant\SpecialApplymentDto;

/** 微信支付 API v3 子商户管理客户端。 */
final class Client
{
    /** 文档明确要求使用微信支付公钥加密的字段名。 */
    private const SENSITIVE_FIELDS = [
        'contact_name' => true,
        'contact_id_number' => true,
        'contact_id_card_number' => true,
        'contact_email' => true,
        'mobile_phone' => true,
        'id_card_name' => true,
        'id_card_number' => true,
        'id_card_address' => true,
        'id_doc_name' => true,
        'id_doc_number' => true,
        'id_doc_address' => true,
        'ubo_id_doc_name' => true,
        'ubo_id_doc_number' => true,
        'ubo_id_doc_address' => true,
        'account_name' => true,
        'account_number' => true,
    ];

    /** 普通文件支持的扩展名。 */
    private const FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'bmp', 'pdf'];

    /** 视频文件支持的扩展名。 */
    private const VIDEO_EXTENSIONS = [
        'avi', 'wmv', 'mpeg', 'mp4', 'mov', 'mkv', 'flv', 'f4v', 'm4v', 'rmvb',
    ];

    /** 创建子商户管理客户端。 */
    public function __construct(
        private PartnerConfig $config,
        private ApplicationContract $application,
        private Builder $builder,
    ) {
    }

    /** 提交普通服务商特约商户入驻申请。 */
    public function applySpecial(SpecialApplymentDto $dto): array
    {
        return $this->postSensitiveJson(
            MerchantEndpoints::V3_APPLYMENT4SUB_APPLYMENT,
            $this->builder->buildSpecialApplyment($dto),
        );
    }

    /** 提交电商收付通二级商户入驻申请。 */
    public function applyEcommerce(EcommerceApplymentDto $dto): array
    {
        return $this->postSensitiveJson(
            MerchantEndpoints::V3_ECOMMERCE_APPLYMENT_APPLYMENT,
            $this->builder->buildEcommerceApplyment($dto),
        );
    }

    /** 按微信支付申请单号查询特约商户入驻状态。 */
    public function querySpecialByApplymentId(int|string $applymentId): array
    {
        return $this->getJson($this->uri(
            MerchantEndpoints::V3_APPLYMENT4SUB_APPLYMENT_ID,
            ['applyment_id' => $this->applymentId($applymentId)],
        ));
    }

    /** 按业务申请编号查询特约商户入驻状态。 */
    public function querySpecialByBusinessCode(string $businessCode): array
    {
        return $this->getJson($this->uri(
            MerchantEndpoints::V3_APPLYMENT4SUB_BUSINESS_CODE,
            ['business_code' => $this->requiredPathValue($businessCode, 'business_code')],
        ));
    }

    /** 按微信支付申请单号查询收付通二级商户入驻状态。 */
    public function queryEcommerceByApplymentId(int|string $applymentId): array
    {
        return $this->getJson($this->uri(
            MerchantEndpoints::V3_ECOMMERCE_APPLYMENT_ID,
            ['applyment_id' => $this->applymentId($applymentId)],
        ));
    }

    /** 按业务申请编号查询收付通二级商户入驻状态。 */
    public function queryEcommerceByOutRequestNo(string $outRequestNo): array
    {
        return $this->getJson($this->uri(
            MerchantEndpoints::V3_ECOMMERCE_APPLYMENT_OUT_REQUEST_NO,
            ['out_request_no' => $this->requiredPathValue($outRequestNo, 'out_request_no')],
        ));
    }

    /** 修改特约商户或二级商户结算账户。 */
    public function modifySettlement(SettlementDto $dto): array
    {
        return $this->postSensitiveJson(
            $this->uri(
                MerchantEndpoints::V3_APPLYMENT4SUB_MODIFY_SETTLEMENT,
                ['sub_mchid' => $dto->sub_mchid],
            ),
            $this->builder->buildSettlement($dto),
        );
    }

    /** 查询特约商户或二级商户当前结算账户。 */
    public function querySettlement(
        string $subMchid,
        string $accountNumberRule = 'ACCOUNT_NUMBER_RULE_MASK_V1',
    ): array {
        return $this->getJson(
            $this->uri(
                MerchantEndpoints::V3_APPLYMENT4SUB_SETTLEMENT,
                ['sub_mchid' => $this->requiredPathValue($subMchid, 'sub_mchid')],
            ),
            ['account_number_rule' => $this->accountNumberRule($accountNumberRule)],
        );
    }

    /** 查询结算账户修改申请状态。 */
    public function querySettlementApplication(
        string $subMchid,
        string $applicationNo,
        string $accountNumberRule = 'ACCOUNT_NUMBER_RULE_MASK_V1',
    ): array {
        return $this->getJson(
            $this->uri(
                MerchantEndpoints::V3_APPLYMENT4SUB_APPLICATION,
                [
                    'sub_mchid' => $this->requiredPathValue($subMchid, 'sub_mchid'),
                    'application_no' => $this->requiredPathValue($applicationNo, 'application_no'),
                ],
            ),
            ['account_number_rule' => $this->accountNumberRule($accountNumberRule)],
        );
    }

    /** 查询支持个人或企业业务的银行列表。 */
    public function banks(
        int|string $bankType,
        int $offset = 0,
        int $limit = 100,
    ): array {
        $type = $this->bankType($bankType);

        return $this->getJson(
            $this->uri(
                MerchantEndpoints::V3_CAPITALLHH_BANKS,
                ['bank_type' => $type],
            ),
            $this->pagination($offset, $limit),
        );
    }

    /** 查询指定银行和城市下的支行列表。 */
    public function branches(
        string $bankAliasCode,
        string $cityCode,
        int $offset = 0,
        int $limit = 100,
    ): array {
        return $this->getJson(
            $this->uri(
                MerchantEndpoints::V3_CAPITALLHH_BRANCHES,
                [
                    'bank_alias_code' => $this->requiredPathValue(
                        $bankAliasCode,
                        'bank_alias_code',
                    ),
                ],
            ),
            array_merge(
                ['city_code' => $this->requiredQueryValue($cityCode, 'city_code')],
                $this->pagination($offset, $limit),
            ),
        );
    }

    /** 查询省份列表，或查询指定省份下的城市列表。 */
    public function areas(?string $provinceCode = null): array
    {
        $provinceCode = $provinceCode === null ? '' : trim($provinceCode);
        $endpoint = $provinceCode === ''
            ? MerchantEndpoints::V3_CAPITALLHH_AREAS_PROVINCES
            : MerchantEndpoints::V3_CAPITALLHH_AREAS_PROVINCES
                . '/{province_code}/cities';

        return $this->getJson(
            $this->uri($endpoint, ['province_code' => $provinceCode]),
        );
    }

    /** 上传进件图片或 PDF 文件并返回 MediaID。 */
    public function upload(string $filePath, ?string $filename = null): array
    {
        return $this->uploadMedia(
            MerchantEndpoints::V3_MERCHANT_MEDIA_UPLOAD,
            $filePath,
            $filename,
            self::FILE_EXTENSIONS,
        );
    }

    /** 上传普通服务商特约商户进件视频并返回 MediaID。 */
    public function uploadVideo(string $filePath, ?string $filename = null): array
    {
        return $this->uploadMedia(
            MerchantEndpoints::V3_MERCHANT_MEDIA_VIDEO_UPLOAD,
            $filePath,
            $filename,
            self::VIDEO_EXTENSIONS,
        );
    }

    /** 加密敏感字段后发送 JSON POST 请求。 */
    private function postSensitiveJson(string $endpoint, array $payload): array
    {
        $serial = $this->encryptionSerial();
        $payload = $this->encryptSensitiveFields($payload, $serial);
        $response = $this->application->getClient()->post(
            MerchantEndpoints::BASE_PAY_URL . $endpoint,
            [
                'headers' => ['Wechatpay-Serial' => $serial],
                'json' => $payload,
            ],
        );

        return $this->decode($response);
    }

    /** 发送子商户管理 GET 请求。 */
    private function getJson(string $endpoint, array $query = []): array
    {
        $options = $query === [] ? [] : ['query' => $query];
        $response = $this->application->getClient()->get(
            MerchantEndpoints::BASE_PAY_URL . $endpoint,
            $options,
        );

        return $this->decode($response);
    }

    /** 使用 EasyWeChat 的媒体上传签名实现上传文件。 */
    private function uploadMedia(
        string $endpoint,
        string $filePath,
        ?string $filename,
        array $extensions,
    ): array {
        $realPath = $this->mediaPath($filePath);
        $filename = $filename === null || trim($filename) === ''
            ? basename($realPath)
            : basename(trim($filename));
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, $extensions, true)) {
            throw new PaymentException(
                'Wechat merchant media file extension is not supported.'
            );
        }

        if (strlen($filename) > 128) {
            throw new PaymentException(
                'Wechat merchant media filename must not exceed 128 bytes.'
            );
        }

        $maxSize = in_array($extension, ['jpg', 'jpeg', 'png', 'bmp'], true)
            ? 5242880
            : (in_array($extension, ['pdf'], true) ? 7864320 : 5242880);
        $size = filesize($realPath);
        if ($size === false || $size <= 0 || $size > $maxSize) {
            throw new PaymentException(
                "Wechat merchant media file size must be between 1 and {$maxSize} bytes."
            );
        }

        $sha256 = hash_file('sha256', $realPath);
        if (! is_string($sha256)) {
            throw new PaymentException(
                'Wechat merchant media SHA-256 calculation failed.'
            );
        }

        $response = $this->application->getClient()->uploadMedia(
            MerchantEndpoints::BASE_PAY_URL . $endpoint,
            $realPath,
            ['filename' => $filename, 'sha256' => $sha256],
            $filename,
        );

        return $this->decode($response);
    }

    /** 递归加密文档列出的敏感字段，支持对象和数组列表。 */
    private function encryptSensitiveFields(array $payload, string $serial): array
    {
        $utils = new Utils($this->application->getMerchant());

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->encryptSensitiveFields($value, $serial);
                continue;
            }

            if (
                is_string($key)
                && isset(self::SENSITIVE_FIELDS[$key])
                && is_string($value)
                && $value !== ''
            ) {
                $payload[$key] = $utils->encryptWithRsaPublicKey($value, $serial);
            }
        }

        return $payload;
    }

    /** 获取加密敏感字段使用的微信支付公钥 ID 或平台证书序列号。 */
    private function encryptionSerial(): string
    {
        if ($this->config->public_key_id !== '') {
            return $this->config->public_key_id;
        }

        $serial = array_key_first($this->config->platform_certs);
        if ((! is_string($serial) && ! is_int($serial)) || (string) $serial === '') {
            throw new PaymentException(
                'Wechat merchant applyment requires a public key ID or platform certificate.'
            );
        }

        return (string) $serial;
    }

    /** 校验媒体文件路径并返回绝对路径。 */
    private function mediaPath(string $filePath): string
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new PaymentException(
                'Wechat merchant media file must be a readable local file.'
            );
        }

        $realPath = realpath($filePath);
        if ($realPath === false) {
            throw new PaymentException(
                'Wechat merchant media file path cannot be resolved.'
            );
        }

        return $realPath;
    }

    /** 校验并标准化微信支付申请单号。 */
    private function applymentId(int|string $applymentId): string
    {
        $applymentId = trim((string) $applymentId);
        if ($applymentId === '' || ctype_digit($applymentId) === false) {
            throw new PaymentException(
                'Wechat merchant [applyment_id] must be an integer.'
            );
        }

        return $applymentId;
    }

    /** 校验必填路径参数。 */
    private function requiredPathValue(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new PaymentException(
                "Wechat merchant [{$field}] is required."
            );
        }

        return $value;
    }

    /** 校验结算账户查询的银行账号展示规则。 */
    private function accountNumberRule(string $rule): string
    {
        if (! in_array($rule, [
            'ACCOUNT_NUMBER_RULE_MASK_V1',
            'ACCOUNT_NUMBER_RULE_MASK_V2',
        ], true)) {
            throw new PaymentException(
                'Wechat merchant [account_number_rule] is not supported.'
            );
        }

        return $rule;
    }

    /** 标准化银行业务类型，兼容旧项目使用的 0/1 参数。 */
    private function bankType(int|string $bankType): string
    {
        if (is_int($bankType) || ctype_digit(trim((string) $bankType))) {
            return match ((int) $bankType) {
                0 => 'personal-banking',
                1 => 'corporate-banking',
                default => throw new PaymentException(
                    'Wechat merchant [bank_type] must be 0 or 1.'
                ),
            };
        }

        $bankType = trim($bankType);
        if (in_array($bankType, ['personal-banking', 'corporate-banking'], true)) {
            return $bankType;
        }

        throw new PaymentException(
            'Wechat merchant [bank_type] must be personal-banking or corporate-banking.'
        );
    }

    /** 组装并校验分页参数。 */
    private function pagination(int $offset, int $limit): array
    {
        if ($offset < 0) {
            throw new PaymentException('Wechat merchant [offset] must not be negative.');
        }

        if ($limit < 1 || $limit > 100) {
            throw new PaymentException('Wechat merchant [limit] must be between 1 and 100.');
        }

        return ['offset' => $offset, 'limit' => $limit];
    }

    /** 校验必填查询参数。 */
    private function requiredQueryValue(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new PaymentException("Wechat merchant [{$field}] is required.");
        }

        return $value;
    }

    /** 替换并 URL 编码接口路径占位符。 */
    private function uri(string $template, array $params): string
    {
        foreach ($params as $key => $value) {
            $template = str_replace(
                '{' . $key . '}',
                rawurlencode((string) $value),
                $template,
            );
        }

        return $template;
    }

    /** 解析微信支付响应并转换错误信息。 */
    private function decode(object $response): array
    {
        $statusCode = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($statusCode >= 400) {
            $code = is_string($data['code'] ?? null)
                ? $data['code']
                : 'UNKNOWN_ERROR';
            $message = is_string($data['message'] ?? null)
                ? $data['message']
                : 'Wechat Pay returned an unsuccessful merchant response.';

            throw new PaymentException(
                "Wechat merchant API request failed ({$statusCode}) [{$code}]: {$message}"
            );
        }

        return $data;
    }
}
