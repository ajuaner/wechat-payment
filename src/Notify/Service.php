<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Notify;

use EasyWeChat\Kernel\Support\Xml;
use EasyWeChat\Pay\LegacySignature;
use EasyWeChat\Pay\Server;
use EasyWeChat\Pay\Validator;
use Psr\Http\Message\ServerRequestInterface;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Config\PaymentConfigResolver;
use Qinii\WechatPayment\Config\TransferConfigResolver;
use Qinii\WechatPayment\Enum\ApiVersion;
use Qinii\WechatPayment\Enum\PayMode;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Shared\WechatContext;
use Throwable;

final class Service
{
    /** 创建微信支付通知验签与解密服务。 */
    public function __construct(
        private WechatContext $context,
        private ?PaymentConfigResolver $paymentConfigResolver = null,
        private ?TransferConfigResolver $transferConfigResolver = null,
    ) {
    }

    /** 验签并解密支付成功通知。 */
    public function payment(
        ServerRequestInterface $request,
        string $mode = PayMode::PAYMENT,
    ): Notification {
        return $this->parse($request, $this->paymentConfig($mode));
    }

    /** 验签并解密退款结果通知。 */
    public function refund(
        ServerRequestInterface $request,
        string $mode = PayMode::PAYMENT,
    ): Notification {
        // V2 退款通知只有使用 API V2 密钥加密的 req_info，不包含 sign。
        return $this->parse(
            $request,
            $this->paymentConfig($mode),
            false,
        );
    }

    /** 验签并解密商家转账通知。 */
    public function transfer(ServerRequestInterface $request): Notification
    {
        $config = ($this->transferConfigResolver ?? new TransferConfigResolver())
            ->resolve($this->context->accountConfig());

        return $this->parse($request, $config);
    }

    /** 验签并解密分账结果通知。 */
    public function profitSharing(
        ServerRequestInterface $request,
        string $mode = PayMode::PAYMENT,
    ): Notification {
        return $this->parse($request, $this->paymentConfig($mode));
    }

    /** 使用指定普通商户或服务商配置解析通知。 */
    private function paymentConfig(string $mode): AbstractWechatPayConfig
    {
        return ($this->paymentConfigResolver ?? new PaymentConfigResolver())
            ->resolveMode($this->context->accountConfig(), $mode);
    }

    /** 使用 EasyWeChat 验签、解密并返回统一通知结果。 */
    private function parse(
        ServerRequestInterface $request,
        AbstractWechatPayConfig $config,
        bool $validateV2Signature = true,
    ): Notification {
        try {
            $body = (string) $request->getBody();
            $version = $this->isXml($body)
                ? ApiVersion::V2
                : ApiVersion::V3;

            if ($config->version !== $version) {
                throw new PaymentException(
                    "Wechat notification protocol {$version} does not match configured {$config->version}."
                );
            }

            $application = $this->context->application($config);
            $merchant = $application->getMerchant();

            if ($version === ApiVersion::V2) {
                if ($validateV2Signature) {
                    $this->validateV2Signature($body, $merchant);
                }

                if (! str_contains(
                    strtolower($request->getHeaderLine('Content-Type')),
                    'xml',
                )) {
                    $request = $request->withHeader(
                        'Content-Type',
                        'text/xml; charset=utf-8',
                    );
                }
            } else {
                (new Validator($merchant))->validate($request);

                return new Notification(
                    (new Server(
                        $merchant,
                        $request,
                    ))->getDecryptedMessage($request),
                    $version,
                );
            }

            return new Notification(
                (new Server(
                    $merchant,
                    $request,
                ))->getDecryptedMessage($request),
                $version,
            );
        } catch (PaymentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PaymentException(
                'Wechat notification validation or decryption failed: '
                . $exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    /** 使用 EasyWeChat V2 签名器校验 XML 通知签名。 */
    private function validateV2Signature(
        string $body,
        object $merchant,
    ): void {
        $attributes = Xml::parse($body);
        if (! is_array($attributes)) {
            throw new PaymentException(
                'Wechat Pay V2 notification body must be valid XML.'
            );
        }

        $provided = strtoupper(trim((string) ($attributes['sign'] ?? '')));
        if ($provided === '') {
            throw new PaymentException(
                'Wechat Pay V2 notification [sign] is required.'
            );
        }

        unset($attributes['sign']);
        $signed = (new LegacySignature($merchant))->sign($attributes);
        $expected = strtoupper(trim((string) ($signed['sign'] ?? '')));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            throw new PaymentException(
                'Wechat Pay V2 notification signature is invalid.'
            );
        }
    }

    /** 根据请求体识别 V2 XML 或 V3 JSON 通知。 */
    private function isXml(string $body): bool
    {
        return preg_match(
            '/^(?:<\?xml[^>]*>\s*)?<xml(?:\s|>)/i',
            ltrim($body),
        ) === 1;
    }
}
