<?php

declare (strict_types=1);

namespace think\admin\service;

use think\admin\Exception;
use think\admin\extend\DataExtend;
use think\admin\Library;
use think\admin\model\SystemUser;
use think\admin\model\SysUserRole;
use think\admin\model\SysMenu;
use think\admin\model\SysRoleMenu;
use think\admin\Service;
use think\admin\extend\JwtExtend;
use think\helper\Str;

/**
 * 系统权限管理服务
 * Class AdminService
 * @package think\admin\service
 */
class AdminService extends Service
{
    /**
     * 是否已经登录
     * @return boolean
     */
    public static function isLogin(): bool
    {
        return static::getUserId() > 0;
    }

    /**
     * 是否为超级用户
     * @return boolean
     */
    public static function isSuper(): bool
    {
        return static::getUserName() === static::getSuperName() && static::getAdminType() === '100';
    }

    /**
     * 是否为租户管理员（租户创始人）
     * @return boolean
     */
    public static function isAdmin(): bool
    {
        return static::getAdminType() === '200';
    }

    /**
     * 获取超级用户账号
     * @return string
     */
    public static function getSuperName(): string
    {
        return Library::$sapp->config->get('app.super_user', 'superAdmin');
    }

    /**
     * 获取户类型值 超管100,租户创始人200,普通管理员300
     * @return string
     */
    public static function getAdminType(): string
    {
        return Library::$sapp->session->get('user.user_type', '');
    }


    /**
     * 获取后台用户ID:Jwt方式
     * 修改用户ID获取方法（前后端分离）
     * @return integer
     */
    public static function getUserId(): int
    {
        $token = Library::$sapp->request->header('Jwt-Token');
        if($token) $payloadData = JwtExtend::verifyToken($token);
        if(!empty($payloadData)){
            Library::$sapp->session->set('user', $payloadData['data']);
            return $payloadData['data']['id'];
        }
        return 0;
    }

    /**
     * 获取后台用户名称(Jwt)
     * 修改用户名获取方法（前后端分离）
     * @return string
     */
    public static function getUserName(): string
    {
        if(static::getUserId()>0){
            return Library::$sapp->session->get('user.username', '');
        }else{
            return '';
        }
    }


    /**
     * 获取租户ID
     * @return int
     */
    public static function getTenantId(): int
    {
        if(static::getUserId()>0){
            return Library::$sapp->session->get('user.tenant_id', '');
        }elseif($tenantId = Library::$sapp->request->header('TenantId')){
            return (int)$tenantId;
        }else{
            return 0;
        }
    }

    /**
     * 获取用户扩展数据
     * @param null|string $field
     * @param null|mixed $default
     * @return array|mixed
     */
    public static function getUserData(?string $field = null, $default = null)
    {
        $data = SystemService::getData('UserData_' . static::getUserId());
        return is_null($field) ? $data : ($data[$field] ?? $default);
    }

    /**
     * 设置用户扩展数据
     * @param array $data
     * @param boolean $replace
     * @return boolean|integer
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function setUserData(array $data, bool $replace = false)
    {
        $data = $replace ? $data : array_merge(static::getUserData(), $data);
        return SystemService::setData('UserData_' . static::getUserId(), $data);
    }

    /**
     * 获取用户主题名称
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getUserTheme(): string
    {
        $default = sysconf('base.site_theme|raw') ?: 'default';
        return static::getUserData('site_theme', $default);
    }

    /**
     * 设置用户主题名称
     * @param string $theme 主题名称
     * @return boolean|integer
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function setUserTheme(string $theme)
    {
        return static::setUserData(['site_theme' => $theme]);
    }

    /**
     * 检查指定节点授权
     * --- 需要读取缓存或扫描所有节点
     * @param null|string $node
     * @return boolean
     * @throws \ReflectionException
     */
    public static function check(?string $node = ''): bool
    {
        $methods = NodeService::getMethods();
        $current = NodeService::fullNode($node);
        // 兼容 windows 控制器不区分大小写的验证问题
        foreach ($methods as $key => $rule) {
            if (preg_match('#.*?/.*?_.*?#', $key)) {
                $attr = explode('/', $key);
                $attr[1] = strtr($attr[1], ['_' => '']);
                $methods[join('/', $attr)] = $rule;
            }
        }
        // 自定义权限
        if (function_exists('admin_check_filter')) {
            $nodes = Library::$sapp->session->get('user.nodes', []);
            return call_user_func('admin_check_filter', $current, $methods, $nodes);
        }
        // 超级用户权限
        if (static::isSuper()) return true;
        // 节点权限检查
        if (empty($methods[$current]['isauth'])) {
            return !(!empty($methods[$current]['islogin']) && !static::isLogin());
        } else {
            return in_array($current, Library::$sapp->session->get('nodes', []));//'user.nodes'
        }
    }

