<?php

namespace think\admin\model;

use think\admin\Model;

/**
 * 内容安全数据模型
 * Class SysGreen
 * @package think\admin\model
 */
class SysGreen extends Model
{
    /**
     * 格式化创建时间
     * @param string $value
     * @return string
     */
    public function getCreateAtAttr(string $value): string
    {
        return format_datetime($value);
    }
}