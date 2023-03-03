<?php
declare (strict_types=1);

namespace think\admin\service;

use think\admin\Library;
use think\admin\model\SystemUserToken;
use think\admin\model\SysUserRole;
use think\admin\model\SysRoleDept;
use think\admin\model\SystemUser;
use think\admin\model\SysDept;
use think\admin\model\SysRole;
use think\admin\Service;


/**
 * 数据权限服务
 * Class DataScopeService
 * @package app\admin\service
 */
class DataScopeService extends Service
{

    /**
     * 获取用户ID集合
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function setDataScope(): array
    {
        $user = Library::$sapp->session->get('user', []);
        $role_ids = SysUserRole::mk()->where(['user_id' => $user['id']])->column('role_id');
        $roles = SysRole::mk()->whereIn('id',$role_ids)->select()->toArray();
        return $this->getDeptUserIdsBy($user['id'], $roles, $role_ids);
    }

    /**
     * 数据范围查询
     * @param int $userid
     * @param array $roles
     * @param array $role_ids
     * @return $this|void
     */
    public function dataRange(int $userid, array $roles = [], array $role_ids = [])
    {
        if (AdminService::isSuper()) {
            return $this;
        }

        $userIds =  $this->getDeptUserIdsBy($userid, $roles, $role_ids);

        if (empty($userIds)) {
            return $this;
        }
    }

    /**
     * 获取部门IDs
     * @param int $userid
     * @param array $roles
     * @param array $role_ids
     * @return array
     */
    public function getDeptUserIdsBy(int $userid, array $roles = [], array $role_ids = []):array
    {
        $userIds = [];
        $isAll = false;
        $user = SystemUser::mk()->where(['id' => $userid,'is_deleted' => 0])->findOrEmpty();
        if (empty($roles)) {
            return [0, '账号未分配角色，请让管理员分配相应角色再操作。', 0, 0];
        }
        foreach ($roles as $role) {
            switch ($role['data_scope']) {
                case 0:
                    //全部
                    $isAll = true;
                    break;
                case 1:
                    //自定义
                    $dept_ids = SysRoleDept::mk()->whereIn('role_id', $role_ids)->column('dept_id');
                    $userIds = array_merge($userIds, $this->getUserIdsByDeptId($dept_ids));
                    break;
                case 2:
                    //本部门
                    $userIds[] = array_merge([$user['id']], $this->getUserIdsByDeptId([$user['dept_id']]));
                    break;
                case 3:
                    // 本部门及以下
                    $dept_ids = SysDept::mk()->where(['parent_id'=> $user['dept_id']])->column('id');
                    array_unshift($dept_ids,$user['dept_id']);
                    $userIds = $this->getUserIdsByDeptId($dept_ids);
                    break;
                case 4:
                    //本人
                    $userIds[] = $user['id'];
                    break;
                default:
                    break;
            }

            // 如果有全部数据 直接跳出
            if ($isAll) {
                break;
            }
        }
        return $userIds;
    }

    /**
     * 获取UserID
     * @param array $id
     * @return array
     */
    protected function getUserIdsByDeptId(array $id):array
    {
        return SystemUser::mk()->whereIn('dept_id', $id)->column('id');
    }
}