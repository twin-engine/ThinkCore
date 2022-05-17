<?php

declare (strict_types=1);

namespace think\admin\service;

use think\admin\Service;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Green\Green;
use think\admin\model\SysGreen;
use think\admin\model\SystemUser;
/**
 * 内容安全服务
 * 取值：
    porn：图片智能鉴黄
    terrorism：图片暴恐涉政
    ad：图文违规
    qrcode：图片二维码
    live：图片不良场景
    logo：图片logo
 * 文本垃圾检测结果的分类。取值：
    normal：正常文本
    spam：含垃圾信息
    ad：广告
    politics：涉政
    terrorism：暴恐
    abuse：辱骂
    porn：色情
    flood：灌水
    contraband：违禁
    meaningless：无意义
    harmful：不良场景（保护未成年场景，支持拜金炫富、追星应援、负面情绪、负面诱导等检测场景）
    customized：自定义（例如命中自定义关键词）
 * Class GreenService
 * @package think\admin\service
 */
class GreenService extends Service
{
    /**
     * AccessKey ID
     */
    protected $accesskey_id;

    /**
     * AccessKey Secret
     */
    protected $accesskey_secret;

    /**
     * 控制器初始化
     */
    protected function initialize()
    {
        $this->accesskey_id = syconfig('ALIBABA_GREEN','AccessKeyID');
        $this->accesskey_secret = syconfig('ALIBABA_GREEN','AccessKeySecret');
    }
    /**
     * 文本检测
     * @param int $id 上传id
     * @param int $userid 用户id
     * @param int $tenantid 租户id
     * @param array $task 检测任务
     */
    public function txtCheck(int $id, int $userid, int $tenantid, array $task)
    {
        try {
            AlibabaCloud::accessKeyClient($this->accesskey_id, $this->accesskey_secret)
                ->regionId('cn-shanghai')
                ->asDefaultClient();
            /**
             * 文本垃圾检测：antispam。
             **/
            
            $result = Green::v20180509()->textScan()
                ->body(json_encode(array('tasks' => array($task), 'scenes' => ['antispam'], 'bizType' => '业务场景')))
                ->request();
            $r = $result->toArray();
            //p($r);
            return $this->_insetGreen($id,$userid,$tenantid,$task['content'],$r);
            //p($result->toArray());
            //return $result->toArray();
        } catch (ClientException $exception) {
            throw new InvalidResponseException($exception->getMessage());
        } catch (ServerException $exception) {
            throw new InvalidResponseException($exception->getMessage());
            throw new InvalidResponseException($exception->getErrorCode());
            throw new InvalidResponseException($exception->getRequestId());
            throw new InvalidResponseException($exception->getErrorMessage());
        }
    }
    
    /**
     * 图片检测
     * @param int $id 图片上传id
     * @param int $userid 用户id
     * @param int $tenantid 租户id
     * @param array $task 检测任务
     */
    public function imageCheck(int $id, int $userid, int $tenantid, array $task)
    {
        try {
            AlibabaCloud::accessKeyClient($this->accesskey_id, $this->accesskey_secret)
                ->regionId('cn-shanghai')
                ->asDefaultClient();
        
            /* 设置待检测的图片，一张图片对应一个检测任务。
             * 多张图片同时检测时，处理时间由最后一张处理完的图片决定。
             * 通常情况下批量检测的平均响应时间比单张检测要长。一次批量提交的图片数越多，响应时间被拉长的概率越高。
             * 代码中以单张图片检测作为示例，如果需要批量检测多张图片，请自行构建多个检测任务。
             * OCR检测按照实际检测的图片张数*检测的卡证类型单价计费。
             */
            $response = Green::v20180509()->imageSyncScan()
                ->body(json_encode(array('tasks' => array($task), 'scenes' => ['porn', 'terrorism'], 'bizType' => '业务场景')))
                ->request();
            $r = $response->toArray();
            return $this->_insetGreen($id,$userid,$tenantid,$task['url'],$r);
            //return $response->toArray();
            //print_r($response->toArray());
            if (200 == $response->code) {
                $taskResults = $response->data;
                foreach ($taskResults as $taskResult) {
                    if (200 == $taskResult->code) {
                        $sceneResults = $taskResult->results;
                        foreach ($sceneResults as $sceneResult) {
                            $scene = $sceneResult->scene;
                            $suggestion = $sceneResult->suggestion;
                            // 根据scene和suggetion做相关的处理。
                            return ['scene'=>$scene,'suggestion'=>$suggestion];
                        }
                    } else {
                        return [0,'task process fail',$response->code];
                    }
                }
            } else {
                 return [0,'detect not success. code',$response->code];
            }
        } catch (Exception $exception) {
            throw new InvalidResponseException($exception->getMessage());
        }
    }
    
