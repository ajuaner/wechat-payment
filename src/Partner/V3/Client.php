<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Partner\V3;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use EasyWeChat\Pay\Utils;
use Qinii\WechatPayment\Config\PartnerConfig;
use Qinii\WechatPayment\Endpoints\PaymentEndpoints;
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Exception\UnsupportedModeException;
use Qinii\WechatPayment\Partner\PartnerDto;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentClient;
use Qinii\WechatPayment\Shared\Abstract\AbstractPaymentDto;

class Client extends AbstractPaymentClient
{
    /** 创建 V3 服务商支付客户端。 */
    public function __construct(
        PartnerConfig $config,
        ApplicationContract $application,
        private Builder $builder,
    ) {
        parent::__construct($config, $application);
    }

    /** 发起 V3 服务商 H5 支付。 */
    public function h5(AbstractPaymentDto $dto): array
    {
        return $this->requestPayment(
            $dto,
            PaymentEndpoints::V3_PARTNER_TRANSACTION_H5,
        );
    }

    /** 发起 V3 服务商 APP 支付并生成客户端调起参数。 */
    public function app(AbstractPaymentDto $dto): array
    {
        $paymentPrepare = $this->requestPayment(
            $dto,
            PaymentEndpoints::V3_PARTNER_TRANSACTION_APP,
        );

        if (! isset($paymentPrepare['prepay_id'])) {
            return $paymentPrepare;
        }

        $dto = $this->partnerDto($dto);
        $useSubMerchant = $dto->sub_appid !== '';
        $params = $this->utils()->buildAppConfig(
            (string) $paymentPrepare['prepay_id'],
            $useSubMerchant ? $dto->sub_appid : $this->config->app_id,
        );

        // APP 调起签名不包含 partnerid，按实际使用的 appid 配套商户号。
        $params['partnerid'] = $useSubMerchant
            ? $dto->sub_mchid
            : $this->config->mch_id;

        return $params;
    }

    /** 发起 V3 服务商公众号或小程序支付。 */
    public function jsapi(AbstractPaymentDto $dto): array
    {
        $paymentPrepare = $this->requestPayment(
            $dto,
            PaymentEndpoints::V3_PARTNER_TRANSACTION_JSAPI,
        );

        if (! isset($paymentPrepare['prepay_id'])) {
            return $paymentPrepare;
        }

        $dto = $this->partnerDto($dto);
        if ($dto->payType() === PayType::MINI_PROGRAM) {
            return $this->utils()->buildMiniAppConfig(
                (string) $paymentPrepare['prepay_id'],
                $this->payerAppId($dto),
            );
        }
        return $this->utils()->buildSdkConfig(
            (string) $paymentPrepare['prepay_id'],
            $this->payerAppId($dto),
        );
    }

    /** 发起 V3 服务商 Native 二维码支付。 */
    public function native(AbstractPaymentDto $dto): array
    {
        return $this->requestPayment(
            $dto,
            PaymentEndpoints::V3_PARTNER_TRANSACTION_NATIVE,
        );
    }

    /** 服务商 V3 暂不支持付款码支付。 */
    public function micropay(AbstractPaymentDto $dto): array
    {
        return $this->notImplemented('micropay');
    }

    /** 按微信支付订单号查询服务商订单。 */
    public function queryByTransactionId(
        string $transactionId,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->getJson(
            $this->uri(
                PaymentEndpoints::V3_PARTNER_TRANSACTION_ID,
                ['transaction_id' => $this->requiredValue(
                    $transactionId,
                    'transaction_id',
                    32,
                )],
            ),
            $this->merchantQuery($subMchid),
        );
    }

    /** 按商户订单号查询服务商订单。 */
    public function queryByOutTradeNo(
        string $outTradeNo,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->getJson(
            $this->uri(
                PaymentEndpoints::V3_PARTNER_TRANSACTION_OUT_TRADE_NO,
                ['out_trade_no' => $this->outTradeNo($outTradeNo)],
            ),
            $this->merchantQuery($subMchid),
        );
    }

