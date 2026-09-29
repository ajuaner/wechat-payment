<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Partner\V2;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use EasyWeChat\Pay\LegacySignature;
use EasyWeChat\Pay\Utils;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Endpoints\PaymentEndpoints;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Partner\PartnerDto;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentClient;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentDto;

final class Client extends AbstractPaymentClient
{
    /** 创建完整的 V2 服务商支付客户端。 */
    public function __construct(
        PartnerConfig $config,
        ApplicationContract $application,
        ?Builder $builder = null,
    ) {
        $this->partnerConfig = $config;
        $this->builder = $builder ?? new Builder($config);
        parent::__construct($config, $application);
    }

    /** 保存服务商 V2 专属配置。 */
    private PartnerConfig $partnerConfig;
    /** 保存服务商 V2 请求参数构造器。 */
    private Builder $builder;

    /** 发起 V2 服务商 H5 支付。 */
    public function h5(AbstractPaymentDto $dto): array
    {
        return $this->requestUnifiedOrder($dto);
    }

    /** 发起 V2 服务商 APP 支付并生成客户端调起参数。 */
    public function app(AbstractPaymentDto $dto): array
    {
        $paymentPrepare = $this->requestUnifiedOrder($dto);
        if (! isset($paymentPrepare['prepay_id'])) {
            return $paymentPrepare;
        }

        $dto = $this->partnerDto($dto);
        $params = [
            'appid' => $this->appLaunchAppId($dto),
            'partnerid' => $dto->sub_mchid,
            'prepayid' => (string) $paymentPrepare['prepay_id'],
            'package' => 'Sign=WXPay',
            'noncestr' => bin2hex(random_bytes(16)),
            'timestamp' => time(),
        ];

        // APP 调起参数使用服务商 APIv2 密钥签名，partnerid 必须为子商户号。
        $signed = (new LegacySignature($this->application->getMerchant()))->sign(
            $params + [
                'sign_type' => $this->signType($dto),
                'nonce_str' => '',
            ],
        );
        unset($signed['sign_type']);

        return $signed;
    }

    /** 发起 V2 服务商公众号或小程序支付并生成客户端调起参数。 */
    public function jsapi(AbstractPaymentDto $dto): array
    {
        $paymentPrepare = $this->requestUnifiedOrder($dto);
        if (! isset($paymentPrepare['prepay_id'])) {
            return $paymentPrepare;
        }

        $dto = $this->partnerDto($dto);
        $prepayId = (string) $paymentPrepare['prepay_id'];
        $appId = $this->payerAppId($dto);
        $signType = $this->signType($dto);

        if ($dto->payType() === PayType::MINI_PROGRAM) {
            return $this->utils()->buildMiniAppConfig(
                $prepayId,
                $appId,
                $signType,
            );
        }

        return $this->utils()->buildSdkConfig(
            $prepayId,
            $appId,
            $signType,
        );
    }

    /** 发起 V2 服务商 Native 二维码支付。 */
    public function native(AbstractPaymentDto $dto): array
    {
        return $this->requestUnifiedOrder($dto);
    }

    /** 发起 V2 服务商付款码支付。 */
    public function micropay(AbstractPaymentDto $dto): array
    {
        $dto = $this->partnerDto($dto);
        $payload = $this->builder->buildMicropay($dto);

        return $this->postXml(
            PaymentEndpoints::V2_MICROPAY,
            $this->paymentParams($dto, $payload),
        );
    }

    /** 按商户订单号查询 V2 服务商订单。 */
    public function queryByOutTradeNo(
        string $outTradeNo,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->postXml(
            PaymentEndpoints::V2_ORDER_QUERY,
            $this->orderParams($subMchid, $subAppid, [
                'out_trade_no' => $this->outTradeNo($outTradeNo),
            ]),
        );
    }

    /** 按微信支付订单号查询 V2 服务商订单。 */
    public function queryByTransactionId(
        string $transactionId,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->postXml(
            PaymentEndpoints::V2_ORDER_QUERY,
            $this->orderParams($subMchid, $subAppid, [
                'transaction_id' => $this->requiredValue(
                    $transactionId,
                    'transaction_id',
                    32,
                ),
            ]),
        );
    }

