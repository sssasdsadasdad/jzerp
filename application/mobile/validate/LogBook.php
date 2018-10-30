<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DolphinPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2017 河源市卓锐科技有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://dolphinphp.com
// +----------------------------------------------------------------------
// | 开源协议 ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------

namespace app\mobile\validate;

use think\Validate;

/**
 * 日志证器
 * @package app\cms\validate
 * @author 黄远东 <641435071@qq.com>
 */
class LogBook extends Validate
{
    // 定义验证规则
    protected $rule = [
    	'type|日志类型' => 'require',
    	'title|日志标题' => 'require',
    	'info|日志详情' => 'require',
    	'daily_time|日志时间' => 'require',
    	'uid' => 'require'
    ];


}
