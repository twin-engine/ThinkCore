<?php

namespace think\admin\model;

use think\admin\Model;

/**
 * 岗位数据模型
 * Class SysDept
 * @package think\admin\model
 */
class SysDept extends Model
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