    /** 按商户订单号关闭 V2 服务商订单。 */
    public function close(
        string $outTradeNo,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->postXml(
            PaymentEndpoints::V2_CLOSE_ORDER,
            $this->orderParams($subMchid, $subAppid, [
                'out_trade_no' => $this->outTradeNo($outTradeNo),
            ]),
        );
    }

    /** 查询 V2 服务商付款码支付订单。 */
    public function queryMicropay(
        string $outTradeNo,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->queryByOutTradeNo(
            $outTradeNo,
            $subMchid,
            $subAppid,
        );
    }

    /** 撤销 V2 服务商付款码支付订单，请求使用服务商 API 证书。 */
    public function reverseMicropay(
        string $outTradeNo,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->postXml(
            PaymentEndpoints::V2_REVERSE_ORDER,
            $this->orderParams($subMchid, $subAppid, [
                'out_trade_no' => $this->outTradeNo($outTradeNo),
            ]),
            true,
        );
    }

    /** 通过付款码查询服务商或子商户维度的用户 OpenID。 */
    public function authCodeToOpenid(
        string $authCode,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        $authCode = trim($authCode);
        if (preg_match('/^1[0-5]\d{16}$/D', $authCode) !== 1) {
            throw new PaymentException(
                'Wechat Pay partner V2 [auth_code] must be an 18-digit payment code with prefix 10-15.'
            );
        }

        return $this->postXml(
            PaymentEndpoints::V2_AUTH_CODE_TO_OPENID,
            $this->orderParams($subMchid, $subAppid, [
                'auth_code' => $authCode,
            ]),
        );
    }

    /** 将 V2 Native 支付长链接转换为更适合生成二维码的短链接。 */
    public function shortUrl(
        string $longUrl,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        $longUrl = trim($longUrl);
        if (! str_starts_with($longUrl, 'weixin://') || strlen($longUrl) > 512) {
            throw new PaymentException(
                'Wechat Pay partner V2 [long_url] must be a weixin:// URL within 512 bytes.'
            );
        }

        return $this->postXml(
            PaymentEndpoints::V2_SHORT_URL,
            $this->orderParams($subMchid, $subAppid, [
                'long_url' => $longUrl,
            ]),
        );
    }

    /** 上报 V2 服务商接口调用耗时和返回结果。 */
    public function reportTransaction(
        array $report,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        foreach ([
            'interface_url',
            'execute_time_',
            'return_code',
            'result_code',
            'user_ip',
        ] as $field) {
            if (! isset($report[$field]) || trim((string) $report[$field]) === '') {
                throw new PaymentException(
                    "Wechat Pay partner V2 report [{$field}] is required."
                );
            }
        }

        if (filter_var($report['interface_url'], FILTER_VALIDATE_URL) === false) {
            throw new PaymentException(
                'Wechat Pay partner V2 report [interface_url] must be a valid URL.'
            );
        }

        if ((int) $report['execute_time_'] < 0) {
            throw new PaymentException(
                'Wechat Pay partner V2 report [execute_time_] must not be negative.'
            );
        }

        if (filter_var($report['user_ip'], FILTER_VALIDATE_IP) === false) {
            throw new PaymentException(
                'Wechat Pay partner V2 report [user_ip] must be a valid IP address.'
            );
        }

        return $this->postXml(
            PaymentEndpoints::V2_TRANSACTION_REPORT,
            $this->orderParams($subMchid, $subAppid, $report),
        );
    }

    /** 下载 V2 服务商交易账单并返回原始内容。 */
    public function applyTradeBill(
        string $billDate,
        ?string $subMchid = null,
        string $billType = 'ALL',
        ?string $tarType = null,
    ): string {
        $billType = strtoupper(trim($billType));
        if (! in_array(
            $billType,
            ['ALL', 'SUCCESS', 'REFUND', 'RECHARGE_REFUND'],
            true,
        )) {
            throw new PaymentException(
                'Wechat Pay V2 trade bill [bill_type] is invalid.'
            );
        }

        $params = [
            'appid' => $this->config->app_id,
            'mch_id' => $this->config->mch_id,
            'nonce_str' => bin2hex(random_bytes(16)),
            'bill_date' => $this->billDate($billDate),
            'bill_type' => $billType,
            'tar_type' => $this->tarType($tarType),
        ];

        if ($subMchid !== null && trim($subMchid) !== '') {
            $params['sub_mch_id'] = $this->requiredValue(
                $subMchid,
                'sub_mch_id',
                32,
            );
        }

        return $this->postRaw(PaymentEndpoints::V2_TRADE_BILL, $params);
    }

