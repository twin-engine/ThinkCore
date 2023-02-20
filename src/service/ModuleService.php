<?php

declare (strict_types=1);

namespace think\admin\service;

use think\admin\Library;
use think\admin\Service;

/**
 * 系统模块管理
 * Class ModuleService
 * @package think\admin\service
 */
class ModuleService extends Service
{
    /**
     * 获取版本号信息
     * @return string
     */
    public static function getVersion(): string
    {
        return trim(Library::VERSION, 'v');
    }

    /**
     * 获取应用列表
     * @param array $data
     * @return array
     */
    public static function getModules(array $data = []): array
    {
        $path = Library::$sapp->getBasePath();
        foreach (scandir($path) as $item) if ($item[0] !== '.') {
            if (is_dir(realpath($path . $item))) $data[] = $item;
        }
        return $data;
    }

    /**
     * 获取本地组件
     * @param string $package 指定包名
     * @param boolean $force 强制刷新
     * @return array|string|null
     */
    public static function getLibrarys(string $package = '', bool $force = false)
    {
        static $plugs;
        if (empty($plugs) || $force) {
            $plugs = include syspath('vendor/versions.php');
        }
        return empty($package) ? $plugs : ($plugs[$package] ?? null);
    }
}