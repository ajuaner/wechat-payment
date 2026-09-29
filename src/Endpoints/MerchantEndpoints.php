<?php

declare(strict_types=1);

namespace Qinii\WechatPayment\Endpoints;

/** 子商户进件、结算账户和媒体上传接口。 */
final class MerchantEndpoints extends Endpoints
{
    /** 普通服务商特约商户：提交申请单。 */
    public const V3_APPLYMENT4SUB_APPLYMENT = '/v3/applyment4sub/applyment/';

    /** 普通服务商特约商户：按申请单号查询申请状态。 */
    public const V3_APPLYMENT4SUB_APPLYMENT_ID = '/v3/applyment4sub/applyment/applyment_id/{applyment_id}';

    /** 普通服务商特约商户：按业务申请编号查询申请状态。 */
    public const V3_APPLYMENT4SUB_BUSINESS_CODE = '/v3/applyment4sub/applyment/business_code/{business_code}';

    /** 特约商户/二级商户：修改结算账户。 */
    public const V3_APPLYMENT4SUB_MODIFY_SETTLEMENT = '/v3/apply4sub/sub_merchants/{sub_mchid}/modify-settlement';

    /** 特约商户/二级商户：查询当前结算账户。 */
    public const V3_APPLYMENT4SUB_SETTLEMENT = '/v3/apply4sub/sub_merchants/{sub_mchid}/settlement';

    /** 特约商户/二级商户：查询结算账户修改申请状态。 */
    public const V3_APPLYMENT4SUB_APPLICATION = '/v3/apply4sub/sub_merchants/{sub_mchid}/application/{application_no}';

    /** 查询支持个人或企业业务的银行列表。 */
    public const V3_CAPITALLHH_BANKS = '/v3/capital/capitallhh/banks/{bank_type}';

    /** 查询指定银行和城市下的支行列表。 */
    public const V3_CAPITALLHH_BRANCHES = '/v3/capital/capitallhh/banks/{bank_alias_code}/branches';

    /** 查询省份列表或指定省份下的城市列表。 */
    public const V3_CAPITALLHH_AREAS_PROVINCES = '/v3/capital/capitallhh/areas/provinces';

    /** 上传图片或 PDF 文件。 */
    public const V3_MERCHANT_MEDIA_UPLOAD = '/v3/merchant/media/upload';

    /** 上传特约商户进件视频。 */
    public const V3_MERCHANT_MEDIA_VIDEO_UPLOAD = '/v3/merchant/media/video_upload';

    /** 电商收付通二级商户：提交申请单。 */
    public const V3_ECOMMERCE_APPLYMENT_APPLYMENT = '/v3/ecommerce/applyments/';

    /** 电商收付通二级商户：按业务申请编号查询申请状态。 */
    public const V3_ECOMMERCE_APPLYMENT_OUT_REQUEST_NO = '/v3/ecommerce/applyments/out-request-no/{out_request_no}';

    /** 电商收付通二级商户：按申请单号查询申请状态。 */
    public const V3_ECOMMERCE_APPLYMENT_ID = '/v3/ecommerce/applyments/{applyment_id}';

    /** @deprecated 使用 V3_APPLYMENT4SUB_MODIFY_SETTLEMENT。 */
    public const V3_ECOMMERCE_APPLYMENT_MODIFY_SETTLEMENT = '/v3/apply4sub/sub_merchants/{sub_mchid}/modify-settlement';

    /** @deprecated 使用 V3_APPLYMENT4SUB_SETTLEMENT。 */
    public const V3_ECOMMERCE_APPLYMENT_SETTLEMENT = '/v3/apply4sub/sub_merchants/{sub_mchid}/settlement';

    /** @deprecated 使用 V3_APPLYMENT4SUB_APPLICATION。 */
    public const V3_ECOMMERCE_APPLYMENT_APPLICATION = '/v3/apply4sub/sub_merchants/{sub_mchid}/application/{application_no}';

}