    /** 下载 V2 服务商资金账单并返回原始内容。 */
    public function applyFundFlowBill(
        string $billDate,
        string $accountType = 'BASIC',
        ?string $tarType = null,
    ): string {
        $accountType = match (strtoupper(trim($accountType))) {
            'BASIC' => 'Basic',
            'OPERATION' => 'Operation',
            'FEES' => 'Fees',
            default => throw new PaymentException(
                'Wechat Pay V2 fund flow bill [account_type] must be BASIC, OPERATION or FEES.'
            ),
        };

        return $this->postRaw(
            PaymentEndpoints::V2_FUND_FLOW_BILL,
            [
                'appid' => $this->config->app_id,
                'mch_id' => $this->config->mch_id,
                'nonce_str' => bin2hex(random_bytes(16)),
                'sign_type' => 'HMAC-SHA256',
                'bill_date' => $this->billDate($billDate),
                'account_type' => $accountType,
                'tar_type' => $this->tarType($tarType),
            ],
            true,
        );
    }

    /** V2 账单接口直接返回内容，不接受 V3 临时下载地址。 */
    public function downloadBill(string $downloadUrl): string
    {
        throw new UnsupportedModeException(
            'Wechat Pay API v2 bill operations return bill content directly.'
        );
    }

    /** 调用 V2 统一下单接口。 */
    private function requestUnifiedOrder(AbstractPaymentDto $dto): array
    {
        $dto = $this->partnerDto($dto);
        $payload = $this->builder->build($dto);
        $params = $this->paymentParams($dto, $payload);
        $params['notify_url'] = $this->resolveNotifyUrl($params);

        return $this->postXml(
            PaymentEndpoints::V2_UNIFIED_ORDER,
            $params,
        );
    }

    /** 合并支付业务参数和服务商身份参数。 */
    private function paymentParams(PartnerDto $dto, array $payload): array
    {
        return array_merge($payload, [
            'appid' => $this->config->app_id,
            'mch_id' => $this->config->mch_id,
            'sub_appid' => $dto->sub_appid,
            'sub_mch_id' => $dto->sub_mchid,
            'spbill_create_ip' => $this->resolveClientIp($dto),
            'nonce_str' => bin2hex(random_bytes(16)),
        ]);
    }

    /** 发送 V2 XML 请求并返回解析后的响应。 */
    private function postXml(
        string $endpoint,
        array $payload,
        bool $requiresClientCertificate = false,
    ): array {
        $response = $this->application->getClient()->post(
            PaymentEndpoints::BASE_PAY_URL . $endpoint,
            $this->requestOptions($payload, $requiresClientCertificate),
        );

        return $response->toArray();
    }

    /** 发送账单请求并返回文本或 GZIP 原始内容。 */
    private function postRaw(
        string $endpoint,
        array $payload,
        bool $requiresClientCertificate = false,
    ): string {
        $response = $this->application->getClient()->post(
            PaymentEndpoints::BASE_PAY_URL . $endpoint,
            $this->requestOptions($payload, $requiresClientCertificate),
        );

        return $response->getContent(false);
    }

    /** 构造 XML 请求选项，并按需附加服务商 API 证书。 */
    private function requestOptions(
        array $payload,
        bool $requiresClientCertificate,
    ): array {
        $options = ['xml' => array_filter(
            $payload,
            static fn ($value): bool => $value !== '' && $value !== null,
        )];

        if (! $requiresClientCertificate) {
            return $options;
        }

        if (
            $this->config->certificate === ''
            || $this->config->private_key === ''
        ) {
            throw new PaymentException(
                'Wechat Pay partner V2 operation requires certificate and private_key.'
            );
        }

        $options['local_cert'] = $this->config->certificate;
        $options['local_pk'] = $this->config->private_key;

        return $options;
    }

