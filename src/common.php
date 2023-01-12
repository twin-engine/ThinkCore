<?php

declare (strict_types=1);

use think\admin\extend\CodeExtend;
use think\admin\extend\HttpExtend;
use think\admin\Helper;
use think\admin\helper\TokenHelper;
use think\admin\Library;
use think\admin\service\AdminService;
use think\admin\service\QueueService;
use think\admin\service\RuntimeService;
use think\admin\service\SystemService;
use think\admin\Storage;
use think\db\Query;
use think\Model;

if (!function_exists('p')) {
    /**
     * 打印输出数据到文件
     * @param mixed $data 输出的数据
     * @param boolean $new 强制替换文件
     * @param null|string $file 保存文件名称
     * @return false|int
     */
    function p($data, bool $new = false, ?string $file = null)
    {
        return SystemService::putDebug($data, $new, $file);
    }
}
if (!function_exists('m')) {
    /**
     * 动态创建模型对象
     * @param string $name 模型名称
     * @param array $data 初始数据
     * @param string $conn 指定连接
     * @return Model
     */
    function m(string $name, array $data = [], string $conn = ''): Model
    {
        return Helper::buildModel($name, $data, $conn);
    }
}
if (!function_exists('auth')) {
    /**
     * 访问权限检查
     * @param null|string $node
     * @return boolean
     * @throws ReflectionException
     */
    function auth(?string $node): bool
    {
        return AdminService::check($node);
    }
}

if (!function_exists('admuri')) {
    /**
     * 生成后台 URL 地址
     * @param string $url 路由地址
     * @param array $vars PATH 变量
     * @param boolean|string $suffix 后缀
     * @param boolean|string $domain 域名
     * @return string
     */
    function admuri(string $url = '', array $vars = [], $suffix = true, $domain = false): string
    {
        return sysuri('admin/index/index') . '#' . url($url, $vars, $suffix, $domain)->build();
    }
}
if (!function_exists('sysuri')) {
    /**
     * 生成最短 URL 地址
     * @param string $url 路由地址
     * @param array $vars PATH 变量
     * @param boolean|string $suffix 后缀
     * @param boolean|string $domain 域名
     * @return string
     */
    function sysuri(string $url = '', array $vars = [], $suffix = true, $domain = false): string
    {
        return SystemService::sysuri($url, $vars, $suffix, $domain);
    }
}

if (!function_exists('encode')) {
    /**
     * 加密 UTF8 字符串
     * @param string $content
     * @return string
     * @deprecated
     */
    function encode(string $content): string
    {
        [$chars, $length] = ['', strlen($string = iconv('UTF-8', 'GBK//TRANSLIT', $content))];
        for ($i = 0; $i < $length; $i++) $chars .= str_pad(base_convert(strval(ord($string[$i])), 10, 36), 2, '0', 0);
        return $chars;
    }
}
if (!function_exists('decode')) {
    /**
     * 解密 UTF8 字符串
     * @param string $content
     * @return string
     * @deprecated
     */
    function decode(string $content): string
    {
        $chars = '';
        foreach (str_split($content, 2) as $char) {
            $chars .= chr(intval(base_convert($char, 36, 10)));
        }
        return iconv('GBK//TRANSLIT', 'UTF-8', $chars);
    }
}

if (!function_exists('str2arr')) {
    /**
     * 字符串转数组
     * @param string $text 待转内容
     * @param string $separ 分隔字符
     * @param null|array $allow 限定规则
     * @return array
     */
    function str2arr(string $text, string $separ = ',', ?array $allow = null): array
    {
        $items = [];
        foreach (explode($separ, trim($text, $separ)) as $item) {
            if ($item !== '' && (!is_array($allow) || in_array($item, $allow))) {
                $items[] = trim($item);
            }
        }
        return $items;
    }
}
if (!function_exists('arr2str')) {
    /**
     * 数组转字符串
     * @param array $data 待转数组
     * @param string $separ 分隔字符
     * @param null|array $allow 限定规则
     * @return string
     */
    function arr2str(array $data, string $separ = ',', ?array $allow = null): string
    {
        foreach ($data as $key => $item) {
            if ($item === '' || (is_array($allow) && !in_array($item, $allow))) {
                unset($data[$key]);
            }
        }
        return $separ . join($separ, $data) . $separ;
    }
}

