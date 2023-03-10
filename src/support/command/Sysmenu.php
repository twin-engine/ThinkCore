<?php

declare (strict_types=1);

namespace think\admin\support\command;

use think\admin\Command;
use think\admin\extend\DataExtend;
use think\admin\model\SysMenu as MenuSys;

/**
 * 重置并清理系统菜单
 * Class Sysmenu
 * @package think\admin\support\command
 */
class Sysmenu extends Command
{
    /**
     * 指令任务配置
     */
    public function configure()
    {
        $this->setName('xadmin:sysmenu');
        $this->setDescription('Clean and Reset System Menu Data for DeAdmin');
    }

    /**
     * 任务执行入口
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function handle()
    {
        $query = MenuSys::mQuery()->where(['is_deleted' => 0]);
        $menus = $query->db()->order('sort desc,id asc')->select()->toArray();
        [$total, $count] = [count($menus), 0, $query->empty()];
        $this->setQueueMessage($total, 0, '开始重置系统菜单编号...');
        foreach (DataExtend::arr2tree($menus,'id','parent_id') as $sub1) {
            $pid1 = $this->write($sub1,0,'0');
            $this->setQueueMessage($total, ++$count, "重写1级菜单：{$sub1['name']}");
            if (!empty($sub1['sub'])) foreach ($sub1['sub'] as $sub2) {
                $pid2 = $this->write($sub2, $pid1, '0,'.$pid1);
                $this->setQueueMessage($total, ++$count, "重写2级菜单：-> {$sub2['name']}");
                if (!empty($sub2['sub'])) foreach ($sub2['sub'] as $sub3) {
                    $this->write($sub3, $pid2, '0,'.$pid1.','.$pid2);
                    $this->setQueueMessage($total, ++$count, "重写3级菜单：-> -> {$sub3['name']}");
                }
            }
        }
        $this->setQueueMessage($total, $count, "完成重置系统菜单编号！");
    }

    /**
     * 写入单项菜单数据
     * @param array $arr 单项菜单数据
     * @param mixed $pid 上级菜单编号
     * @param mixed $level 级别
     * @return int|string
     */
    private function write(array $arr, $pid = 0, $level = '0')
    {
        return MenuSys::mk()->insertGetId([
            'parent_id'    => $pid,
            'name'    => $arr['name'],
            'code'   => $arr['code'],
            'level'   => $level,
            'type'   => $arr['type'],
            'icon'  => $arr['icon'],
            'router' => $arr['router'],
            'component' => $arr['component'],
            'permission'    => $arr['permission'],
            'application'   => $arr['application'],
            'open_type'   => $arr['open_type'],
            'visible'  => $arr['visible'],
            'weight'    => $arr['weight'],
            'sort'   => $arr['sort'],
            'status'   => $arr['status'],
            'hide'  => $arr['hide'],
            'is_deleted'    => $arr['is_deleted'],
            'created_by'   => $arr['created_by'],
            'updated_by'   => $arr['updated_by'],
            'created_at'  => $arr['created_at'],
            'updated_at'   => $arr['updated_at'],
            'remark'  => $arr['remark'],
        ]);
    }
}