    /** 组装 V2 服务商订单操作共用参数。 */
    private function orderParams(
        string $subMchid,
        ?string $subAppid,
        array $params,
    ): array {
        return array_merge($params, [
            'appid' => $this->config->app_id,
            'mch_id' => $this->config->mch_id,
            'sub_mch_id' => $this->requiredValue(
                $subMchid,
                'sub_mch_id',
                32,
            ),
            'sub_appid' => $subAppid === null
                ? null
                : $this->requiredValue($subAppid, 'sub_appid', 32),
            'nonce_str' => bin2hex(random_bytes(16)),
        ]);
    }

    /** 解析 V2 请求使用的终端 IP。 */
    private function resolveClientIp(PartnerDto $dto): string
    {
        $clientIp = trim(
            $dto->payer_client_ip !== ''
                ? $dto->payer_client_ip
                : (string) $this->partnerConfig->spbill_create_ip,
        );

        if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            throw new PaymentException(
                'Wechat Pay partner V2 requires a valid payer_client_ip or spbill_create_ip.'
            );
        }

        return $clientIp;
    }

    /** 根据用户标识返回 JSAPI 或小程序调起支付使用的 AppID。 */
    private function payerAppId(PartnerDto $dto): string
    {
        return $dto->openid !== ''
            ? $this->config->app_id
            : $dto->sub_appid;
    }

    /** 返回 APP 调起支付选择的服务商或子商户 AppID。 */
    private function appLaunchAppId(PartnerDto $dto): string
    {
        return $dto->sub_appid !== ''
            ? $dto->sub_appid
            : $this->config->app_id;
    }

    /** 返回统一下单和调起支付共同使用的签名类型。 */
    private function signType(PartnerDto $dto): string
    {
        $signType = strtoupper(trim((string) ($dto->extra['sign_type'] ?? 'MD5')));
        if (! in_array($signType, ['MD5', 'HMAC-SHA256'], true)) {
            throw new PaymentException(
                'Wechat Pay partner V2 [sign_type] must be MD5 or HMAC-SHA256.'
            );
        }

        return $signType;
    }

    /** 返回 EasyWeChat 支付参数工具。 */
    private function utils(): Utils
    {
        return new Utils($this->application->getMerchant());
    }

    /** 将 V2 和 V3 日期格式统一转换为 V2 的 yyyyMMdd。 */
    private function billDate(string $billDate): string
    {
        $billDate = trim($billDate);
        if (preg_match('/^\d{8}$/D', $billDate) === 1) {
            return $billDate;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $billDate);
        if ($date === false || $date->format('Y-m-d') !== $billDate) {
            throw new PaymentException(
                'Wechat Pay V2 bill [bill_date] must use yyyyMMdd or yyyy-MM-dd format.'
            );
        }

        return $date->format('Ymd');
    }

    /** 校验账单压缩类型，只允许微信支持的 GZIP。 */
    private function tarType(?string $tarType): ?string
    {
        if ($tarType === null || trim($tarType) === '') {
            return null;
        }

        if (strtoupper(trim($tarType)) !== 'GZIP') {
            throw new PaymentException(
                'Wechat Pay V2 bill [tar_type] must be GZIP.'
            );
        }

        return 'GZIP';
    }

    /** 校验并返回服务商 V2 商户订单号。 */
    private function outTradeNo(string $outTradeNo): string
    {
        $outTradeNo = trim($outTradeNo);
        if (! preg_match('/^[0-9A-Za-z_\-|*]{6,32}$/D', $outTradeNo)) {
            throw new PaymentException(
                'Wechat Pay partner V2 [out_trade_no] must be 6-32 valid characters.'
            );
        }

        return $outTradeNo;
    }

    /** 校验并返回服务商 V2 接口必填字符串。 */
    private function requiredValue(
        string $value,
        string $field,
        int $maxLength,
    ): string {
        $value = trim($value);
        if ($value === '') {
            throw new PaymentException(
                "Wechat Pay partner V2 [{$field}] is required."
            );
        }

        if (strlen($value) > $maxLength) {
            throw new PaymentException(
                "Wechat Pay partner V2 [{$field}] must not exceed {$maxLength} bytes."
            );
        }

        return $value;
    }

    /** 确认当前客户端接收的是服务商支付 DTO。 */
    private function partnerDto(AbstractPaymentDto $dto): PartnerDto
    {
        if (! $dto instanceof PartnerDto) {
            throw new PaymentException(
                'V2 PartnerClient only supports PartnerDto.'
            );
        }

        return $dto;
    }
}
