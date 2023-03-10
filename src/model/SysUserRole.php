<?php

namespace think\admin\model;

use think\admin\Model;

/**
 * 用户与角色数据模型
 * Class SysUserRole
 * @package think\admin\model
 */
class SysUserRole extends Model
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