# qinii/wechat-payment

基于 EasyWeChat 6 的微信支付业务封装。扩展包按普通商户、服务商、合单、退款、商家转账、分账、子商户进件和通知划分业务边界，并根据当前账号配置中的 `version` 自动选择 API V2 或 API V3，调用方不需要选择版本 DTO。

> 完整的方法、DTO 和版本支持情况请查看 [已实现功能清单](docs/FEATURES.md)。清单中未列出的方法，不代表微信支付没有该能力，只表示本扩展包目前没有提供对应封装。

## 环境要求

- PHP 8.0 及以上
- OpenSSL 扩展
- EasyWeChat 6.19.1 及以上
- 一个兼容 Symfony Contracts 的 HTTP Client

## 安装

```bash
composer require qinii/wechat-payment
```

## 已实现模块

| 模块 | 门面入口 | API 版本 | 主要能力 |
| --- | --- | --- | --- |
| 普通商户支付 | `$payment->payment()` | V2 / V3 | 下单、查单、关单、退款、退款查询、异常退款 |
| 服务商支付 | `$payment->partner()` | V2 / V3 | 下单、查单、关单、退款、账单及 V2 付款码配套接口 |
| 合单支付 | `$payment->combine()` | V3 | JSAPI/小程序、APP、H5、Native、查单、关单、退款 |
| 商家转账到零钱 | `$payment->transfer()` | V3 | 转账、撤销、查询、电子回单、免确认收款授权流程 |
| 分账 | `$payment->profitSharing()` | V2 / V3 | 普通商户、服务商和电商收付通分账 |
| 子商户进件 | `$payment->merchant()` | V3 | 特约商户、电商收付通二级商户进件及资料上传 |
| 回调通知 | `$payment->notify()` | V2 / V3 | 支付、退款、转账、分账通知验签、解密和应答 |

“已实现”表示扩展包已提供请求组装和调用入口。接口能否在真实商户号下使用，仍取决于微信支付产品权限、商户类型和产品开通状态。

## 配置

配置第一层是账号组。每个账号组包含应用配置，以及普通商户 `payment`、服务商 `partner` 两个支付配置。支付节点中的 `app_id` 会根据支付类型从 `official_account`、`mini_program` 或 `app` 节点读取，不需要重复配置。

```php
use Qinii\WechatPayment\Enum\ApiVersion;

$config = [
    'default' => [
        'official_account' => [
            'app_id' => 'wx-official-account-appid',
        ],
        'mini_program' => [
            'app_id' => 'wx-mini-program-appid',
        ],
        'app' => [
            'app_id' => 'wx-app-appid',
        ],

        // 账号组级 HTTP 配置会与 payment/partner.http 合并。
        'http' => [
            'http_version' => '1.1',
            'timeout' => 5,
        ],

        'payment' => [
            'version' => ApiVersion::V3,
            'mch_id' => '1900000001',
            'notify_url' => 'https://merchant.example.com/wechat/notify',
            'default_application' => 'official_account',
            'spbill_create_ip' => '203.0.113.10',

            // API V2。
            'v2_secret_key' => 'APIv2-key',

            // API V3。private_key 是商户私钥，certificate 是商户证书。
            'secret_key' => '32-byte-APIv3-key',
            'private_key' => '/absolute/path/apiclient_key.pem',
            'certificate' => '/absolute/path/apiclient_cert.pem',
            'serial_no' => 'MERCHANT_CERTIFICATE_SERIAL_NO',

            // 微信支付公钥模式，和平台证书模式二选一即可。
            'public_key_id' => 'PUB_KEY_ID_...',
            'public_key' => '/absolute/path/wechatpay_pubkey.pem',
            'platform_certs' => [
                // 'PLATFORM_CERT_SERIAL_NO' => '/absolute/path/wechatpay_cert.pem',
            ],
            'platform_certs_cache_dir' => 'storage/wechat-payment/platform-certs',

            // H5 默认场景，可由 DTO 中的 scene_info 覆盖。
            'h5' => [
                'type' => 'Wap',
                'wap_url' => 'https://merchant.example.com',
                'wap_name' => 'Merchant',
            ],
        ],

        'partner' => [
            'version' => ApiVersion::V3,
            // 服务商配置仍使用统一字段名；请求时会映射为 sp_mchid/sp_appid。
            'mch_id' => '1900000099',
            'notify_url' => 'https://merchant.example.com/wechat/partner-notify',
            'default_application' => 'official_account',
            'spbill_create_ip' => '203.0.113.10',
            'v2_secret_key' => 'partner-APIv2-key',
            'secret_key' => '32-byte-partner-APIv3-key',
            'private_key' => '/absolute/path/partner_apiclient_key.pem',
            'certificate' => '/absolute/path/partner_apiclient_cert.pem',
            'serial_no' => 'PARTNER_CERTIFICATE_SERIAL_NO',
            'public_key_id' => 'PUB_KEY_ID_...',
            'public_key' => '/absolute/path/wechatpay_pubkey.pem',
            'platform_certs' => [],
            //平台证书模式，和微信支付公钥模式二选一即可，此字段是证书模式存放位置，可自定义路径
            'platform_certs_cache_dir' => 'storage/wechat-payment/platform-certs',
        ],
    ],
];
```

