# 已实现功能清单

本文档按扩展包的公开门面列出当前已经封装的微信支付能力。方法是否可在真实商户号下调用，取决于商户类型、产品权限、API 版本以及微信支付侧的开通状态。

## 入口总览

```php
use Qinii\WechatPayment\Payment;

$payment = Payment::create($config);

$payment->payment();       // 普通商户支付
$payment->partner();       // 服务商支付
$payment->combine();       // 合单支付
$payment->transfer();      // 商家转账到零钱
$payment->profitSharing(); // 分账
$payment->merchant();      // 子商户进件与管理
$payment->notify();        // 通知验签、解密与应答
```

公共门面还提供以下方法：

| 方法 | 说明 |
| --- | --- |
| `Payment::create(array $config, string $defaultAccount = 'default')` | 使用数组配置创建门面 |
| `account(string $name)` | 切换账号组并返回新的门面实例 |
| `setHttpClient(HttpClientInterface $httpClient)` | 注入所有模块共用的 HTTP Client 实例 |

## 版本与路由规则

- 普通商户能力读取当前账号组的 `payment` 配置。
- 服务商、平台收付通和子商户进件读取当前账号组的 `partner` 配置。
- 商家转账到零钱复用 `payment` 配置，不需要独立的转账配置。
- 下单 DTO 不区分 V2/V3，扩展包根据相应配置中的 `version` 创建版本 Client。
- 合单 DTO 通过 `pay_mode` 明确选择 `payment` 或 `partner` 配置。
- 平台收付通退款和分账必须由调用方显式选择对应入口，不根据 DTO 字段自动推断。
- 不受当前 API 版本支持的方法会抛出 `UnsupportedModeException`。

## 普通商户支付

入口：`$payment->payment()`

主要 DTO：

- `Qinii\WechatPayment\Payment\PaymentDto`
- `Qinii\WechatPayment\Payment\PaymentRefundDto`
- `Qinii\WechatPayment\Refund\AbnormalRefundDto`

### 支付场景

| 支付场景 | `pay_type` | API V2 | API V3 |
| --- | --- | --- | --- |
| 公众号 JSAPI | `PayType::OFFICIAL` | 支持 | 支持 |
| 小程序 | `PayType::MINI_PROGRAM` | 支持 | 支持 |
| APP | `PayType::APP` | 支持 | 支持 |
| H5 | `PayType::H5` | 支持 | 支持 |
| Native | `PayType::NATIVE` | 支持 | 支持 |
| 付款码 | `PayType::MICROPAY` | 支持 | 不支持 |

JSAPI 和小程序下单返回前端调起参数；APP 返回 APP SDK 调起参数；H5 和 Native 返回微信支付接口响应中的支付地址。

### 公开方法

| 方法 | V2 | V3 | 说明 |
| --- | --- | --- | --- |
| `create(PaymentDto $dto)` | 支持 | 支持 | 根据 `pay_type` 发起支付 |
| `pay(PaymentDto $dto)` | 支持 | 支持 | `create()` 的兼容别名 |
| `queryByOutTradeNo(string $outTradeNo, ?string $payType = null)` | 支持 | 支持 | 按商户订单号查询 |
| `queryByTransactionId(string $transactionId, ?string $payType = null)` | 支持 | 支持 | 按微信支付订单号查询 |
| `close(string $outTradeNo, ?string $payType = null)` | 支持 | 支持 | 关闭订单 |
| `queryMicropay(string $outTradeNo)` | 支持 | 不支持 | 查询付款码订单 |
| `reverseMicropay(string $outTradeNo)` | 支持 | 不支持 | 撤销付款码订单 |
| `refund(PaymentRefundDto $dto)` | 支持 | 支持 | 申请普通退款 |
| `queryRefund(string $outRefundNo)` | 支持 | 支持 | 按商户退款单号查询 |
| `abnormalRefund(AbnormalRefundDto $dto)` | 不支持 | 支持 | 处理银行卡异常退款 |

API V2 查单和关单需要使用原下单 AppID。非默认应用下单时，应在查询和关单方法的 `$payType` 参数中传入原支付类型。

## 服务商支付

入口：`$payment->partner()`

主要 DTO：

- `Qinii\WechatPayment\Partner\PartnerDto`
- `Qinii\WechatPayment\Partner\PartnerRefundDto`
- `Qinii\WechatPayment\Refund\AbnormalRefundDto`

### 支付场景

