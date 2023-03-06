<?php

declare (strict_types=1);

namespace think\admin;

use think\admin\service\RuntimeService;
use think\admin\support\command\Database;
use think\admin\support\command\Package;
use think\admin\support\command\Publish;
use think\admin\support\command\Queue;
use think\admin\support\command\Replace;
use think\admin\support\command\Sysmenu;
use think\admin\support\middleware\JwtSession;
use think\admin\support\middleware\MultAccess;
use think\admin\support\middleware\RbacAccess;
use think\App;
use think\middleware\LoadLangPack;
use think\Request;
use think\Service;

/**
 * 模块注册服务
 * Class Library
 * @package think\admin
 */
class Library extends Service
{
    /**
     * 组件版本号
     */
    const VERSION = '6.1.23';

    /**
     * 静态应用实例
     * @var App
     */
    public static $sapp;

    /**
     * 启动服务
     */
    public function boot()
    {
        // 静态应用赋值
        static::$sapp = $this->app;

        // 注册 ThinkAdmin 指令
        $this->commands([
            Queue::class,
            Package::class,
            Sysmenu::class,
            Publish::class,
            Replace::class,
            Database::class,
        ]);

        // 动态应用运行参数
        RuntimeService::apply();

        // 服务初始化处理
        $this->app->event->listen('HttpRun', function (Request $request) {

            // 配置默认输入过滤
            $request->filter([function ($value) {
                return is_string($value) ? xss_safe($value) : $value;
            }]);

            // 判断访问模式兼容处理
            if ($request->isCli()) {
                // 兼容 CLI 访问控制器
                if (empty($_SERVER['REQUEST_URI']) && isset($_SERVER['argv'][1])) {
                    $request->setPathinfo($_SERVER['argv'][1]);
                }
            } else {
                // 兼容 HTTP 调用 Console 后 URL 问题
                $request->setHost($request->host());
            }

            // 注册多应用中间键
            $this->app->middleware->add(MultAccess::class);
        });

    }

    /**
     * 初始化服务
     */
    public function register()
    {
        // 动态加载应用初始化系统函数
        $this->app->lang->load(__DIR__ . '/lang/zh-cn.php', 'zh-cn');
        foreach (glob("{$this->app->getBasePath()}*/sys.php") as $file) {
            include $file;
        }

        // 终端 HTTP 访问时特殊处理
        if (!$this->app->request->isCli()) {

            // 初始化会话和语言包
            $isApiRequest = $this->app->request->header('api-token', '') !== '';
            $isYarRequest = is_numeric(stripos($this->app->request->header('user_agent', ''), 'PHP Yar RPC-'));
            if (!($isApiRequest || $isYarRequest || $this->app->request->get('not_init_session', 0) > 0)) {
                // 注册会话中间键
                $this->app->middleware->add(JwtSession::class);
                // 注册语言包中间键
                $this->app->middleware->add(LoadLangPack::class);
            }

            // 注册权限验证中间键
            $this->app->middleware->add(RbacAccess::class, 'route');
        }
    }
}