<?php


declare (strict_types=1);

namespace think\admin\tracking;


use think\admin\Service;
use think\admin\tracking\Tracking\Api;
use think\admin\tracking\Tracking\Webhook;

/**
 * 国际物流接口(https://www.51tracking.com/)
 */
class TrackingLogistics extends Service
{
    /**
     * api
     * @var string
     */
    private $api;
    
    /**
     * 安全邮箱
     */
    private $verifyEmail;
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
        $this->api = syconfig('TrackingLogistics','Api');
        $this->verifyEmail = syconfig('TrackingLogistics','verifyEmail');
        
        $this->api = new Api($this->api);
        #sandbox model
        $this->api->sandbox = false;
        
        $this->webhook = new Webhook();
    }
    
    /**
     * 获取物流商
     */
     public function getCarrierList()
     {
          /*$response = $this->api->courier();
          p($response);*/
          return $this->api->courier();
     }
    /**
     * 创建面单
     */
    public function Create(array $data)
    {
        /*$data = [
            ["tracking_number" => "YT2205421266056615", "courier_code" => "yunexpress"],
            ["tracking_number" => "303662548678", "courier_code" => "qichen"],
        ];
        $response = $this->api->create($data);*/
        return $this->api->create($data);
    }
    
    /**
     * 获取查询结果
     */
     public function GetInfo(array $data)
     {
         /*$data = ["tracking_numbers" => "YT2205421266056615,303662548678"];
         $response = $this->api->get($data);
         p($response);*/
         return $this->api->get($data);
     }
    /**
     * 修改code
     */
     public function UpdateCode(array $data)
     {
         /*$data = ["num" => "RP325552475CN", "express" => "china-post", "new_express" => "china-ems"];
         $response =  $this->api->modifyCourier($data);*/
         return $this->api->modifyCourier($data);
     }
    /**
     * 删除面单
     */
     public function Delete(array $data)
     {
         /*$data = [["tracking_number"=>"YT2205421266056614", "courier_code"=>"yunexpress"]];
         $response = $this->api->delete($data);*/
         return $this->api->delete($data);
     }
     
     /**
      * Webhoo
      */
      public function Webhook()
      {
        $response = $this->webhook->get($this->verifyEmail);
        p($response);
        # Write the push content to the log file, note: read and write permissions are required
        //file_put_contents(__DIR__."/webhook.txt",$response."\r\n",FILE_APPEND);

        # If you pass the data review logic and return a 200 status code, here is just a simple example
        if(!empty($response)) echo "200";
        
        exit;
      }
}