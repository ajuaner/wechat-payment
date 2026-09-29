<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Endpoints;

final class PaymentEndpoints extends Endpoints
{

    // 获取平台证书
    public const CERTIFICATES_ENDPOINT = '/v3/certificates';

    /**
     *  普通商户支付接口
     */
    //v2 支付相关接口
    public const V2_UNIFIED_ORDER = '/pay/unifiedorder';
    //扫码付款
    public const V2_MICROPAY = '/pay/micropay';
    // 查询订单（普通商户、服务商和付款码支付共用）
    public const V2_ORDER_QUERY = '/pay/orderquery';
    // 关闭订单（普通商户和服务商共用）
    public const V2_CLOSE_ORDER = '/pay/closeorder';
    // 撤销付款码支付订单
    public const V2_REVERSE_ORDER = '/secapi/pay/reverse';
    // 下载交易账单
    public const V2_TRADE_BILL = '/pay/downloadbill';
    // 下载资金账单
    public const V2_FUND_FLOW_BILL = '/pay/downloadfundflow';
    // 上报接口耗时和返回结果
    public const V2_TRANSACTION_REPORT = '/payitil/report';
    // 通过付款码查询用户 OpenID
    public const V2_AUTH_CODE_TO_OPENID = '/tools/authcodetoopenid';
    // 将 Native 支付长链接转换为短链接
    public const V2_SHORT_URL = '/tools/shorturl';


    // v3 支付相关接口
    //JSAPI/小程序下单
    public const V3_TRANSACTION_JSAPI = '/v3/pay/transactions/jsapi';
    //H5下单
    public const V3_TRANSACTION_H5 = '/v3/pay/transactions/h5';
    //Native下单
    public const V3_TRANSACTION_NATIVE = '/v3/pay/transactions/native';
    //app下单
    public const V3_TRANSACTION_APP = '/v3/pay/transactions/app';
    // 按微信支付订单号查询订单
    public const V3_TRANSACTION_ID = '/v3/pay/transactions/id/{transaction_id}';
    // 按商户订单号查询订单
    public const V3_TRANSACTION_OUT_TRADE_NO = '/v3/pay/transactions/out-trade-no/{out_trade_no}';
    // 按商户订单号关闭订单
    public const V3_TRANSACTION_CLOSE = '/v3/pay/transactions/out-trade-no/{out_trade_no}/close';


    /**
     * 服务商支付接口
     */
    //  JSAPI/小程序下单
    public const V3_PARTNER_TRANSACTION_JSAPI = '/v3/pay/partner/transactions/jsapi';
    //  H5下单
    public const V3_PARTNER_TRANSACTION_H5 = '/v3/pay/partner/transactions/h5';
    //  Native下单
    public const V3_PARTNER_TRANSACTION_NATIVE = '/v3/pay/partner/transactions/native';
    //  app下单
    public const V3_PARTNER_TRANSACTION_APP = '/v3/pay/partner/transactions/app';
    //  按微信支付订单号查询订单
    public const V3_PARTNER_TRANSACTION_ID = '/v3/pay/partner/transactions/id/{transaction_id}';
    //  按商户订单号查询订单
    public const V3_PARTNER_TRANSACTION_OUT_TRADE_NO = '/v3/pay/partner/transactions/out-trade-no/{out_trade_no}';
    //  按商户订单号关闭订单
    public const V3_PARTNER_TRANSACTION_CLOSE = '/v3/pay/partner/transactions/out-trade-no/{out_trade_no}/close';

    /**
     * 服务商账单接口
     */
    // 申请交易账单
    public const V3_TRADE_BILL = '/v3/bill/tradebill';
    // 申请资金账单
    public const V3_FUND_FLOW_BILL = '/v3/bill/fundflowbill';


    /**
     * 合单支付 -- 平台收付通
     */
    // jsapi 合单支付
    public const V3_COMBINE_TRANSACTION_JSAPI = '/v3/combine-transactions/jsapi';
    // app 合单支付
    public const V3_COMBINE_TRANSACTION_APP = '/v3/combine-transactions/app';
    // h5 合单支付
    public const V3_COMBINE_TRANSACTION_H5 = '/v3/combine-transactions/h5';
    // native 合单支付
    public const V3_COMBINE_TRANSACTION_NATIVE = '/v3/combine-transactions/native';
    // 按合单商户订单号查询合单订单
    public const V3_COMBINE_TRANSACTION_OUT_TRADE_NO = '/v3/combine-transactions/out-trade-no/{combine_out_trade_no}';
    // 按合单商户订单号关闭合单订单
    public const V3_COMBINE_TRANSACTION_CLOSE = '/v3/combine-transactions/out-trade-no/{combine_out_trade_no}/close';


    /**
     * 退款逻辑
     *  普通商户 和 服务商商户
     *  v2、v3 退款接口 一样
     *  合单支付 v3 接口退款也是 v3 退款接口
     *  平台收付通合单支付退款接口独立的，和普通商户退款接口不同
     */
    // 申请退款
    public const V2_TRANSACTION_REFUND = '/secapi/pay/refund';
    // 查询退款
    // 查询退款不要求双向证书，和申请退款的 secapi 地址不同。
    public const V2_TRANSACTION_REFUND_QUERY = '/pay/refundquery';

    // 申请退款
    public const V3_TRANSACTION_REFUND = '/v3/refund/domestic/refunds';
    // 查询退款
    public const V3_TRANSACTION_REFUND_QUERY = '/v3/refund/domestic/refunds/{out_refund_no}';
    // 发起异常退款
    public const V3_TRANSACTION_REFUND_EXCEPTION = '/v3/refund/domestic/refunds/{refund_id}/apply-abnormal-refund';


    /** 平台收付通退款接口（普通支付和合单支付共用） */
    //申请退款
    public const V3_TRANSACTION_REFUND_APPLY = '/v3/ecommerce/refunds/apply';
    //查询单笔退款（按微信支付退款单号）
    public const V3_TRANSACTION_REFUND_QUERY_ID = '/v3/ecommerce/refunds/id/{refund_id}';
    //查询单笔退款（按商户退款单号）
    public const V3_TRANSACTION_REFUND_QUERY_OUT_REFUND_NO = '/v3/ecommerce/refunds/out-refund-no/{out_refund_no}';
    //查询垫付回补结果
    public const V3_TRANSACTION_REFUND_RETURN_ADVANCE = '/v3/ecommerce/refunds/{refund_id}/return-advance';
    //垫付退款回补
    public const V3_TRANSACTION_REFUND_RETURN_ADVANCE_APPLY = '/v3/ecommerce/refunds/{refund_id}/return-advance';
    //发起异常退款
    public const V3_TRANSACTION_REFUND_EXCEPTION_APPLY = '/v3/ecommerce/refunds/{refund_id}/apply-abnormal-refund';





}
