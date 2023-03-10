<?php

namespace think\admin\model;

use think\admin\Model;

/**
 * 角色数据模型
 * Class SysRole
 * @package think\admin\model
 */
class SysRole extends Model
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