    /** 按商户订单号关闭服务商订单。 */
    public function close(
        string $outTradeNo,
        string $subMchid,
        ?string $subAppid = null,
    ): array {
        return $this->postJson(
            $this->uri(
                PaymentEndpoints::V3_PARTNER_TRANSACTION_CLOSE,
                ['out_trade_no' => $this->outTradeNo($outTradeNo)],
            ),
            [
                'sp_mchid' => $this->config->mch_id,
                'sub_mchid' => $this->requiredValue(
                    $subMchid,
                    'sub_mchid',
                    32,
                ),
            ],
        );
    }

    /** 申请服务商交易账单。 */
    public function applyTradeBill(
        string $billDate,
        ?string $subMchid = null,
        string $billType = 'ALL',
        ?string $tarType = null,
    ): array {
        $billType = strtoupper(trim($billType));
        if (! in_array($billType, ['ALL', 'SUCCESS', 'REFUND'], true)) {
            throw new PaymentException(
                'Wechat Pay trade bill [bill_type] must be ALL, SUCCESS or REFUND.'
            );
        }

        $query = [
            'bill_date' => $this->billDate($billDate),
            'bill_type' => $billType,
        ];

        if ($subMchid !== null && trim($subMchid) !== '') {
            $query['sub_mchid'] = $this->requiredValue(
                $subMchid,
                'sub_mchid',
                32,
            );
        }

        if (($tarType = $this->tarType($tarType)) !== null) {
            $query['tar_type'] = $tarType;
        }

        return $this->getJson(PaymentEndpoints::V3_TRADE_BILL, $query);
    }

    /** 申请服务商资金账单。 */
    public function applyFundFlowBill(
        string $billDate,
        string $accountType = 'BASIC',
        ?string $tarType = null,
    ): array {
        $accountType = strtoupper(trim($accountType));
        if (! in_array(
            $accountType,
            ['BASIC', 'OPERATION', 'FEES'],
            true,
        )) {
            throw new PaymentException(
                'Wechat Pay fund flow bill [account_type] must be BASIC, OPERATION or FEES.'
            );
        }

        $query = [
            'bill_date' => $this->billDate($billDate),
            'account_type' => $accountType,
        ];

        if (($tarType = $this->tarType($tarType)) !== null) {
            $query['tar_type'] = $tarType;
        }

        return $this->getJson(PaymentEndpoints::V3_FUND_FLOW_BILL, $query);
    }

    /** 使用微信返回的临时地址下载账单原始内容。 */
    public function downloadBill(string $downloadUrl): string
    {
        $downloadUrl = trim($downloadUrl);
        $parts = parse_url($downloadUrl);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';

        if (
            ! is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || ! in_array(
                $host,
                ['api.mch.weixin.qq.com', 'api2.mch.weixin.qq.com'],
                true,
            )
        ) {
            throw new PaymentException(
                'Wechat Pay [download_url] must be an official HTTPS bill URL.'
            );
        }

        $response = $this->application->getClient()->get($downloadUrl);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $this->throwResponseError($response, $statusCode);
        }