| 支付场景 | API V2 | API V3 |
| --- | --- | --- |
| 公众号 JSAPI | 支持 | 支持 |
| 小程序 | 支持 | 支持 |
| APP | 支持 | 支持 |
| H5 | 支持 | 支持 |
| Native | 支持 | 支持 |
| 付款码 | 支持 | 不支持 |

### 支付与订单方法

| 方法 | V2 | V3 | 说明 |
| --- | --- | --- | --- |
| `create(PartnerDto $dto)` | 支持 | 支持 | 发起服务商支付 |
| `pay(PartnerDto $dto)` | 支持 | 支持 | `create()` 的兼容别名 |
| `queryByTransactionId(string $transactionId, string $subMchid, ?string $subAppid = null)` | 支持 | 支持 | 按微信支付订单号查询 |
| `queryByOutTradeNo(string $outTradeNo, string $subMchid, ?string $subAppid = null)` | 支持 | 支持 | 按商户订单号查询 |
| `close(string $outTradeNo, string $subMchid, ?string $subAppid = null)` | 支持 | 支持 | 关闭订单 |
| `queryMicropay(string $outTradeNo, string $subMchid, ?string $subAppid = null)` | 支持 | 不支持 | 查询付款码订单 |
| `reverseMicropay(string $outTradeNo, string $subMchid, ?string $subAppid = null)` | 支持 | 不支持 | 撤销付款码订单 |
| `authCodeToOpenid(string $authCode, string $subMchid, ?string $subAppid = null)` | 支持 | 不支持 | 付款码查询 OpenID |
| `shortUrl(string $longUrl, string $subMchid, ?string $subAppid = null)` | 支持 | 不支持 | Native 长链接转短链接 |
| `reportTransaction(array $report, string $subMchid, ?string $subAppid = null)` | 支持 | 不支持 | 上报接口耗时和结果 |

服务商配置中的 `app_id`、`mch_id` 会在 V3 请求中映射为 `sp_appid`、`sp_mchid`。`PartnerDto::$openid` 表示服务商 AppID 下的 OpenID；使用子商户 AppID 下的 OpenID 时，传入 `sub_appid` 和 `sub_openid`。

### 账单方法

| 方法 | V2 | V3 | 说明 |
| --- | --- | --- | --- |
| `applyTradeBill(string $billDate, ?string $subMchid = null, string $billType = 'ALL', ?string $tarType = null)` | 支持 | 支持 | 申请交易账单 |
| `applyFundFlowBill(string $billDate, string $accountType = 'BASIC', ?string $tarType = null)` | 支持 | 支持 | 申请资金账单 |
| `downloadBill(string $downloadUrl)` | 不适用 | 支持 | 下载 V3 临时账单地址中的内容 |

V2 和 V3 的账单返回值不同：

```php
// V2：申请方法直接返回 CSV 或 GZIP 原始字符串。
$contents = $payment->partner()->applyTradeBill('2026-09-01');

// V3：先返回包含 download_url 的数组，再下载原始内容。
$bill = $payment->partner()->applyTradeBill('2026-09-01');
$contents = $payment->partner()->downloadBill($bill['download_url']);
```

### 退款方法

| 方法 | V2 | V3 | 说明 |
| --- | --- | --- | --- |
| `refund(PartnerRefundDto $dto)` | 支持 | 支持 | 服务商普通退款 |
| `queryRefund(string $outRefundNo, string $subMchid)` | 支持 | 支持 | 查询服务商普通退款 |
| `ecommerceRefund(PartnerRefundDto $dto)` | 不支持 | 支持 | 电商收付通退款 |
| `queryEcommerceRefund(string $outRefundNo, string $subMchid)` | 不支持 | 支持 | 按商户退款单号查询收付通退款 |
| `queryEcommerceRefundById(string $refundId, string $subMchid)` | 不支持 | 支持 | 按微信退款单号查询收付通退款 |
| `abnormalRefund(AbnormalRefundDto $dto)` | 不支持 | 支持 | 服务商普通异常退款 |
| `abnormalEcommerceRefund(AbnormalRefundDto $dto)` | 不支持 | 支持 | 电商收付通异常退款 |

## 合单支付

入口：`$payment->combine()`

版本：仅 API V3。

主要 DTO：

- `Qinii\WechatPayment\Combine\CombineDto`
- `Qinii\WechatPayment\Combine\SubOrderDto`
- 普通合单退款使用 `PaymentRefundDto`
- 服务商或收付通合单退款使用 `PartnerRefundDto`
- 异常退款使用 `AbnormalRefundDto`

