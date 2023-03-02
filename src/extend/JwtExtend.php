<?php

declare (strict_types=1);

namespace think\admin\extend;

use think\admin\Controller;
use think\admin\Exception;
use think\admin\Library;

/**
 * 接口 JWT 接口扩展
 * Class JwtExtend
 * @package think\admin\extend
 */
class JwtExtend
{
    // 标识字段
    private const skey = '__ISJWT_SESS__';

    // 头部参数
    private const header = ['typ' => 'JWT', 'alg' => 'HS256'];

    // 签名配置
    private const signTypes = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512'
    ];

    /**
     * 当前请求状态
     * @var boolean
     */
    public static $isjwt = false;

    /**
     * 是否返回令牌
     * @var boolean
     */
    private static $rejwt = false;

    /**
     * 当前请求数据
     * @var array
     */
    private static $inData = [];

    /**
     * 当前输出数据
     * @var array
     */
    private static $outData = [];

    /**
     * 生成 jwt token
     * @param array $payload jwt 载荷 格式如下非必须
     * [
     *     'iss' => 'jwt_admin',               // 该JWT的签发者
     *     'iat' => time(),                    // 签发时间
     *     'exp' => time() + 7200,             // 过期时间
     *     'nbf' => time() + 60,               // 该时间之前不接收处理该Token
     *     'sub' => '',                        // 面向的用户
     *     'jti' => md5(uniqid('JWT').time())  // 该 Token 唯一标识
     * ]
     * @param ?string $jwtkey 签名密钥
     * @param ?boolean $rejwt 输出令牌
     * @return string
     */
    public static function getToken(array $payload, ?string $jwtkey = null, ?bool $rejwt = null): string
    {
        is_bool($rejwt) && static::$rejwt = $rejwt;
        $payload['sub'] = CodeExtend::encrypt(static::setJwtMode(), static::jwtkey());
        $base64header = CodeExtend::enSafe64(json_encode(static::header, JSON_UNESCAPED_UNICODE));
        $base64payload = CodeExtend::enSafe64(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = static::withSign($base64header . '.' . $base64payload, static::header['alg'], $jwtkey);
        return $base64header . '.' . $base64payload . '.' . $signature;
    }

    /**
     * 验证 token 是否有效, 默认验证 exp,nbf,iat 时间
     * @param string $token 加密数据
     * @param ?string $jwtkey 签名密钥
     * @return array
     * @throws \think\admin\Exception
     */
    public static function verifyToken(string $token, ?string $jwtkey = null): array
    {
        $tokens = explode('.', $token);
        if (count($tokens) != 3) throw new Exception('数据解密失败！', 0, []);

        [$base64header, $base64payload, $signature] = $tokens;

        // 加密算法
        $header = json_decode(CodeExtend::deSafe64($base64header), true);
        if (empty($header['alg'])) throw new Exception('数据解密失败！', 0, []);

        // 签名验证
        if (self::withSign("{$base64header}.{$base64payload}", $header['alg'], static::jwtkey($jwtkey)) !== $signature) {
            throw new Exception('验证签名失败！', 0, []);
        }

        // 获取数据
        $payload = json_decode(CodeExtend::deSafe64($base64payload), true);

        // 签发时间大于当前服务器时间验证失败
        if (isset($payload['iat']) && $payload['iat'] > time()) {
            throw new Exception('服务器时间验证失败！', 0, $payload);
        }

        // 过期时间小于当前服务器时间验证失败
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('服务器时间验证失败！', 0, $payload);
        }

        // 该 nbf 时间之前不接收处理该 TOKEN
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            throw new Exception('不接收处理该TOKEN', 0, $payload);
        }

        static::$isjwt = true;
        return static::$inData = $payload;
    }

    /**
     * 使用refresh token换取新的token
     * @param string $refresh_token
     * @param string|null $jwtkey
     * @return string
     * @throws Exception
     */
    public static function getNewToken(string $refresh_token, ?string $jwtkey = null):string
    {
        $payload = self::verifyToken($refresh_token, static::jwtkey($jwtkey));
        $new_token = self::getToken($payload, static::jwtkey($jwtkey));
        return $new_token;
    }

    /**
     * 同时生成token和refresh_token
     * @param array $payload
     * @param string|null $jwtkey
     * @return array
     */
    public static function authorizations(array $payload, ?string $jwtkey = null):array
    {
        $access_token = $payload;
        $access_token['scopes'] = 'role_access';  // token标识，请求接口的token
        $access_token['iss'] = 'jwt_admin';
        $access_token['iat'] = time(); //签发时间
        $access_token['exp'] = time() + 7200; // access_token过期时间,这里设置2个小时
        $access_token['nbf'] = time();  // 该时间之前不接收处理该Token  + 60

        $refresh_token = $payload;
        $refresh_token['scopes'] = 'role_refresh';  // token标识，刷新access_token
        $refresh_token['iss'] = 'jwt_admin';
        $refresh_token['iat'] = time(); //签发时间
        $refresh_token['exp'] = time() + (86400 * 30); // refresh_token过期时间,这里设置30天
        $refresh_token['nbf'] = time();  // 该时间之前不接收处理该Token  + 60

        $tokenList = [
            'access_token' => self::getToken($access_token, static::jwtkey($jwtkey)),
            'refresh_token' => self::getToken($refresh_token, static::jwtkey($jwtkey)),
            //'token_type' => 'bearer'  // token_type：表示令牌类型，该值大小写不敏感，这里用bearer
        ];

        return $tokenList;
    }

    /**
     * 获取 JWT 密钥
     * @param ?string $jwtkey
     * @return string
     */
    public static function jwtkey(?string $jwtkey = null): string
    {
        try {
            if (!empty($jwtkey)) return $jwtkey;

            // 优先读取配置文件
            $jwtkey = config('app.jwtkey');
            if (!empty($jwtkey)) return $jwtkey;

            // 再次读取数据配置
            $jwtkey = sysconf('data.jwtkey|raw');
            if (!empty($jwtkey)) return $jwtkey;

            // 自动生成新的密钥
            $jwtkey = md5(uniqid(strval(rand(1000, 9999)), true));
            sysconf('data.jwtkey', $jwtkey);
            return $jwtkey;

        } catch (\Exception $exception) {
            trace_file($exception);
            return 'Deadmin';
        }
    }

    /**
     * 输出模板变量
     * @param \think\admin\Controller $class
     * @param array $vars
     * @return void
     */
    public static function fetch(Controller $class, array $vars = [])
    {
        $ignore = array_keys(get_class_vars(Controller::class));
        foreach ($class as $name => $value) if (!in_array($name, $ignore)) {
            if (is_array($value) || is_numeric($value) || is_string($value) || is_bool($value) || is_null($value)) {
                $vars[$name] = $value;
            }
        }
        $class->success('获取变量成功！', $vars);
    }

    /**
     * 获取请求数据
     * @return array
     */
    public static function getInData(): array
    {
        return static::$inData;
    }

    /**
     * 获取输出数据
     * @return array
     */
    public static function getOutData(): array
    {
        return static::$outData;
    }

    /**
     * 设置输出数据
     * @param array $data
     * @return array
     */
    public static function setOutData(array $data = []): array
    {
        static::$rejwt = true;
        return static::$outData = $data;
    }

    /**
     * 是否返回令牌
     * @return boolean
     */
    public static function isRejwt(): bool
    {
        return self::$rejwt;
    }

    /**
     * 判断会话模式
     * @return bool
     */
    public static function isJwtMode(): bool
    {
        return boolval(Library::$sapp->session->get(static::skey));
    }

    /**
     * 切换会话模式
     * @return string
     */
    public static function setJwtMode(): string
    {
        if (isset(Library::$sapp->session)) {
            if (!static::isJwtMode()) {
                Library::$sapp->session->save(); // 保存原会话数据
                Library::$sapp->session->regenerate(); // 切换新会话编号
                Library::$sapp->session->set(self::skey, true);
            }
            return Library::$sapp->session->getId();
        } else {
            return md5(uniqid(strval(rand(0, 999))));
        }
    }

    /**
     * 生成数据签名
     * @param string $input 为 base64UrlEncode(header).".".base64UrlEncode(payload)
     * @param string $alg 算法方式
     * @param ?string $key 签名密钥
     * @return string
     */
    private static function withSign(string $input, string $alg = 'HS256', ?string $key = null): string
    {
        return CodeExtend::enSafe64(hash_hmac(self::signTypes[$alg], $input, static::jwtkey($key), true));
    }
}