        return $response->getContent(false);
    }

    /** 为暂未实现的服务商支付类型抛出统一异常。 */
    private function notImplemented(string $payType): array
    {
        throw new UnsupportedModeException(
            "Wechat Pay API v3 partner {$payType} payment is not implemented yet."
        );
    }

    /** 根据使用的 openid 返回客户端调起所需的 appid。 */
    private function payerAppId(PartnerDto $dto): string
    {
        return $dto->openid !== ''
            ? $this->config->app_id
            : $dto->sub_appid;
    }

    /** 创建 EasyWeChat V3 签名和客户端参数工具。 */
    private function utils(): Utils
    {
        return new Utils($this->application->getMerchant());
    }

    /** 组装并发送 V3 服务商支付请求，统一处理错误响应。 */
    private function requestPayment(
        AbstractPaymentDto $dto,
        string $endpoint,
    ): array {
        $params = $this->builder->build($dto);
        $params['notify_url'] = $this->resolveNotifyUrl($params);
        $params = array_filter(
            $params,
            static fn ($value): bool => $value !== '' && $value !== null,
        );

        return $this->postJson($endpoint, $params);
    }

    /** 发送服务商 V3 JSON POST 请求。 */
    private function postJson(string $endpoint, array $payload): array
    {
        $response = $this->application->getClient()->post(
            PaymentEndpoints::BASE_PAY_URL . $endpoint,
            ['json' => $payload],
        );

        return $this->decodeJsonResponse($response);
    }

    /** 发送服务商 V3 GET 请求。 */
    private function getJson(string $endpoint, array $query = []): array
    {
        $options = $query === [] ? [] : ['query' => $query];
        $response = $this->application->getClient()->get(
            PaymentEndpoints::BASE_PAY_URL . $endpoint,
            $options,
        );

        return $this->decodeJsonResponse($response);
    }

    /** 解析服务商 V3 JSON 响应并兼容关单的 204 空响应。 */
    private function decodeJsonResponse(object $response): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode === 204) {
            return [];
        }

        if ($statusCode >= 400) {
            $this->throwResponseError($response, $statusCode);
        }

        return $response->toArray(false);
    }

    /** 将微信支付错误响应转换为扩展包统一异常。 */
    private function throwResponseError(object $response, int $statusCode): void
    {
        $data = $response->toArray(false);
        $code = is_string($data['code'] ?? null)
            ? $data['code']
            : 'UNKNOWN_ERROR';
        $message = is_string($data['message'] ?? null)
            ? $data['message']
            : 'Wechat Pay returned an unsuccessful response.';

        throw new PaymentException(
            "Wechat Pay API V3 partner request failed ({$statusCode}) [{$code}]: {$message}"
        );
    }

    /** 替换并编码接口路径中的参数。 */
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

    /** 生成服务商和子商户查单所需的查询参数。 */
    private function merchantQuery(string $subMchid): array
    {
        return [
            'sp_mchid' => $this->config->mch_id,
            'sub_mchid' => $this->requiredValue(
                $subMchid,
                'sub_mchid',
                32,
            ),
        ];
    }

    /** 校验并返回服务商接口必填字符串。 */
    private function requiredValue(
        string $value,
        string $field,
        int $maxLength,
    ): string {
        $value = trim($value);
        if ($value === '') {
            throw new PaymentException(
                "Wechat Pay partner [{$field}] is required."
            );
        }

        if (strlen($value) > $maxLength) {
            throw new PaymentException(
                "Wechat Pay partner [{$field}] must not exceed {$maxLength} bytes."
            );
        }

        return $value;
    }

    /** 校验并返回商户订单号。 */
    private function outTradeNo(string $outTradeNo): string
    {
        $outTradeNo = trim($outTradeNo);
        if (! preg_match('/^[0-9A-Za-z_\-|*]{6,32}$/D', $outTradeNo)) {
            throw new PaymentException(
                'Wechat Pay partner [out_trade_no] must be 6-32 valid characters.'
            );
        }

        return $outTradeNo;
    }

    /** 校验并返回 yyyy-MM-dd 格式的账单日期。 */
    private function billDate(string $billDate): string
    {
        $billDate = trim($billDate);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $billDate);

        if ($date === false || $date->format('Y-m-d') !== $billDate) {
            throw new PaymentException(
                'Wechat Pay bill [bill_date] must use YYYY-MM-DD format.'
            );
        }

        return $billDate;
    }

    /** 校验并返回可选的 GZIP 账单压缩类型。 */
    private function tarType(?string $tarType): ?string
    {
        if ($tarType === null || trim($tarType) === '') {
            return null;
        }

        $tarType = strtoupper(trim($tarType));
        if ($tarType !== 'GZIP') {
            throw new PaymentException(
                'Wechat Pay bill [tar_type] must be GZIP.'
            );
        }

        return $tarType;
    }

    /** 确认客户端接收的是服务商 PartnerDto。 */
    private function partnerDto(AbstractPaymentDto $dto): PartnerDto
    {
        if (! $dto instanceof PartnerDto) {
            throw new PaymentException(
                'V3 Partner client only supports PartnerDto.'
            );
        }

        return $dto;
    }
}
