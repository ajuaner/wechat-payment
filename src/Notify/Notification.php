<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Notify;

use EasyWeChat\Kernel\Message;
use EasyWeChat\Kernel\Support\Xml;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Qinii\WechatPayment\Enum\ApiVersion;

final class Notification
{
    /** 保存解密后的消息及其通知协议版本。 */
    public function __construct(
        private Message $message,
        private string $version,
    ) {
    }

    /** 返回 EasyWeChat 解密后的原始消息对象。 */
    public function message(): Message
    {
        return $this->message;
    }

    /** 返回解密后的通知业务数据。 */
    public function data(): array
    {
        return $this->message->toArray();
    }

    /** 返回当前通知使用的微信支付协议版本。 */
    public function version(): string
    {
        return $this->version;
    }

    /** 生成微信支付要求的成功应答。 */
    public function success(): ResponseInterface
    {
        if ($this->version === ApiVersion::V2) {
            return new Response(
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
                Xml::build([
                    'return_code' => 'SUCCESS',
                    'return_msg' => 'OK',
                ]),
            );
        }

        return new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            (string) json_encode(
                ['code' => 'SUCCESS', 'message' => '成功'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        );
    }

    /** 生成微信支付可识别的失败应答，使微信按规则重试通知。 */
    public function fail(string $message = '处理失败'): ResponseInterface
    {
        if ($this->version === ApiVersion::V2) {
            return new Response(
                200,
                ['Content-Type' => 'text/xml; charset=utf-8'],
                Xml::build([
                    'return_code' => 'FAIL',
                    'return_msg' => $message,
                ]),
            );
        }

        return new Response(
            500,
            ['Content-Type' => 'application/json; charset=utf-8'],
            (string) json_encode(
                ['code' => 'FAIL', 'message' => $message],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        );
    }
}
