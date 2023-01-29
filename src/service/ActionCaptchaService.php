<?php

declare (strict_types=1);
namespace think\admin\service;

use think\admin\Service;
use Fastknife\Exception\ParamException;
use Fastknife\Service\ClickWordCaptchaService;
use Fastknife\Service\BlockPuzzleCaptchaService;
use think\exception\HttpResponseException;
use think\Response;

/**
 * 行为验证码服务
 * Class ActionCaptchaService
 * @package think\admin\service
 */
class ActionCaptchaService extends Service
{
    /**
     * 获取code
     * @param $captchaType
     * @return void
     */
    public function getCode($captchaType)
    {
        try {
            $captchaService = $this->getCaptchaService($captchaType);
            $data = $captchaService->get();
        } catch (\Exception $e) {
            $this->repError($e->getMessage());
        }
        $this->repSuccess($data);
    }

    /**
     * 一次验证
     * @param $captchaType
     * @param $token
     * @param $pointJson
     * @return void
     */
    public function check($captchaType,$token,$pointJson)
    {
        try {
            $captchaService = $this->getCaptchaService($captchaType);
            $captchaService->check($token,$pointJson);
        } catch (\Exception $e) {
            $this->repError($e->getMessage());
        }
        $this->repsuccess([]);
    }

    /**
     * 二次验证
     * @param $captchaType
     * @param $token
     * @param $pointJson
     * @return void
     */
    public function verification($captchaType,$token,$pointJson)
    {
        try {
            $captchaService = $this->getCaptchaService($captchaType);
            $captchaService->verification($token,$pointJson);
        } catch (\Exception $e) {
            $this->repError($e->getMessage());
        }
        $this->repSuccess([]);
    }

    /**
     * @param $captchaType
     * @return BlockPuzzleCaptchaService|ClickWordCaptchaService
     */
    protected function getCaptchaService($captchaType)
    {
        $config = $this->app->config->get('captcha');
        switch ($captchaType) {
            case "clickWord":
                $captchaService = new ClickWordCaptchaService($config);
                break;
            case "blockPuzzle":
                $captchaService = new BlockPuzzleCaptchaService($config);
                break;
            default:
                throw new ParamException('captchaType参数不正确！');
        }
        return $captchaService;
    }

    /**
     * @param $data
     * @return mixed
     */
    protected function repSuccess($data)
    {
        $response = [
            'error' => false,
            'repCode' => '0000',
            'repData' => $data,
            'repMsg' => null,
            'success' => true,
        ];
        throw new HttpResponseException(Response::create($response, 'json'));
    }

    /**
     * @param $msg
     * @return mixed
     */
    protected function repError($msg)
    {
        $response = [
            'error' => true,
            'repCode' => '6111',
            'repData' => null,
            'repMsg' => $msg,
            'success' => false,
        ];
        throw new HttpResponseException(Response::create($response, 'json'));
    }


}
