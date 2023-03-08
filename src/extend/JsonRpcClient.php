<?php
declare (strict_types=1);

namespace think\admin\extend;

use think\admin\Exception;

/**
 * JsonRpc 客户端
 * Class JsonRpcClient
 * @package think\admin\extend
 */
class JsonRpcClient
{
    /**
     * 请求ID
     * @var integer
     */
    private $id;

    /**
     * 服务端地址
     * @var string
     */
    private $proxy;

    /**
     * 请求头部参数
     * @var string
     */
    private $header;

    /**
     * JsonRpcClient constructor.
     * @param string $proxy
     * @param array $header
     */
    public function __construct(string $proxy, array $header = [])
    {
        $this->id = time();
        $this->proxy = $proxy;
        $this->header = $header;
    }

    /**
     * 执行 JsonRpc 请求
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws \think\admin\Exception
     */
    public function __call(string $method, array $params = [])
    {
        $options = [
            'ssl'  => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'method'  => 'POST',
                'header'  => join("\r\n", array_merge(['Content-type:application/json'], $this->header)),
                'content' => json_encode(['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => $this->id], JSON_UNESCAPED_UNICODE),
            ],
        ];
        try {
            // Performs the HTTP POST
            if ($fp = fopen($this->proxy, 'r', false, stream_context_create($options))) {
                $response = '';
                while ($line = fgets($fp)) $response .= trim($line) . "\n";
                [, $response] = [fclose($fp), json_decode($response, true)];
            } else {
                throw new Exception(lang("Unable connect: %s", [$this->proxy]));
            }
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage());
        }
        // Compatible with normal
        if (isset($response['code']) && isset($response['info'])) {
            throw new Exception($response['info'], intval($response['code']), $response['data'] ?? []);
        }
        // Final checks and return
        if (empty($response['id']) || $response['id'] != $this->id) {
            throw new Exception(lang("Error flag ( Request tag: %s, Response tag: %s )", [$this->id, $response['id'] ?? '-']), 0, $response);
        }
        if (is_null($response['error'])) return $response['result'];
        throw new Exception($response['error']['message'], intval($response['error']['code']), $response['result'] ?? []);
    }
}