if (!function_exists('isDebug')) {
    /**
     * 调试模式运行
     * @return boolean
     */
    function isDebug(): bool
    {
        return RuntimeService::isDebug();
    }
}
if (!function_exists('isOnline')) {
    /**
     * 产品模式运行
     * @return boolean
     */
    function isOnline(): bool
    {
        return RuntimeService::isOnline();
    }
}
if (!function_exists('sysconf')) {
    /**
     * 获取或配置系统参数
     * @param string $name 参数名称
     * @param mixed $value 参数内容
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    function sysconf(string $name = '', $value = null)
    {
        if (is_null($value) && is_string($name)) {
            return SystemService::get($name);
        } else {
            return SystemService::set($name, $value);
        }
    }
}
if (!function_exists('syconfig')) {
    /**
     * 获取系统参数
     * @param string $groupCode 常量的分类名称
     * @param string $code 参数名称
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    function syconfig(string $groupCode = '', string $code = '')
    {
        return SystemService::getConfig($groupCode, $code);
    }
}
if (!function_exists('sysdata')) {
    /**
     * JSON 数据读取与存储
     * @param string $name 数据名称
     * @param mixed $value 数据内容
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    function sysdata(string $name, $value = null)
    {
        if (is_null($value)) {
            return SystemService::getData($name);
        } else {
            return SystemService::setData($name, $value);
        }
    }
}
if (!function_exists('syspath')) {
    /**
     * 获取文件绝对路径
     * @param string $name 文件路径
     * @param ?string $root 程序根路径
     * @return string
     */
    function syspath(string $name = '', ?string $root = null): string
    {
        if (is_null($root)) $root = Library::$sapp->getRootPath();
        $attr = ['/' => DIRECTORY_SEPARATOR, '\\' => DIRECTORY_SEPARATOR];
        return rtrim($root, '\\/') . DIRECTORY_SEPARATOR . ltrim(strtr($name, $attr), '\\/');
    }
}
if (!function_exists('sysoplog')) {
    /**
     * 写入系统日志
     * @param string $action 日志行为
     * @param string $content 日志内容
     * @return boolean
     */
    function sysoplog(string $action, string $content): bool
    {
        return SystemService::setOplog($action, $content);
    }
}
if (!function_exists('systoken')) {
    /**
     * 生成 CSRF-TOKEN 参数
     * @return string
     */
    function systoken(): string
    {
        return TokenHelper::token();
    }
}
if (!function_exists('sysqueue')) {
    /**
     * 注册异步处理任务
     * @param string $title 任务名称
     * @param string $command 执行内容
     * @param integer $later 延时执行时间
     * @param array $data 任务附加数据
     * @param integer $rscript 任务类型(0单例,1多例)
     * @param integer $loops 循环等待时间
     * @return string
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    function sysqueue(string $title, string $command, int $later = 0, array $data = [], int $rscript = 1, int $loops = 0): string
    {
        return QueueService::register($title, $command, $later, $data, $rscript, $loops)->code;
    }
}

if (!function_exists('enbase64url')) {
    /**
     * Base64安全URL编码
     * @param string $string
     * @return string
     */
    function enbase64url(string $string): string
    {
        return CodeExtend::enSafe64($string);
    }
}
if (!function_exists('debase64url')) {
    /**
     * Base64安全URL解码
     * @param string $string
     * @return string
     */
    function debase64url(string $string): string
    {
        return CodeExtend::deSafe64($string);
    }
}

