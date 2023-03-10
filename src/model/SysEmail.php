<?php

namespace think\admin\model;

use think\admin\Model;

/**
 * 邮件数据模型
 * Class SyEmail
 * @package think\admin\model
 */
class SysEmail extends Model
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