### 支付场景

| 支付场景 | 支持情况 |
| --- | --- |
| 公众号 JSAPI | 支持 |
| 小程序 | 支持 |
| APP | 支持 |
| H5 | 支持 |
| Native | 支持 |
| 付款码 | 不支持 |

普通商户与服务商使用同一组合并下单接口。通过 `CombineDto::$pay_mode` 选择 `PayMode::PAYMENT` 或 `PayMode::PARTNER`，扩展包再读取对应配置。

### 公开方法

| 方法 | 说明 |
| --- | --- |
| `create(CombineDto $dto)` | 根据 `pay_type` 发起合单支付 |
| `pay(CombineDto $dto)` | `create()` 的兼容别名 |
| `queryByOutTradeNo(string $combineOutTradeNo, string $mode = PayMode::PAYMENT)` | 按合单商户订单号查询 |
| `close(string $combineOutTradeNo, array $subOrders, string $mode = PayMode::PAYMENT)` | 关闭指定合单及商品单 |
| `refund(PaymentRefundDto|PartnerRefundDto $dto)` | 普通商户或服务商普通合单退款 |
| `ecommerceRefund(PartnerRefundDto $dto)` | 电商收付通合单退款 |
| `queryRefund(string $outRefundNo, ?string $subMchid = null)` | 查询普通合单退款 |
| `queryEcommerceRefund(string $outRefundNo, string $subMchid)` | 按商户退款单号查询收付通合单退款 |
| `queryEcommerceRefundById(string $refundId, string $subMchid)` | 按微信退款单号查询收付通合单退款 |
| `abnormalRefund(AbnormalRefundDto $dto)` | 普通合单异常退款 |
| `abnormalEcommerceRefund(AbnormalRefundDto $dto)` | 收付通合单异常退款 |

普通商户合单支持 2 至 10 笔商品单；服务商或平台合单支持 2 至 50 笔。扩展包 DTO 的上限为 50，普通商户场景的 10 笔限制会在 Builder 中进一步校验。

## 退款能力

退款没有单独门面，而是放在产生交易的业务入口中，调用方可以根据原交易协议明确选择：

| 原交易类型 | 退款入口 | DTO | 版本 |
| --- | --- | --- | --- |
| 普通商户支付 | `$payment->payment()->refund()` | `PaymentRefundDto` | V2 / V3 |
| 服务商普通支付 | `$payment->partner()->refund()` | `PartnerRefundDto` | V2 / V3 |
| 普通商户合单 | `$payment->combine()->refund()` | `PaymentRefundDto` | V3 |
| 服务商普通合单 | `$payment->combine()->refund()` | `PartnerRefundDto` | V3 |
| 电商收付通支付 | `$payment->partner()->ecommerceRefund()` | `PartnerRefundDto` | V3 |
| 电商收付通合单 | `$payment->combine()->ecommerceRefund()` | `PartnerRefundDto` | V3 |

`PaymentRefundDto` 和 `PartnerRefundDto` 均支持使用 `transaction_id` 或 `out_trade_no` 标识原订单。金额单位为分。V3 银行卡退款失败后的异常退款使用 `AbnormalRefundDto` 和各业务入口的 `abnormalRefund()` 或 `abnormalEcommerceRefund()`。

## 商家转账到零钱

入口：`$payment->transfer()`

版本：仅 API V3，读取普通商户 `payment` 配置。

未单独设置 `payment.app_id` 时，默认使用 `payment.default_application` 对应的 AppID；`$payment->transfer()->application('mini_program')` 可为当前账号组的小程序 OpenID 选择 `mini_program.app_id`。也支持 `official_account` 和 `app` 等已有应用节点。显式选择会覆盖 `payment.app_id`，返回独立服务；后续转账、授权和查询均使用所选应用，商户凭证仍来自 `payment`。OpenID 必须属于所选 AppID。

主要 DTO：

- `Qinii\WechatPayment\Transfer\TransferDto`：普通转账
- `Qinii\WechatPayment\Transfer\AuthorizationDto`：免确认收款授权流程

### 普通转账与电子回单