if (!function_exists('xss_safe')) {
    /**
     * 文本内容XSS过滤
     * @param string $text
     * @return string
     */
    function xss_safe(string $text): string
    {
        // 将所有 onxxx= 中的字母 o 替换为符号 ο，注意它不是字母
        $rules = ['#<script.*?<\/script>#is' => '', '#(\s)on(\w+=\S)#i' => '$1οn$2'];
        return preg_replace(array_keys($rules), array_values($rules), trim($text));
    }
}
if (!function_exists('http_get')) {
    /**
     * 以 get 模拟网络请求
     * @param string $url HTTP请求URL地址
     * @param array|string $query GET请求参数
     * @param array $options CURL参数
     * @return boolean|string
     */
    function http_get(string $url, $query = [], array $options = [])
    {
        return HttpExtend::get($url, $query, $options);
    }
}
if (!function_exists('http_post')) {
    /**
     * 以 post 模拟网络请求
     * @param string $url HTTP请求URL地址
     * @param array|string $data POST请求数据
     * @param array $options CURL参数
     * @return boolean|string
     */
    function http_post(string $url, $data, array $options = [])
    {
        return HttpExtend::post($url, $data, $options);
    }
}
if (!function_exists('data_save')) {
    /**
     * 数据增量保存
     * @param Model|Query|string $dbQuery
     * @param array $data 需要保存或更新的数据
     * @param string $key 条件主键限制
     * @param mixed $where 其它的where条件
     * @return boolean|integer
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    function data_save($dbQuery, array $data, string $key = 'id', $where = [])
    {
        return SystemService::save($dbQuery, $data, $key, $where);
    }
}
if (!function_exists('down_file')) {
    /**
     * 下载远程文件到本地
     * @param string $source 远程文件地址
     * @param boolean $force 是否强制重新下载
     * @param integer $expire 强制本地存储时间
     * @return string
     */
    function down_file(string $source, bool $force = false, int $expire = 0): string
    {
        return Storage::down($source, $force, $expire)['url'] ?? $source;
    }
}

if (!function_exists('trace_file')) {
    /**
     * 输出异常数据到文件
     * @param \Exception $exception
     * @return boolean
     */
    function trace_file(Exception $exception): bool
    {
        $path = Library::$sapp->getRuntimePath() . 'trace';
        if (!file_exists($path)) mkdir($path, 0755, true);
        $name = substr($exception->getFile(), strlen(syspath()));
        $file = $path . DIRECTORY_SEPARATOR . date('Ymd_His_') . strtr($name, ['/' => '.', '\\' => '.']);
        $class = get_class($exception);
        return false !== file_put_contents($file,
                "[CODE] {$exception->getCode()}" . PHP_EOL .
                "[INFO] {$exception->getMessage()}" . PHP_EOL .
                "[FILE] {$class} in {$name} line {$exception->getLine()}" . PHP_EOL .
                "[TIME] " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL .
                '[TRACE]' . PHP_EOL . $exception->getTraceAsString()
            );
    }
}
if (!function_exists('format_bytes')) {
    /**
     * 文件字节单位转换
     * @param string|integer $size
     * @return string
     */
    function format_bytes($size): string
    {
        if (is_numeric($size)) {
            $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
            for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
            return round($size, 2) . ' ' . $units[$i];
        } else {
            return $size;
        }
    }
}
if (!function_exists('format_datetime')) {
    /**
     * 日期格式标准输出
     * @param int|string $datetime 输入日期
     * @param string $format 输出格式
     * @return string
     */
    function format_datetime($datetime, string $format = 'Y年m月d日 H:i:s'): string
    {

        if (empty($datetime)) {
            return '-';
        } elseif (is_numeric($datetime)) {
            return date(lang($format), intval($datetime));
        } else {
            return date(lang($format), strtotime($datetime));
        }
    }
}
if(!function_exists('desensitize')){
    /**
     * 信息脱敏函数
     * @param $string 字符串
     * @param int $start 开始明文长度
     * @param int $end 结束明文长度
     * @param string $re 替换字符
     * @return string
     */
    function desensitize($string = '', $start = 0, $end = 0, $re = '*'){
        if(empty($string) || empty($end) || empty($re)) return $string;
        $strLen = strlen($string);
        if($strLen < ($start + $end)) return $string;
        $strEnd = $strLen-$end;
        for($i=0; $i<$strLen; $i++) {
            if($i>=$start && $i<$strEnd)
                $str_arr[] = $re;
            else
                $str_arr[] = mb_substr($string, $i, 1);
        }
        return implode('',$str_arr);
    }
}
if(!function_exists('real_ip')){
    /**
     * 获得用户的真实IP地址
     *
     * @access  public
     * @return  string
     */
    function real_ip(){
        static $realip = NULL;
        if ($realip !== NULL){
            return $realip;
        }
        if (isset($_SERVER)){
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                /* 取X-Forwarded-For中第一个非unknown的有效IP字符串 */
                foreach ($arr AS $ip){
                    $ip = trim($ip);
                    if ($ip != 'unknown'){
                        $realip = $ip;
                        break;
                    }
                }
            }elseif (isset($_SERVER['HTTP_CLIENT_IP'])){
                $realip = $_SERVER['HTTP_CLIENT_IP'];
            }else{
                if (isset($_SERVER['REMOTE_ADDR'])){
                    $realip = $_SERVER['REMOTE_ADDR'];
                }else{
                    $realip = '0.0.0.0';
                }
            }
        }else{
            if (getenv('HTTP_X_FORWARDED_FOR')){
                $realip = getenv('HTTP_X_FORWARDED_FOR');
            }elseif (getenv('HTTP_CLIENT_IP')){
                $realip = getenv('HTTP_CLIENT_IP');
            }else{
                $realip = getenv('REMOTE_ADDR');
            }
        }
        preg_match("/[\d\.]{7,15}/", $realip, $onlineip);
        $realip = !empty($onlineip[0]) ? $onlineip[0] : '0.0.0.0';
        return $realip;
    }
}
if (!function_exists('http_post_data')) {
    /**
     * 蚂蚁区块链 post 模拟网络请求
     * @param $url
     * @param $data_string
     * @return array
     */
    function http_post_data($url, $data_string) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    		'Content-Type: application/json; charset=utf-8',
    		'Content-Length: ' . strlen($data_string))
    	);
        ob_start();
        curl_exec($ch);
        $return_content = ob_get_contents();
        ob_end_clean();
    
        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array($return_code, $return_content);
    }
    
}
if (!function_exists('getUnixTimestamp')) {
    /**
     * 13位时间戳转换
     * @return float
     */
    function getUnixTimestamp ()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($s1) + floatval($s2)) * 1000);
    }   
}

