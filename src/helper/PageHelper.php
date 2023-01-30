<?php
declare (strict_types=1);

namespace think\admin\helper;

use think\admin\Helper;
use think\admin\service\AdminService;
use think\db\BaseQuery;
use think\db\Query;
use think\exception\HttpResponseException;
use think\Model;

/**
 * 列表处理管理器
 * Class PageHelper
 * @package think\admin\helper
 */
class PageHelper extends Helper
{
    /**
     * 逻辑器初始化
     * @param Model|BaseQuery|string $dbQuery
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
    public function init($dbQuery, $page = true, bool $display = true, $total = false, int $limit = 0, string $template = ''): array
    {
        $query = $this->autoSortQuery($dbQuery);
        if ($page !== false) {
            $get = $this->app->request->get();
            $limits = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150, 160, 170, 180, 190, 200];
            if ($limit <= 1) {
                $limit = $get['limit'] ?? $this->app->cookie->get('limit', 20);
                if (in_array($limit, $limits) && ($get['not_cache_limit'] ?? 0) < 1) {
                    $this->app->cookie->set('limit', ($limit = intval($limit >= 5 ? $limit : 20)) . '');
                }
            }
            $inner = strpos($get['spm'] ?? '', 'm-') === 0;
            $prefix = $inner ? (sysuri('admin/index/index') . '#') : '';
            // 生成分页数据
            $config = ['list_rows' => $limit, 'query' => $get];
            if (is_numeric($page)) $config['page'] = $page;
            $data = ($paginate = $query->paginate($config, $this->getCount($query, $total)))->toArray();
            $result = ['page' => ['limit' => $data['per_page'], 'total' => $data['total'], 'pages' => $data['last_page'], 'current' => $data['current_page']], 'list' => $data['data']];
            //xss过滤处理
            $this->xssFilter($result['list']);
            //某些字段后处理（图片、重要信息脱敏）
            $this->fieldAfterMk($query,$result['list']);
            // 分页跳转参数
            $select = "<select onchange='location.href=this.options[this.selectedIndex].value'>";
            if (in_array($limit, $limits)) foreach ($limits as $num) {
                $get = array_merge($get, ['limit' => $num, 'page' => 1]);
                $url = $this->app->request->baseUrl() . '?' . http_build_query($get, '', '&', PHP_QUERY_RFC3986);
                $select .= sprintf('<option data-num="%d" value="%s" %s>%d</option>', $num, $prefix . $url, $limit === $num ? 'selected' : '', $num);
            } else {
                $select .= "<option selected>{$limit}</option>";
            }
            $html = lang('think_library_page_html', [$data['total'], "{$select}</select>", $data['last_page'], $data['current_page']]);
            $link = $inner ? str_replace('<a href=', '<a data-open=', $paginate->render() ?: '') : ($paginate->render() ?: '');
            $this->class->assign('pagehtml', "<div class='pagination-container nowrap'><span>{$html}</span>{$link}</div>");
        } else {
            $result = ['list' => $query->select()->toArray()];
        }
        if (false !== $this->class->callback('_page_filter', $result['list'], $result) && $display) {
            if ($this->output === 'get.json') {
                $this->class->success('JSON数据获取成功！', $result);
            } else {
                $this->class->fetch($template, $result);
            }
        }
        return $result;
    }
    /**
     * 组件 Layui.Table 处理
     * @param BaseQuery|Model|string $dbQuery
     * @param string $template
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function layTable($dbQuery, string $template = ''): array
    {
        if ($this->output === 'get.json') {
            $get = $this->app->request->get();
            $query = static::buildQuery($dbQuery);
            // 根据参数排序
            if (isset($get['_field_']) && isset($get['_order_'])) {
                $dbQuery->order("{$get['_field_']} {$get['_order_']}");
            }
            return PageHelper::instance()->init($query);
        }
        if ($this->output === 'get.layui.table') {
            $get = $this->app->request->get();
            $query = $this->autoSortQuery($dbQuery);
            // 根据参数排序
            if (isset($get['_field_']) && isset($get['_order_'])) {
                $query->order("{$get['_field_']} {$get['_order_']}");
            }
            // 数据分页处理
            if (empty($get['page']) || empty($get['limit'])) {
                $data = $query->select()->toArray();
                $result = ['msg' => '', 'code' => 0, 'count' => count($data), 'data' => $data];
            } else {
                $cfg = ['list_rows' => $get['limit'], 'query' => $get];
                $data = $query->paginate($cfg, static::getCount($query))->toArray();
                $result = ['msg' => '', 'code' => 0, 'count' => $data['total'], 'data' => $data['data']];
            }
            static::xssFilter($result['data']);
            if (false !== $this->class->callback('_page_filter', $result['data'], $result)) {
                throw new HttpResponseException(json($result));
            } else {
                return $result;
            }
        } else {
            $this->class->fetch($template);
            return [];
        }
    }

    /**
     * 特殊字段的后处理（图片类、重要信息脱敏）
     * @param Model|BaseQuery|string $dbQuery
     * @param array $items
     */
     private function fieldAfterMk($query, array &$items)
     {
        if (method_exists($query, 'getTableFields')) {
            $fields = $query->getTableFields();
            if(count($items)>0){
                $itemsFields = array_keys($items[0]);
                //数据中含有图片ID的字段通过键名判断获取相应图片URL(图片数组命名方式：字段名+Arr,例字段logo,则对应为logoArr)
                foreach($itemsFields as $field){
                   if(in_array($field,['image','image_id','pic','img','logo','certificate','license','business_license','qualification_documents'])){
                        foreach($items as &$v){
                            if($v[$field]){
                                $ids = explode(',',$v[$field]);
                                $v[$field.'Arr'] = $this->autoSortQuery('sys_upload_file')->whereIn('id',$ids)->select()->toArray();
                                if(count($v[$field.'Arr'])==1) $v[$field.'_url'] = $v[$field.'Arr'][0]['url'];
                                
                            }
                        }
                    } 
                }
            }
            //特殊字段的后期处理方式，读取表字段
            foreach($fields as $field){
                //手机号，电话号脱敏
                if(in_array($field,['phone','contact_phone','mobile','tel'])){
                    foreach($items as &$v){
                        if($v[$field]){
                            $v[$field] = desensitize($v[$field],3,4);
                        }
                    }
                }
                //邮箱脱敏
                if(in_array($field,['email','contact_mail','mail'])){
                    foreach($items as &$v){
                        if($v[$field]){
                            $newfield = explode('@',$v[$field]);
                            $len = strlen($newfield[1]) + 1;
                            $v[$field] = desensitize($v[$field],1,$len);
                        }
                    }
                }
                //姓名，真名脱敏
                if(in_array($field,['leader','realname'])){
                    foreach($items as &$v){
                        if($v[$field]){
                            $v[$field] = desensitize($v[$field],1,1);
                        }
                    }
                }
            }
        }
     }