| 方法 | 说明 |
| --- | --- |
| `create(TransferDto $dto)` | 发起普通转账 |
| `transfer(TransferDto $dto)` | `create()` 的兼容入口 |
| `cancel(string $outBillNo)` | 按商户单号撤销转账 |
| `queryByOutBillNo(string $outBillNo)` | 按商户单号查询转账 |
| `queryByTransferBillNo(string $transferBillNo)` | 按微信转账单号查询 |
| `applyReceiptByOutBillNo(string $outBillNo)` | 按商户单号申请电子回单 |
| `queryReceiptByOutBillNo(string $outBillNo)` | 按商户单号查询电子回单 |
| `applyReceiptByTransferBillNo(string $transferBillNo)` | 按微信转账单号申请电子回单 |
| `queryReceiptByTransferBillNo(string $transferBillNo)` | 按微信转账单号查询电子回单 |

### 免确认收款授权

| 方法 | 说明 |
| --- | --- |
| `preTransferWithAuthorization(AuthorizationDto $dto)` | 发起转账并进入授权流程 |
| `applyAuthorization(AuthorizationDto $dto)` | 发起免确认收款授权 |
| `queryAuthorization(string $outAuthorizationNo)` | 查询授权结果 |
| `transferAfterAuthorization(AuthorizationDto $dto)` | 使用已取得的授权转账 |
| `closeAuthorization(string $outAuthorizationNo)` | 解除免确认收款授权 |

每个转账方法会按自身接口校验参数，不要求所有转账接口共用同一组必填字段。

## 分账

分账入口需要继续选择协议：

```php
$payment->profitSharing()->payment();   // 普通商户分账
$payment->profitSharing()->partner();   // 服务商分账
$payment->profitSharing()->ecommerce(); // 电商收付通分账
```

主要 DTO：

- `Qinii\WechatPayment\ProfitSharing\ProfitSharingDto`
- `Qinii\WechatPayment\ProfitSharing\ReceiverDto`
- `Qinii\WechatPayment\ProfitSharing\ReturnDto`

### 协议支持

| 协议 | API V2 | API V3 |
| --- | --- | --- |
| 普通商户分账 | 支持 | 支持 |
| 服务商分账 | 支持 | 支持 |
| 电商收付通分账 | 不支持 | 支持 |

### 公开方法

三个入口返回统一的 `ClientInterface`，公开方法如下：

| 方法 | 说明 |
| --- | --- |
| `create(ProfitSharingDto $dto)` | 请求分账 |
| `query(ProfitSharingDto $dto)` | 查询分账结果 |
| `finish(ProfitSharingDto $dto)` | 完结分账并解冻剩余资金 |
| `queryAmounts(ProfitSharingDto $dto)` | 查询订单剩余待分金额 |
| `createReturn(ReturnDto $dto)` | 请求分账回退 |
| `queryReturn(ReturnDto $dto)` | 查询分账回退结果 |
| `addReceiver(ReceiverDto $dto, string $subMchid = '', string $subAppid = '', string $brandMchId = '')` | 添加分账接收方 |
| `deleteReceiver(ReceiverDto $dto, string $subMchid = '', string $subAppid = '', string $brandMchId = '')` | 删除分账接收方 |
| `queryMaxRatio(string $subMchid, string $brandMchId = '')` | 查询服务商子商户最大分账比例 |
| `requestBill(string $billDate, string $tarType = 'GZIP', string $subMchid = '')` | 申请 V3 分账账单 |

限制：

- API V2 不支持 `requestBill()`。
- `queryMaxRatio()` 是服务商子商户能力，不用于普通商户分账。
- 电商收付通分账不提供 `queryMaxRatio()` 和 `requestBill()`，调用时会抛出 `UnsupportedModeException`。

## 子商户进件与管理

入口：`$payment->merchant()`

版本：仅 API V3，读取服务商 `partner` 配置。

主要 DTO：

- `Qinii\WechatPayment\Merchant\SpecialApplymentDto`
- `Qinii\WechatPayment\Merchant\EcommerceApplymentDto`
- `Qinii\WechatPayment\Merchant\SettlementDto`

### 公开方法

