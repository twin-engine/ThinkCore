<?php

declare (strict_types=1);


namespace think\admin\support;

use think\Route as ThinkRoute;

/**
 * 自定义路由对象
 * Class Route
 * @package think\admin\support
 */
class Route extends ThinkRoute
{
    /**
     * 重载路由配置
     * @return $this
     */
    public function reload(): Route
    {
        $this->config = array_merge($this->config, $this->app->config->get('route'));
        return $this;
    }
}