<?php

declare (strict_types=1);

namespace think\admin\storage;

use think\admin\Exception;
use think\admin\extend\HttpExtend;
use think\admin\Storage;

/**
 * 七牛云存储支持
 * Class QiniuStorage
 * @package think\admin\storage
 */
class QiniuStorage extends Storage
{

    private $bucket;
    private $accessKey;
    private $secretKey;

    /**
     * 初始化入口
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function initialize()
    {
        // 读取配置文件
        $this->bucket = sysconf('storage.qiniu_bucket|raw');
        $this->accessKey = sysconf('storage.qiniu_access_key|raw');
        $this->secretKey = sysconf('storage.qiniu_secret_key|raw');
        // 计算链接前缀
        $host = strtolower(sysconf('storage.qiniu_http_domain|raw'));
        $type = strtolower(sysconf('storage.qiniu_http_protocol|raw'));
        if ($type === 'auto') {
            $this->domain = "//{$host}";
        } elseif (in_array($type, ['http', 'https'])) {
            $this->domain = "{$type}://{$host}";
        } else {
            throw new Exception(lang('未配置七牛云URL域名哦'));
        }
    }

    /**
     * 上传文件内容
     * @param string $name 文件名称
     * @param string $file 文件内容
     * @param boolean $safe 安全模式
     * @param null|string $attname 下载名称
     * @return array
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null): array
    {
        $token = $this->buildUploadToken($name, 3600, $attname);
        $data = ['key' => $name, 'token' => $token, 'fileName' => $name];
        $file = ['field' => "file", 'name' => $name, 'content' => $file];
        $result = HttpExtend::submit($this->upload(), $data, $file, [], 'POST', false);
        return json_decode($result, true);
    }


    /**
     * 根据文件名读取文件内容
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function get(string $name, bool $safe = false): string
    {
        $url = $this->url($name, $safe) . "?e=" . time();
        $token = "{$this->accessKey}:{$this->safeBase64(hash_hmac('sha1', $url, $this->secretKey, true))}";
        return static::curlGet("{$url}&token={$token}");
    }

    /**
     * 删除存储的文件
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return boolean
     */
    public function del(string $name, bool $safe = false): bool
    {
        [$EncodedEntryURI, $AccessToken] = $this->getAccessToken($name, 'delete');
        $data = json_decode(HttpExtend::post("https://rs.qiniu.com/delete/{$EncodedEntryURI}", [], [
            'headers' => ["Authorization:QBox {$AccessToken}"],
        ]), true);
        return empty($data['error']);
    }

    /**
     * 检查文件是否已经存在
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return boolean
     */
    public function has(string $name, bool $safe = false): bool
    {
        return is_array($this->info($name, $safe));
    }

    /**
     * 获取文件当前URL地址
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @param null|string $attname 下载名称
     * @return string
     */
    public function url(string $name, bool $safe = false, ?string $attname = null): string
    {
        return "{$this->domain}/{$this->delSuffix($name)}{$this->getSuffix($attname,$name)}";
    }

    /**
     * 获取文件存储路径
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function path(string $name, bool $safe = false): string
    {
        return $this->url($name, $safe);
    }

    /**
     * 获取文件存储信息
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @param null|string $attname 下载名称
     * @return array
     */
    public function info(string $name, bool $safe = false, ?string $attname = null): array
    {
        [$entry, $token] = $this->getAccessToken($name);
        $data = json_decode(HttpExtend::get("https://rs.qiniu.com/stat/{$entry}", [], ['headers' => ["Authorization: QBox {$token}"]]), true);
        return isset($data['md5']) ? ['file' => $name, 'url' => $this->url($name, $safe, $attname), 'key' => $name] : [];
    }

    /**
     * 获取文件上传地址
     * @return string
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function upload(): string
    {
        $protocol = $this->app->request->isSsl() ? 'https' : 'http';
        // 注：汉字为兼容旧版本区域配置
        switch (sysconf('storage.qiniu_region|raw')) {
            case '华东':
            case 'up.qiniup.com':
                return "{$protocol}://up.qiniup.com";
            case 'up-cn-east-2.qiniup.com':
                return "{$protocol}://up-cn-east-2.qiniup.com";
            case '华北':
            case 'up-z1.qiniup.com':
                return "{$protocol}://up-z1.qiniup.com";
            case '华南':
            case 'up-z2.qiniup.com':
                return "{$protocol}://up-z2.qiniup.com";
            case '北美':
            case 'up-na0.qiniup.com':
                return "{$protocol}://up-na0.qiniup.com";
            case '东南亚':
            case 'up-as0.qiniup.com':
                return "{$protocol}://up-as0.qiniup.com";
            case 'up-ap-northeast-1.qiniup.com':
                return "{$protocol}://up-ap-northeast-1.qiniup.com";
            default:
                throw new Exception(lang('未配置七牛云空间区域哦'));
        }
    }

    /**
     * 获取文件上传令牌
     * @param ?string $name 文件名称
     * @param integer $expires 有效时间
     * @param ?string $attname 下载名称
     * @return string
     */
    public function buildUploadToken(?string $name = null, int $expires = 3600, ?string $attname = null): string
    {
        $key = is_null($name) ? '$(key)' : $name;
        $url = "{$this->domain}/$(key){$this->getSuffix($attname,$name)}";
        $policy = $this->safeBase64(json_encode([
            "deadline"   => time() + $expires, "scope" => is_null($name) ? $this->bucket : "{$this->bucket}:{$name}",
            'returnBody' => json_encode(['uploaded' => true, 'filename' => '$(key)', 'url' => $url, 'key' => $key, 'file' => $key], JSON_UNESCAPED_UNICODE),
        ]));
        return "{$this->accessKey}:{$this->safeBase64(hash_hmac('sha1', $policy, $this->secretKey, true))}:{$policy}";
    }

    /**
     * URL安全的Base64编码
     * @param string $content
     * @return string
     */
    private function safeBase64(string $content): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($content));
    }

    /**
     * 获取对象管理凭证
     * @param string $name 文件名称
     * @param string $type 操作类型
     * @return array
     */
    private function getAccessToken(string $name, string $type = 'stat'): array
    {
        $entry = $this->safeBase64("{$this->bucket}:{$name}");
        $sign = hash_hmac('sha1', "/{$type}/{$entry}\n", $this->secretKey, true);
        return [$entry, "{$this->accessKey}:{$this->safeBase64($sign)}"];
    }

    /**
     * 七牛云对象存储区域
     * @return array
     */
    public static function region(): array
    {
        return [
            'up.qiniup.com'                => lang('华东-浙江'),
            'up-cn-east-2.qiniup.com'      => lang('华东-浙江2'),
            'up-z1.qiniup.com'             => lang('华北-河北'),
            'up-z2.qiniup.com'             => lang('华南-广东'),
            'up-na0.qiniup.com'            => lang('北美-洛杉矶'),
            'up-as0.qiniup.com'            => lang('亚太-新加坡'),
            'up-ap-northeast-1.qiniup.com' => lang('亚太-首尔'),
        ];
    }
}