<?php
declare (strict_types=1);

namespace think\admin\helper;

use think\admin\Helper;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\Model;
use think\admin\service\DataScopeService;
use think\admin\service\AdminService;
/**
 * 搜索条件处理器
 * Class QueryHelper
 * @package think\admin\helper
 * @see \think\db\Query
 * @mixin Query
 */
class QueryHelper extends Helper
{
    /**
     * 分页助手工具
     * @var PageHelper
     */
    protected $page;

    /**
     * 当前数据操作
     * @var Query
     */
    protected $query;

    /**
     * 初始化默认数据
     * @var array
     */
    protected $input;

    /**
     * 获取当前Db操作对象
     * @return Query
     */
    public function db(): Query
    {
        return $this->query;
    }

    /**
     * 逻辑器初始化
     * @param Model|BaseQuery|string $dbQuery
     * @param string|array|null $input 输入数据
     * @param callable|null $callable 初始回调
     * @return $this
     * @throws \think\db\exception\DbException
     */
    public function init($dbQuery, $input = null, ?callable $callable = null): QueryHelper
    {
        $this->page = PageHelper::instance();
        //增加数据访问权限（数据隔离）
        $this->input = $this->setPermission($this->getInputData($input));
        $this->query = $this->page->autoSortQuery($dbQuery);
        if (is_callable($callable)) {
            call_user_func($callable, $this, $this->query);
        }
        return $this;
    }