| 方法 | 说明 |
| --- | --- |
| `applySpecial(SpecialApplymentDto $dto)` | 提交服务商特约商户入驻申请 |
| `applyEcommerce(EcommerceApplymentDto $dto)` | 提交电商收付通二级商户入驻申请 |
| `querySpecialByApplymentId(int|string $applymentId)` | 按申请单号查询特约商户申请 |
| `querySpecialByBusinessCode(string $businessCode)` | 按业务申请编号查询特约商户申请 |
| `queryEcommerceByApplymentId(int|string $applymentId)` | 按申请单号查询二级商户申请 |
| `queryEcommerceByOutRequestNo(string $outRequestNo)` | 按业务申请编号查询二级商户申请 |
| `modifySettlement(SettlementDto $dto)` | 修改结算账户 |
| `querySettlement(string $subMchid, string $accountNumberRule = 'ACCOUNT_NUMBER_RULE_MASK_V1')` | 查询当前结算账户 |
| `querySettlementApplication(string $subMchid, string $applicationNo, string $accountNumberRule = 'ACCOUNT_NUMBER_RULE_MASK_V1')` | 查询结算账户修改申请 |
| `banks(int|string $bankType, int $offset = 0, int $limit = 100)` | 查询个人或企业可用的银行列表，`0/1` 兼容旧项目参数 |
| `branches(string $bankAliasCode, string $cityCode, int $offset = 0, int $limit = 100)` | 查询指定银行和城市下的支行列表 |
| `areas(?string $provinceCode = null)` | 查询省份列表，或查询指定省份下的城市列表 |
| `upload(string $filePath, ?string $filename = null)` | 上传进件图片或 PDF |
| `uploadVideo(string $filePath, ?string $filename = null)` | 上传特约商户进件视频 |

进件与结算账户中的敏感字段传入明文即可，V3 Client 会使用配置中的微信支付公钥或平台证书加密。媒体上传由 EasyWeChat 负责 multipart 组包和请求签名。

## 回调通知

入口：`$payment->notify()`

| 方法 | 版本 | 说明 |
| --- | --- | --- |
| `payment(ServerRequestInterface $request, string $mode = PayMode::PAYMENT)` | V2 / V3 | 支付结果通知 |
| `refund(ServerRequestInterface $request, string $mode = PayMode::PAYMENT)` | V2 / V3 | 退款结果通知 |
| `transfer(ServerRequestInterface $request)` | V3 | 商家转账通知 |
| `profitSharing(ServerRequestInterface $request, string $mode = PayMode::PAYMENT)` | V2 / V3 | 分账结果通知 |

服务商支付、退款和分账通知必须将第二个参数设为 `PayMode::PARTNER`。转账通知固定读取普通商户 `payment` 凭证；通知验签不依赖发起转账的 AppID，业务端应核对通知数据与原转账记录。

通知处理能力：

- V2 XML 通知签名校验。
- V2 退款通知 `req_info` 解密。
- V3 `Wechatpay-*` 请求头验签和 `resource` 解密。
- 自动识别通知协议，并校验其与当前配置的 `version` 一致。
- 统一生成 V2 XML 或 V3 JSON 成功、失败应答。

`Notification` 公开方法：

| 方法 | 说明 |
| --- | --- |
| `message()` | 返回 EasyWeChat 解密后的原始消息对象 |
| `data()` | 返回解密后的业务数组 |
| `version()` | 返回 `v2` 或 `v3` |
| `success()` | 生成微信要求的成功 PSR-7 响应 |
| `fail(string $message = '处理失败')` | 生成失败 PSR-7 响应，使微信按规则重试 |

## 返回值与异常

- 常规 API 方法返回 `array<string, mixed>`。
- 服务商 V2 账单申请直接返回原始字符串；V3 账单申请返回数组，账单下载返回原始字符串。
- 通知入口返回 `Qinii\WechatPayment\Notify\Notification`。
- 配置和证书错误抛出 `InvalidConfigException`。
- 当前版本不支持的能力抛出 `UnsupportedModeException`。
- DTO 校验、请求、微信业务错误、验签或解密错误抛出 `PaymentException`。

## 尚未覆盖的边界

本清单只描述当前源码已提供的公开入口。微信支付还有其他产品和运营接口未在此包中统一封装，例如部分营销、资金管理及新增行业能力。使用者可直接通过 EasyWeChat 调用未封装接口；扩展包不会根据商户是否开通某项产品自动替使用者选择业务协议。

## 验收建议

协议测试只能验证路由、参数结构、客户端调起参数和通知处理流程。接入真实业务时，建议按实际启用的商户模式逐项验收：

1. 下单与客户端调起。
2. 支付成功通知、重复通知和主动查单。
3. 超时关单，以及付款码 `USERPAYING`、`SYSTEMERROR`、网络中断后的查单和撤销。
4. 全额退款、部分退款、退款查询和退款通知。
5. 商家转账、授权、电子回单及通知。
6. 分账、分账回退、完结和通知。
7. 子商户进件、敏感字段加密、媒体上传和状态查询。
