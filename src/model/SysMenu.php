<?php

namespace think\admin\model;

use think\admin\Model;

/**
 * 前分端分离菜单数据模型
 * Class SysMenu
 * @package think\admin\model
 */
class SysMenu extends Model
{
    /**
     * 日志名称
     * @var string
     */
    protected $oplogName = '系统菜单';

    /**
     * 日志类型
     * @var string
     */
    protected $oplogType = '系统菜单管理';

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