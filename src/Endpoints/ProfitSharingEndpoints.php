<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Endpoints;

/** 微信支付分账接口地址。 */
final class ProfitSharingEndpoints extends Endpoints
{
    /** V2 请求单次分账。 */
    public const V2_SINGLE = '/secapi/pay/profitsharing';
    /** V2 请求多次分账。 */
    public const V2_MULTIPLE = '/secapi/pay/multiprofitsharing';
    /** V2 查询分账结果。 */
    public const V2_QUERY = '/pay/profitsharingquery';
    /** V2 添加分账接收方。 */
    public const V2_RECEIVER_ADD = '/pay/profitsharingaddreceiver';
    /** V2 删除分账接收方。 */
    public const V2_RECEIVER_DELETE = '/pay/profitsharingremovereceiver';
    /** V2 完结分账。 */
    public const V2_FINISH = '/secapi/pay/profitsharingfinish';
    /** V2 查询订单待分账金额。 */
    public const V2_AMOUNTS = '/pay/profitsharingorderamountquery';
    /** V2 请求分账回退。 */
    public const V2_RETURN = '/secapi/pay/profitsharingreturn';
    /** V2 查询分账回退结果。 */
    public const V2_RETURN_QUERY = '/pay/profitsharingreturnquery';
    /** V2 服务商查询最大分账比例。 */
    public const V2_MAX_RATIO = '/pay/profitsharingmerchantratioquery';

    /** V3 请求分账。 */
    public const V3_CREATE = '/v3/profitsharing/orders';
    /** V3 查询分账结果。 */
    public const V3_QUERY = '/v3/profitsharing/orders/{out_order_no}';
    /** V3 请求分账回退。 */
    public const V3_RETURN = '/v3/profitsharing/return-orders';
    /** V3 查询分账回退结果。 */
    public const V3_RETURN_QUERY = '/v3/profitsharing/return-orders/{out_return_no}';
    /** V3 解冻剩余资金。 */
    public const V3_FINISH = '/v3/profitsharing/orders/unfreeze';
    /** V3 查询剩余待分金额。 */
    public const V3_AMOUNTS = '/v3/profitsharing/transactions/{transaction_id}/amounts';
    /** V3 添加分账接收方。 */
    public const V3_RECEIVER_ADD = '/v3/profitsharing/receivers/add';
    /** V3 删除分账接收方。 */
    public const V3_RECEIVER_DELETE = '/v3/profitsharing/receivers/delete';
    /** V3 服务商查询最大分账比例。 */
    public const V3_MAX_RATIO = '/v3/profitsharing/merchant-configs/{sub_mchid}';
    /** V3 申请分账账单。 */
    public const V3_BILL = '/v3/profitsharing/bills';

    /** 收付通请求分账。 */
    public const ECOMMERCE_CREATE = '/v3/ecommerce/profitsharing/orders';
    /** 收付通查询分账结果。 */
    public const ECOMMERCE_QUERY = '/v3/ecommerce/profitsharing/orders';
    /** 收付通请求分账回退。 */
    public const ECOMMERCE_RETURN = '/v3/ecommerce/profitsharing/returnorders';
    /** 收付通查询分账回退结果。 */
    public const ECOMMERCE_RETURN_QUERY = '/v3/ecommerce/profitsharing/returnorders';
    /** 收付通解冻剩余资金。 */
    public const ECOMMERCE_FINISH = '/v3/ecommerce/profitsharing/finish-order';
    /** 收付通查询订单剩余待分金额。 */
    public const ECOMMERCE_AMOUNTS = '/v3/ecommerce/profitsharing/orders/{transaction_id}/amounts';
    /** 收付通添加分账接收方。 */
    public const ECOMMERCE_RECEIVER_ADD = '/v3/ecommerce/profitsharing/receivers/add';
    /** 收付通删除分账接收方。 */
    public const ECOMMERCE_RECEIVER_DELETE = '/v3/ecommerce/profitsharing/receivers/delete';

    /** 收付通请求补差。 */
    public const ECOMMERCE_SUBSIDIES_CREATE = '/v3/ecommerce/subsidies/create';
    /** 收付通请求补差回退。 */
    public const ECOMMERCE_SUBSIDIES_RETURN = '/v3/ecommerce/subsidies/return';
    /** 收付通取消补差。 */
    public const ECOMMERCE_SUBSIDIES_CANCEL = '/v3/ecommerce/subsidies/cancel';

    /** 禁止实例化接口常量类。 */
    private function __construct()
    {
    }
}