    /**
     * 获取授权节点列表
     * @param array $checkeds
     * @return array
     * @throws \ReflectionException
     */
    public static function getTree(array $checkeds = []): array
    {
        [$nodes, $pnodes, $methods] = [[], [], array_reverse(NodeService::getMethods())];
        foreach ($methods as $node => $method) {
            [$count, $pnode] = [substr_count($node, '/'), substr($node, 0, strripos($node, '/'))];
            if ($count === 2 && !empty($method['isauth'])) {
                in_array($pnode, $pnodes) or array_push($pnodes, $pnode);
                $nodes[$node] = ['node' => $node, 'title' => $method['title'], 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            } elseif ($count === 1 && in_array($pnode, $pnodes)) {
                $nodes[$node] = ['node' => $node, 'title' => $method['title'], 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            }
        }
        foreach (array_keys($nodes) as $key) foreach ($methods as $node => $method) if (stripos($key, $node . '/') !== false) {
            $pnode = substr($node, 0, strripos($node, '/'));
            $nodes[$node] = ['node' => $node, 'title' => $method['title'], 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            $nodes[$pnode] = ['node' => $pnode, 'title' => Str::studly($pnode), 'pnode' => '', 'checked' => in_array($pnode, $checkeds)];
        }
        return DataExtend::arr2tree(array_reverse($nodes), 'node', 'pnode', '_sub_');
    }


    /**
     * 初始化用户权限(前后端分离）需在用户登录时刷新权限
     * @param boolean $force 强刷权限
     * @return array
     */
    public static function apply(bool $force = false): array
    {
        if ($force) static::clear();
        if (($uuid = static::getUserId()) <= 0) return [];
        $user = SystemUser::mk()->where(['id' => $uuid])->findOrEmpty()->toArray();
        $aids = SysUserRole::mk()->whereIn('user_id', $uuid)->column('role_id');
        $menu_ids = SysRoleMenu::mk()->whereIn('role_id',$aids)->column('menu_id');
        if (!static::isSuper() && count($menu_ids) > 0) {
            $nodes = SysMenu::mk()->where(['status'=>0,'type'=>2])->whereIn('id', $menu_ids)->column('permission');
        }
        if(!empty($nodes)){
            foreach($nodes as $key=>&$v){
                $v = str_replace(':','/',$v);
            }
        }
        Library::$sapp->session->set('user', $user);
        $nodes = $nodes ?? [];
        Library::$sapp->session->set('nodes', $nodes);
        return $user;
    }

    /**
     * 清理节点缓存
     * @return bool
     */
    public static function clear(): bool
    {
        Library::$sapp->cache->delete('SystemAuthNode');
        return true;
    }

    /**
     * 静态方法兼容(停时)
     * @param string $method
     * @param array $arguments
     * @return bool
     * @throws \think\admin\Exception
     */
    public static function __callStatic(string $method, array $arguments)
    {
        if ($method === 'clearCache') {
            return static::clear();
        } else {
            throw new Exception("method not exists: AdminService::{$method}()");
        }
    }

    /**
     * 对象方法兼容(停时)
     * @param string $method
     * @param array $arguments
     * @return bool
     * @throws \think\admin\Exception
     */
    public function __call(string $method, array $arguments)
    {
        return static::__callStatic($method, $arguments);
    }
}