    /**
     * 文件检测
     * @param int $id 上传id
     * @param int $userid 用户id
     * @param int $tenantid 租户id
     * @param array $task 检测任务
     */
    public function fileCheck(int $id, int $userid, int $tenantid, array $task)
    {
        try {
            AlibabaCloud::accessKeyClient($this->accesskey_id, $this->accesskey_secret)
                ->regionId('cn-shanghai')
                ->asDefaultClient();
            /**
             * textScenes：检测内容包含文本时，指定检测场景，取值：antispam。
             * imageScenes：检测内容包含图片时，指定检测场景。
             */
            $result = Green::v20180509()->fileAsyncScan()
                ->body(json_encode(array(
                    'bizType' => '业务场景',
                    'textScenes' => ['antispam'],
                    'imageScenes' => ['porn', 'terrorism'],
                    'tasks' => array($task)
                )))
                ->request();
            $r = $result->toArray();
            return $this->_insetGreen($id,$userid,$tenantid,$task['url'],$r);
            //p($result->toArray());
            //return $result->toArray();
        } catch (ClientException $exception) {
            throw new InvalidResponseException($exception->getMessage());
        } catch (ServerException $exception) {
            throw new InvalidResponseException($exception->getMessage());
            throw new InvalidResponseException($exception->getErrorCode());
            throw new InvalidResponseException($exception->getRequestId());
            throw new InvalidResponseException($exception->getErrorMessage());
        }
    }
    
    /**
     * 视频检测
     * @param int $id 上传id
     * @param int $userid 用户id
     * @param int $tenantid 租户id
     * @param array $task 检测任务
     */
    public function videoCheck(int $id, int $userid, int $tenantid, array $task)
    {
        try {
            AlibabaCloud::accessKeyClient($this->accesskey_id, $this->accesskey_secret)
                ->regionId('cn-shanghai')
                ->asDefaultClient();
            // scenes：检测场景，支持指定多个场景。
            // callback、seed：用于回调通知，可选参数。
            $result = Green::v20180509()->videoAsyncScan()
                ->body(json_encode(array("tasks" => array($task),
                    "scenes" => ['porn', 'terrorism'],
                    'callback' => '回调地址',
                    'seed' => 'sqmla')))
                ->request();
            $r = $result->toArray();
            return $this->_insetGreen($id,$userid,$tenantid,$task['url'],$r);
            //p($result->toArray());
            //return $result->toArray();
        } catch (ClientException $exception) {
            throw new InvalidResponseException($exception->getMessage());
        } catch (ServerException $exception) {
            throw new InvalidResponseException($exception->getMessage());
            throw new InvalidResponseException($exception->getErrorCode());
            throw new InvalidResponseException($exception->getRequestId());
            throw new InvalidResponseException($exception->getErrorMessage());
        }
    }
    
    /**
     * 检测内容入库
     */
     private function _insetGreen(int $id, int $userid, int $tenantid, string $content, array $data)
     {
        if(!$data) return [];
        //上传的图文违规超过三次的将封锁此用户账号
        if(SysGreen::mk()->where(['create_user_id'=>$userid])->where('suggestion','<>','pass')->count()>3){
            SystemUser::mk()->where(['id'=>$userid])->update(['status'=>1]);
        }
        $dat = [];
        $state = false;
        if(count($data['data'][0]['results'])>1){
            foreach($data['data'][0]['results'] as $v){
                if($v['suggestion']!='pass' || $v['label']!='normal') $state = true;
                $dat[] = [
                    'field_id'       => $id,
                    'create_user_id' => $userid,
                    'tenant_id'    => $tenantid,
                    'content' => $content,
                    'data_id' => $data['data'][0]['dataId'],
                    'task_id' => $data['data'][0]['taskId'],
                    'label' => $v['label'],
                    'rate' => $v['rate'],
                    'scene' => $v['scene'],
                    'suggestion' => $v['suggestion'],
                    'status' => $v['suggestion']==='pass' ? 0 : 1
                ];
            }
        }else{
            if($data['data'][0]['results'][0]['suggestion']!='pass' || $data['data'][0]['results'][0]['label']!='normal') $state = true;
            $dat[] = [
                'field_id'       => $id,
                'create_user_id' => $userid,
                'tenant_id'    => $tenantid,
                'content' => $content,
                'data_id' => $data['data'][0]['dataId'],
                'task_id' => $data['data'][0]['taskId'],
                'label' => $data['data'][0]['results'][0]['label'],
                'rate' => $data['data'][0]['results'][0]['rate'],
                'scene' => $data['data'][0]['results'][0]['scene'],
                'suggestion' => $data['data'][0]['results'][0]['suggestion'],
                'status' => $data['data'][0]['results'][0]['suggestion']==='pass' ? 0 : 1
            ];
        }
        
        SysGreen::mk()->failException(true)->insertAll($dat);
        
        return $state;
     }

}