配置注意事项：

- `private_key`、`certificate`、`public_key` 和 `platform_certs` 的值必须是 PHP 进程可读的文件绝对路径或可解析路径，不能传 PEM 内容。
- `private_key` 必须指向 `apiclient_key.pem`，`certificate` 必须指向 `apiclient_cert.pem`，两者不能写反。
- `serial_no` 是商户 API 证书序列号，不是证书文件路径。
- API V3 未配置微信支付公钥或平台证书时，扩展包会下载平台证书并写入 `platform_certs_cache_dir`，后续请求直接复用缓存。
- `notify_url` 必须是微信可访问的 HTTP/HTTPS 地址，不能携带 query 或 fragment。
- API V2 的退款、撤销、资金账单和部分分账接口需要商户证书。
- 商家转账读取当前账号组的 `payment` 配置，不需要单独增加 `transfer` 配置。
- 转账给小程序或 APP 用户时，必须选择其 OpenID 所属的应用配置；转账所用商户号和证书仍来自同一账号组的 `payment` 配置。

## 创建门面

```php
use Qinii\WechatPayment\Payment;

$payment = Payment::create($config);
```

也可以通过容器注入 `Qinii\WechatCore\Config\ConfigResolver` 后实例化 `Payment`。多账号配置使用 `account()` 切换；它会返回新门面，不会修改原实例：

```php
$defaultPayment = Payment::create($config);
$shopPayment = $defaultPayment->account('shop_2');
```

## 普通商户下单

同一个 `PaymentDto` 同时用于 V2/V3，具体版本由 `payment.version` 决定。

```php
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Payment\PaymentDto;

$result = $payment->payment()->create(PaymentDto::fromArray([
    'description' => '订单 202609010001',
    'out_trade_no' => 'ORDER_202609010001',
    'total_fee' => 100, // 单位：分
    'pay_type' => PayType::MINI_PROGRAM,
    'openid' => 'user-openid',
]));
```

支持的 `pay_type`：

- `PayType::OFFICIAL`：公众号 JSAPI
- `PayType::MINI_PROGRAM`：小程序
- `PayType::APP`：APP
- `PayType::H5`：H5
- `PayType::NATIVE`：Native
- `PayType::MICROPAY`：付款码，仅 API V2

`pay()` 是 `create()` 的兼容别名。下单发生网络中断、`SYSTEMERROR` 或付款码返回 `USERPAYING` 时，不应直接判定失败，应主动调用查单接口确认最终状态。

## 服务商下单

服务商仍从配置中的 `app_id`、`mch_id` 读取服务商 AppID 和商户号，Builder 会在请求时转换为微信接口要求的 `sp_appid`、`sp_mchid`。为了统一调用方式，服务商 AppID 下的用户标识仍通过 DTO 的 `openid` 传入。

```php
use Qinii\WechatPayment\Enum\PayType;
use Qinii\WechatPayment\Partner\PartnerDto;

$result = $payment->partner()->create(PartnerDto::fromArray([
    'sub_mchid' => '1900000002',
    'description' => '服务商订单 202609010001',
    'out_trade_no' => 'PARTNER_202609010001',
    'total_fee' => 100,
    'pay_type' => PayType::OFFICIAL,
    'openid' => 'openid-under-sp-appid',
]));
```

使用子商户 AppID 下的用户标识时，同时传入 `sub_appid` 和 `sub_openid`。服务商 V2/V3 均支持 JSAPI/小程序、APP、H5 和 Native；付款码仅 V2 支持。

## 查单与关单

```php
// 普通商户。
$order = $payment->payment()->queryByOutTradeNo('ORDER_202609010001');
$order = $payment->payment()->queryByTransactionId('420000000000000001');
$payment->payment()->close('ORDER_202609010001');

// 服务商。
$order = $payment->partner()->queryByOutTradeNo(
    'PARTNER_202609010001',
    '1900000002',
);
$payment->partner()->close('PARTNER_202609010001', '1900000002');
```

API V2 查询和关单需要原下单 AppID。普通商户可以通过第二个参数传入原支付类型；服务商可以通过第三个参数传入原下单 `sub_appid`。

## 退款

退款使用独立 DTO，不复用支付 DTO。普通退款与服务商退款均根据各自配置自动选择 V2/V3。

