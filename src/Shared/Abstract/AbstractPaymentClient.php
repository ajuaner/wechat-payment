<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Shared\Abstract;

use EasyWeChat\Pay\Contracts\Application as ApplicationContract;
use Qinii\WechatPayment\Config\AbstractWechatPayConfig;
use Qinii\WechatPayment\Exception\PaymentException;

abstract class AbstractPaymentClient
{
    /** 保存配置和 EasyWeChat 应用，用于后续请求。 */
    public function __construct(
        protected AbstractWechatPayConfig $config,
        protected ApplicationContract $application,
    ) {}

    /** 校验并解析请求级通知地址。 */
    protected function resolveNotifyUrl(array $params): string
    {
        $notifyUrl = trim((string) ($params['notify_url'] ?? ''));

        if ($notifyUrl === '') {
            $notifyUrl = trim($this->config->notify_url);
        }

        if ($notifyUrl === '') {
            throw new PaymentException(
                'Wechat payment [notify_url] is required.'
            );
        }

        if (filter_var($notifyUrl, FILTER_VALIDATE_URL) === false) {
            throw new PaymentException(
                'Wechat payment [notify_url] must be a valid URL.'
            );
        }

        $scheme = strtolower(
            (string) parse_url($notifyUrl, PHP_URL_SCHEME)
        );

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new PaymentException(
                'Wechat payment [notify_url] must use HTTP or HTTPS.'
            );
        }

        if (
            parse_url($notifyUrl, PHP_URL_QUERY) !== null
            || parse_url($notifyUrl, PHP_URL_FRAGMENT) !== null
        ) {
            throw new PaymentException(
                'Wechat payment [notify_url] must not contain query parameters or fragments.'
            );
        }

        return $notifyUrl;
    }

    /**
     * 按 DTO 选择的支付方法分发普通支付请求。
     *
     * 版本客户端分别实现 h5、app、jsapi、native 和 micropay 的协议细节。
     */
    public function create(AbstractPaymentDto $dto): array
    {
        return $this->{$dto->method()}($dto);
    }

    /** 发起 H5 支付。 */
    abstract public function h5(AbstractPaymentDto $dto): array;
    /** 发起 APP 支付。 */
    abstract public function app(AbstractPaymentDto $dto): array;
    /** 发起微信公众号或小程序支付。 */
    abstract public function jsapi(AbstractPaymentDto $dto): array;
    /** 发起 Native 二维码支付。 */
    abstract public function native(AbstractPaymentDto $dto): array;
    /** 发起付款码支付。 */
    abstract public function micropay(AbstractPaymentDto $dto): array;
}
