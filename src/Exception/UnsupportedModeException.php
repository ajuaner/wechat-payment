<?php
declare(strict_types=1);

namespace Qinii\WechatPayment\Exception;

use Qinii\WechatCore\Exception\WechatBusinessException;

/**
 * 支付模式异常
 */
class UnsupportedModeException extends WechatBusinessException
{

}