if (!function_exists('curls')) {
    /**
     * curl请求
     * @param $url
     * @param $timeout
     * @return bool|string
     */
    function curls($url, $timeout = '15')
    {
        // 1. 初始化
        $ch = curl_init();
        // 2. 设置选项，包括URL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        // 3. 执行并获取HTML文档内容
        $info = curl_exec($ch);
        // 4. 释放curl句柄
        curl_close($ch);
 
        return $info;
    }
}
if (!function_exists('TimeToSeconds')) {
    /**
     * 时间转换
     * @param $end_time
     * @return int|string
     */
    function TimeToSeconds($end_time)
    {
        date_default_timezone_set('Asia/Shanghai');
        $now = time();
        $expires_in = $end_time;
        $expires = $expires_in - $now;
        if($expires > 0){
            $seconds = (int)$expires;
            if( $seconds < 60 ){
                $format_time = gmstrftime('%S秒' , $seconds);
            }elseif( $seconds < 3600 ){
                $format_time = gmstrftime('%M分%S秒' , $seconds);
            }elseif( $seconds < 86400 ){
                $format_time = gmstrftime('%H时%M分%S秒' , $seconds);
            }else{
                $time = explode(' ' , gmstrftime('%j %H %M %S' , $seconds));//Array ( [0] => 04 [1] => 14 [2] => 14 [3] => 35 )
                $format_time = ( $time[0] - 1 ) . '天' . $time[1] . '时' . $time[2] . '分' . $time[3] . '秒';
            }
            return ltrim($format_time,'0');
        }else{
            return 0;
        }
    }
}
if(!function_exists('get_http_type'))
{
    /**
     * 获取当前网址协议
     * @return string
     */
    function get_http_type()
    {
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        return $http_type;
    }
}