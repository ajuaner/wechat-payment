<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Endpoints;

final class TransferEndpoints extends Endpoints
{
    //发起转账
    public const V3_TRANSFER_BILL = '/v3/fund-app/mch-transfer/transfer-bills';
    //撤销转账
    public const V3_TRANSFER_BILL_CANCEL = '/v3/fund-app/mch-transfer/transfer-bills/out-bill-no/{out_bill_no}/cancel';
    //商户单号查询转账单
    public const V3_TRANSFER_BILL_QUERY = '/v3/fund-app/mch-transfer/transfer-bills/out-bill-no/{out_bill_no}';
    //微信单号查询转账单
    public const V3_TRANSFER_BILL_QUERY_BY_TRANSFER_BILL_NO = '/v3/fund-app/mch-transfer/transfer-bills/transfer-bill-no/{transfer_bill_no}';

    //商户单号申请电子回单
    public const V3_TRANSFER_ELECSIGN = '/v3/fund-app/mch-transfer/elecsign/out-bill-no';
    //商户单号查询电子回单
    public const V3_TRANSFER_ELECSIGN_QUERY = '/v3/fund-app/mch-transfer/elecsign/out-bill-no/{out_bill_no}';
    //微信单号申请电子回单
    public const V3_TRANSFER_ELECSIGN_BY_TRANSFER_BILL_NO = '/v3/fund-app/mch-transfer/elecsign/transfer-bill-no';
    //微信单号查询电子回单
    public const V3_TRANSFER_ELECSIGN_QUERY_BY_TRANSFER_BILL_NO = '/v3/fund-app/mch-transfer/elecsign/transfer-bill-no/{transfer_bill_no}';

    //发起转账并完成免确认收款授权
    public const V3_TRANSFER_PRE_TRANSFER_WITH_AUTHORIZATION = '/v3/fund-app/mch-transfer/transfer-bills/pre-transfer-with-authorization';
    //发起免确认收款授权
    public const V3_TRANSFER_USER_CONFIRM_AUTHORIZATION = '/v3/fund-app/mch-transfer/user-confirm-authorization';
    //商户单号查询授权结果
    public const V3_TRANSFER_USER_CONFIRM_AUTHORIZATION_QUERY_BY_TRANSFER_BILL_NO = '/v3/fund-app/mch-transfer/user-confirm-authorization/out-authorization-no/{out_authorization_no}';
    //用户授权后转账
    public const V3_TRANSFER_USER_CONFIRM_TRANSFER_BILL = '/v3/fund-app/mch-transfer/transfer-bills/transfer';
    //解除免确认收款授权
    public const V3_TRANSFER_USER_CONFIRM_AUTHORIZATION_CLOSE = '/v3/fund-app/mch-transfer/user-confirm-authorization/out-authorization-no/{out_authorization_no}/close';

}       