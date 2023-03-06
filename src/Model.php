<?php

declare (strict_types=1);

namespace think\admin;

use think\admin\helper\DeleteHelper;
use think\admin\helper\FormHelper;
use think\admin\helper\QueryHelper;
use think\admin\helper\SaveHelper;
use think\admin\service\SystemService;
use think\Container;

/**
 * 基础模型类
 * Class Model
 * @package think\admin
 *
 * 模型日志记录
 * @method void onAdminSave(string $ids) 记录状态变更日志
 * @method void onAdminUpdate(string $ids) 记录更新数据日志
 * @method void onAdminInsert(string $ids) 记录新增数据日志
 * @method void onAdminDelete(string $ids) 记录删除数据日志
 *
 * 静态助手调用
 * @method static bool mSave(array $data = [], string $field = '', mixed $where = []) 快捷更新
 * @method static bool mDelete(string $field = '', mixed $where = []) 快捷删除
 * @method static bool|array mForm(string $template = '', string $field = '', mixed $where = [], array $data = []) 快捷表单
 * @method static bool|integer mUpdate(array $data = [], string $field = '', mixed $where = []) 快捷保存
 * @method static QueryHelper mQuery($input = null, callable $callable = null) 快捷查询
 */
abstract class Model extends \think\Model
{
    /**
     * 日志类型
     * @var string
     */
    protected $oplogType;

    /**
     * 日志名称
     * @var string
     */
    protected $oplogName;

    /**
     * 日志过滤
     * @var callable
     */
    public static $oplogCall;

    /**
     * 创建模型实例
     * @template t of static
     * @param mixed $data
     * @return t|static
     */
    public static function mk($data = [])
    {
        return new static($data);
    }

    /**
     * 调用魔术方法
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return $this|false|mixed
     */
    public function __call($method, $args)
    {
        $oplogs = [
            'onAdminSave'   => "修改%s[%s]状态",
            'onAdminUpdate' => "更新%s[%s]记录",
            'onAdminInsert' => "增加%s[%s]成功",
            "onAdminDelete" => "删除%s[%s]成功",
        ];
        if (isset($oplogs[$method])) {
            if ($this->oplogType && $this->oplogName) {
                $changeIds = $args[0] ?? '';
                if (is_callable(static::$oplogCall)) {
                    $changeIds = call_user_func(static::$oplogCall, $method, $changeIds, $this);
                }
                sysoplog($this->oplogType, lang($oplogs[$method], [lang($this->oplogName), $changeIds]));
            }
            return $this;
        } else {
            return parent::__call($method, $args);
        }
    }

    /**
     * 静态魔术方法
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return mixed|false|integer|QueryHelper
     */
    public static function __callStatic($method, $args)
    {
        $helpers = [
            'mForm'   => [FormHelper::class, 'init'],
            'mSave'   => [SaveHelper::class, 'init'],
            'mQuery'  => [QueryHelper::class, 'init'],
            'mDelete' => [DeleteHelper::class, 'init'],
            'mUpdate' => [SystemService::class, 'save'],
        ];
        if (isset($helpers[$method])) {
            [$class, $method] = $helpers[$method];
            return Container::getInstance()->invokeClass($class)->$method(static::class, ...$args);
        } else {
            return parent::__callStatic($method, $args);
        }
    }
}
