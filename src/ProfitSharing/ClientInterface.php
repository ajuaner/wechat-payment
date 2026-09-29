<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\ProfitSharing;

interface ClientInterface
{
    /** 请求分账。 */
    public function create(ProfitSharingDto $dto): array;

    /** 查询分账结果。 */
    public function query(ProfitSharingDto $dto): array;

    /** 完结分账并解冻剩余资金。 */
    public function finish(ProfitSharingDto $dto): array;

    /** 查询订单剩余待分金额。 */
    public function queryAmounts(ProfitSharingDto $dto): array;

    /** 请求分账回退。 */
    public function createReturn(ReturnDto $dto): array;

    /** 查询分账回退结果。 */
    public function queryReturn(ReturnDto $dto): array;

    /** 添加分账接收方。 */
    public function addReceiver(
        ReceiverDto $dto,
        string $subMchid = '',
        string $subAppid = '',
        string $brandMchId = '',
    ): array;

    /** 删除分账接收方。 */
    public function deleteReceiver(
        ReceiverDto $dto,
        string $subMchid = '',
        string $subAppid = '',
        string $brandMchId = '',
    ): array;

    /** 查询服务商子商户的最大分账比例。 */
    public function queryMaxRatio(
        string $subMchid,
        string $brandMchId = '',
    ): array;

    /** 申请 V3 分账账单。 */
    public function requestBill(
        string $billDate,
        string $tarType = 'GZIP',
        string $subMchid = '',
    ): array;
}
