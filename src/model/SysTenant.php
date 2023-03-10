<?php

namespace think\admin\model;

use think\admin\Model;

/**
 * 租户数据模型
 * Class SysTenant
 * @package think\admin\model
 */
class SysTenant extends Model
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