```php
use Qinii\WechatPayment\Partner\PartnerRefundDto;
use Qinii\WechatPayment\Payment\PaymentRefundDto;

$result = $payment->payment()->refund(PaymentRefundDto::fromArray([
    'out_trade_no' => 'ORDER_202609010001',
    'out_refund_no' => 'REFUND_202609010001',
    'total_fee' => 100,
    'refund_fee' => 100,
]));

$result = $payment->partner()->refund(PartnerRefundDto::fromArray([
    'sub_mchid' => '1900000002',
    'out_trade_no' => 'PARTNER_202609010001',
    'out_refund_no' => 'PARTNER_REFUND_202609010001',
    'total_fee' => 100,
    'refund_fee' => 100,
]));
```

平台收付通退款不会根据字段自动推断，调用方应显式使用 `ecommerceRefund()`。普通退款、平台收付通退款、合单退款和异常退款的完整入口见 [退款功能清单](docs/FEATURES.md#退款能力)。

## 商家转账到零钱

未单独设置 `payment.app_id` 时，默认使用 `payment.default_application` 对应的 AppID。若收款人的 OpenID 属于其他应用，在服务入口选择该应用；选择操作返回新实例，不影响原服务：

```php
use Qinii\WechatPayment\Transfer\TransferDto;

$result = $payment->transfer()->application('mini_program')->create(
    TransferDto::fromArray([
        'out_bill_no' => 'TRANSFER202609010001',
        'transfer_scene_id' => '1000',
        'openid' => 'openid-under-mini-program-appid',
        'transfer_amount' => 100,
        'transfer_remark' => '订单结算',
        'transfer_scene_report_infos' => [
            ['info_type' => '活动名称', 'info_content' => '订单结算'],
        ],
    ]),
);
```

同一入口可继续调用查询、撤销、电子回单和免确认收款授权等方法。`application('mini_program')` 从当前账号组的 `mini_program.app_id` 读取 AppID；也可选择 `official_account` 或 `app`。应用配置缺失时会抛出配置异常。

## 回调通知

通知入口接收 PSR-7 `ServerRequestInterface`。内部复用 EasyWeChat 完成 V2 XML 或 V3 JSON 的验签与解密，并返回统一的 `Notification` 对象。

```php
use Qinii\WechatPayment\Enum\PayMode;

// 普通商户支付通知。
$notification = $payment->notify()->payment($psrRequest);

// 服务商支付通知必须显式选择 partner 配置。
$notification = $payment->notify()->payment(
    $psrRequest,
    PayMode::PARTNER,
);

$data = $notification->data();

try {
    // 校验订单金额，并按商户订单号做幂等处理。
    return $notification->success();
} catch (Throwable $exception) {
    return $notification->fail($exception->getMessage());
}
```

其他入口为 `refund()`、`transfer()` 和 `profitSharing()`。验签或解密失败时会抛出 `PaymentException`，业务端不得返回成功应答。

## HTTP Client

`setHttpClient()` 接收实现 `Symfony\Contracts\HttpClient\HttpClientInterface` 的实例，不接受类名字符串。设置后的客户端会被支付请求、证书下载及各业务模块共同使用。

```php
use Qinii\WechatCore\Http\SwooleHttpClient;

$payment->setHttpClient(new SwooleHttpClient());
```

在 Swoole 环境中应注入兼容的客户端实例，避免底层 cURL 客户端设置 Swoole 不支持的选项。

## 异常处理

```php
use Qinii\WechatPayment\Exception\InvalidConfigException;
use Qinii\WechatPayment\Exception\PaymentException;
use Qinii\WechatPayment\Exception\UnsupportedModeException;

try {
    $result = $payment->payment()->create($dto);
} catch (InvalidConfigException $exception) {
    // 配置、证书路径或密钥错误。
} catch (UnsupportedModeException $exception) {
    // 当前 API 版本或业务模式不支持该操作。
} catch (PaymentException $exception) {
    // DTO 校验、签名验签、微信接口或响应解析错误。
}
```

## 验证

```bash
composer test
```

协议测试不会连接微信或产生真实交易。正式上线前仍应使用真实商户环境分别验收下单、前端调起、支付/退款通知、主动查单、关单、异常流程及已开通的增值能力。

## 相关文档

- [已实现功能清单](docs/FEATURES.md)
- [微信支付 API V2 文档](https://pay.weixin.qq.com/doc/v2/merchant/4011937163)
- [微信支付 API V3 文档](https://pay.weixin.qq.com/doc/v3/merchant/4012791856)
- [服务商 API V2 文档](https://pay.weixin.qq.com/doc/v2/partner/4011987842)
- [服务商 API V3 文档](https://pay.weixin.qq.com/doc/v3/partner/4012738519)