    /**
     * 设置 Like 查询条件
     * @param string|array $fields 查询字段
     * @param string $split 前后分割符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function like($fields, string $split = '', $input = null, string $alias = '#'): QueryHelper
    {
        $data = $this->getInputData($input ?: $this->input);
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            [$dk, $qk] = [$field, $field];
            if (stripos($field, $alias) !== false) {
                [$dk, $qk] = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->whereLike($dk, "%{$split}{$data[$qk]}{$split}%");
            }
        }
        return $this;
    }

    /**
     * 设置 Equal 查询条件
     * @param string|array $fields 查询字段
     * @param string|array|null $input 输入类型
     * @param string $alias 别名分割符
     * @return $this
     */
    public function equal($fields, $input = null, string $alias = '#'): QueryHelper
    {
        $data = $this->getInputData($input ?: $this->input);
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            [$dk, $qk] = [$field, $field];
            if (stripos($field, $alias) !== false) {
                [$dk, $qk] = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->where($dk, strval($data[$qk]));
            }
        }
        return $this;
    }

    /**
     * 设置 IN 区间查询
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function in($fields, string $split = ',', $input = null, string $alias = '#'): QueryHelper
    {
        $data = $this->getInputData($input ?: $this->input);
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            [$dk, $qk] = [$field, $field];
            if (stripos($field, $alias) !== false) {
                [$dk, $qk] = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->whereIn($dk, explode($split, strval($data[$qk])));
            }
        }
        return $this;
    }


    /**
     * 两字段范围查询
     * @example field1:field2#field,field11:field22#field00
     * @param string|array $fields 查询字段
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function valueRange($fields, $input = null, string $alias = '#'): QueryHelper
    {
        $data = $this->getInputData($input ?: $this->input);
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            if (strpos($field, ':') !== false) {
                if (stripos($field, $alias) !== false) {
                    [$dk0, $qk0] = explode($alias, $field);
                    [$dk1, $dk2] = explode(':', $dk0);
                } else {
                    [$qk0] = [$dk1, $dk2] = explode(':', $field, 2);
                }
                if (isset($data[$qk0]) && $data[$qk0] !== '') {
                    $this->query->where([[$dk1, '<=', $data[$qk0]], [$dk2, '>=', $data[$qk0]]]);
                }
            }
        }
        return $this;
    }

    /**
     * 设置内容区间查询
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function valueBetween($fields, string $split = ' ', $input = null, string $alias = '#'): QueryHelper
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias);
    }

    /**
     * 设置日期时间区间查询
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function dateBetween($fields, string $split = ' - ', $input = null, string $alias = '#'): QueryHelper
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias, function ($value, $type) {
            if (preg_match('#^\d{4}(-\d\d){2}\s+\d\d(:\d\d){2}$#', $value)) return $value;
            else return $type === 'after' ? "{$value} 23:59:59" : "{$value} 00:00:00";
        });
    }

    /**
     * 设置时间戳区间查询
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @return $this
     */
    public function timeBetween($fields, string $split = ' - ', $input = null, string $alias = '#'): QueryHelper
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias, function ($value, $type) {
            if (preg_match('#^\d{4}(-\d\d){2}\s+\d\d(:\d\d){2}$#', $value)) return strtotime($value);
            else return $type === 'after' ? strtotime("{$value} 23:59:59") : strtotime("{$value} 00:00:00");
        });
    }

    /**
     * 实例化分页管理器
     * @param boolean $page 是否启用分页
     * @param boolean $display 是否渲染模板
     * @param boolean|integer $total 集合分页记录数
     * @param integer $limit 集合每页记录数
     * @param string $template 模板文件名称
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function page(bool $page = true, bool $display = true, $total = false, int $limit = 0, string $template = ''): array
    {
        return $this->page->init($this->query, $page, $display, $total, $limit, $template);
    }

    /**
     * 清空数据并保留表结构
     * @return $this
     * @throws \think\db\exception\DbException
     */
    public function empty(): QueryHelper
    {
        $table = $this->query->getTable();
        $ctype = strtolower($this->query->getConfig('type'));
        if ($ctype === 'mysql') {
            $this->query->getConnection()->execute("truncate table `{$table}`");
        } elseif (in_array($ctype, ['sqlsrv', 'oracle', 'pgsql'])) {
            $this->query->getConnection()->execute("truncate table {$table}");
        } else {
            $this->query->newQuery()->whereRaw('1=1')->delete();
        }
        return $this;
    }

    /**
     * 中间回调处理
     * @param callable $after
     * @return $this
     */
    public function filter(callable $after): QueryHelper
    {
        call_user_func($after, $this, $this->query);
        return $this;
    }


    /**
     * QueryHelper call.
     * @param string $name 调用方法名称
     * @param array $args 调用参数内容
     * @return $this
     */
    public function __call(string $name, array $args): QueryHelper
    {
        if (is_callable($callable = [$this->query, $name])) {
            call_user_func_array($callable, $args);
        }
        return $this;
    }

    /**
     * 设置区域查询条件
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string|array|null $input 输入数据
     * @param string $alias 别名分割符
     * @param callable|null $callback 回调函数
     * @return $this
     */
    private function setBetweenWhere($fields, string $split = ' ', $input = null, string $alias = '#', ?callable $callback = null): QueryHelper
    {
        $data = $this->getInputData($input ?: $this->input);
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            [$dk, $qk] = [$field, $field];
            if (stripos($field, $alias) !== false) {
                [$dk, $qk] = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                [$begin, $after] = explode($split, strval($data[$qk]));
                if (is_callable($callback)) {
                    $after = call_user_func($callback, $after, 'after');
                    $begin = call_user_func($callback, $begin, 'begin');
                }
                $this->query->whereBetween($dk, [$begin, $after]);
            }
        }
        return $this;
    }

    /**
     * 获取输入数据
     * @param string|array|null $input
     * @return array
     */
    private function getInputData($input): array
    {
        if (is_array($input)) {
            return $input;
        } else {
            $input = $input ?: 'request';
            return $this->app->request->$input();
        }
    }

    /**
     * 权限处理｜数据隔离
     * 新增按租户进行数据隔离
     * $param array|string|null $input
     * @retrun array
     */
    private function setPermission($input):array
    {
        if(empty($input['tenant_id'])){
            $input['tenant_id'] = AdminService::getTenantId();
        }
        return $input;
    }


    /**
     * 设置 IN 区间查询(权限处理)
     * @param $fields
     * @param $input
     * @return $this
     */
    public function dataScope($fields, $input = null): QueryHelper
    {
        if (AdminService::isSuper()) {
            return $this;
        }
        $data = $this->getInputData($input ?: $this->input);

        try {
            $userIds = DataScopeService::instance()->setDataScope();
        } catch (\Exception $exception) {
            $userIds = [];
            trace_file($exception);
        }

        if (empty($userIds)) {
            return $this;
        }else{
            $this->query->whereIn('created_by', $userIds);
        }
        return $this;
    }
}