    /**
     * 输出 XSS 过滤处理
     * @param array $items
     */
    private function xssFilter(array &$items)
    {
        foreach ($items as &$item) if (is_array($item)) {
            $this->xssFilter($item);
        } elseif (is_string($item)) {
            $item = htmlspecialchars($item, ENT_QUOTES);
        }
    }

    /**
     * 查询对象数量统计
     * @param BaseQuery|Query $query
     * @param boolean|integer $total
     * @return integer|boolean|string
     */
    private function getCount($query, $total = false)
    {
        if ($total === true || is_numeric($total)) return $total;
        [$query, $options] = [clone $query, $query->getOptions()];
        if (empty($options['union'])) return $query->count();
        $table = [$query->buildSql() => '_union_count_'];
        return $query->newQuery()->table($table)->count();
    }

    /**
     * 绑定排序并返回操作对象
     * @param Model|BaseQuery|string $dbQuery
     * @return Query
     * @throws \think\db\exception\DbException
     */
    public function autoSortQuery($dbQuery): Query
    {
        $query = $this->buildQuery($dbQuery);
        if ($this->app->request->isPost() && $this->app->request->post('action') === 'sort') {
            if (!AdminService::instance()->isLogin()) {
                $this->class->error(lang('think_library_not_login'));
            }
            if (method_exists($query, 'getTableFields') && in_array('sort', $query->getTableFields())) {
                if ($this->app->request->has($pk = $query->getPk() ?: 'id', 'post')) {
                    $map = [$pk => $this->app->request->post($pk, 0)];
                    $data = ['sort' => intval($this->app->request->post('sort', 0))];
                    if ($query->newQuery()->where($map)->update($data) !== false) {
                        $this->class->success(lang('think_library_sort_success'), '');
                    }
                }
            }
            $this->class->error(lang('think_library_sort_error'));
        }
        return $query;
    }

}