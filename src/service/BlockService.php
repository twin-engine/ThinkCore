<?php

declare (strict_types=1);

namespace think\admin\service;

use think\admin\Service;
use think\admin\extend\CodeExtend;
use think\admin\extend\HttpExtend;
use think\admin\model\SysBlockchain;
use think\admin\model\SysTenant;


/*
 * 蚂蚁区块链接口
 * Class Test
 */
class BlockService extends Service
{
    /**
     * 区块链数据缓存时间
     * @var integer
     */
    protected $expire = 1800;
    
    /**
     * 访问BlockToken
     * @var string
     */
    public $block_token = '';
    
    /**
     * 蚂蚁区块链接口地址
     * @var string
     */
    protected $url;
    
    /**
     * 链 ID 打开合约在链接上
     * $var string
     */
    protected $bizid;
    
    /**
     * 开放联盟链access-id
     */
    
    protected $accessId;
    
    /**
     * mykmsKeyId
     */
     
     protected $mykmsKeyId;
     
    /**
     * 蚂蚁租户ID
     */
     protected $ant_tenantid;
     
     
     /**
     * 控制器初始化
     */
    protected function initialize()
    {
        $this->url = syconfig('ANTBLOCK','antBlockUrl');
        $this->bizid = syconfig('ANTBLOCK','bizid');
        $this->accessId = syconfig('ANTBLOCK','accessid');
        $this->mykmsKeyId = syconfig('ANTBLOCK','mykmsKeyId');
        $this->ant_tenantid = syconfig('ANTBLOCK','tenantid');
        $this->antPrivate = syconfig('ANTBLOCK','antPrivate');
        $this->block_token = $this->getToken();
    }
    
    /*
    *与蚂蚁服务器握手，获取Token
    *
    */
    public function getToken()
    {
        if (!empty($this->block_token)) {
            return $this->block_token;
        }
        $this->block_token = $this->app->cache->get("block_token");
        if (!empty($this->block_token)) {
            return $this->block_token;
        }
        $res = [];
        $time = getUnixTimestamp();
        $data = $this->accessId.$time;
        $isOK = openssl_sign($data, $sign, $this->antPrivate, OPENSSL_ALGO_SHA256);
        if ($isOK) {
           $sig = bin2hex($sign);//转16进制
           $data = json_encode(array('accessId'=>$this->accessId, 'time'=>$time.'', 'secret'=>$sig));
           list($return_code, $return_content) = http_post_data($this->url.'/api/contract/shakeHand', $data);
           $res = json_decode($return_content,true);//echo $res['data'];//这个就是token
           if (!empty($res['data'])) {
                $this->app->cache->set("block_token", $res['data'], $this->expire);
            }
        }
        return $this->block_token = $res['data'];
    }
    
    /*
    *检测是否有足够的GAS
    */
    public function checkGas($tenant_id)
    {
        if(!$tenant_id) return [];
        $tenant = SysTenant::mk()->where(['id'=>$tenant_id])->where(['is_deleted'=>0])->find();
        if($tenant){
            if(($tenant['gas_total']-$tenant['gas_used']) < 25000){
                return false;
            }else{
                return true;
            }
        }else{
            return false;
        }
    }
    
    /*
    *存证Api
    *@params string $content 存证内容 字符串
    *@return array
    */
    public function existingEvidence($uid,$type,$content,$oldContent)
    {
        if(!$this->checkGas($this->app->request->header('TenantId'))) return false;
        $params = [//固定参数
            'orderId' => CodeExtend::uniqidNumber(10),
            'bizid' => $this->bizid,
            'account' => 'rotoos',
            'mykmsKeyId' => $this->mykmsKeyId,
            'method' => 'DEPOSIT',
            'accessId' => $this->accessId,
            'token' => $this->block_token,
            'gas' => 30000,
            'tenantid' => $this->ant_tenantid 
        ];
        $params['content'] = $content;//付入参数合并
        $params = json_encode($params);
        list($code, $result) = http_post_data($this->url.'/api/contract/chainCallForBiz', $params);
        //p($result);
        $res = json_decode($result,true);
        sleep(1);
        $gasUsed = $this->TransactionReceipt($this->block_token,$res['data']);//取得gas消费额
        $transaction = $this->queryTransaction($this->block_token,$res['data']);//取得所在区块高度
        $dat = [];
        $dat['tenant_id'] = $this->app->request->header('TenantId');
        $dat['created_by'] = $uid;
        $dat['type'] = $type?$type:1;
        $dat['stype'] = 1;
        $dat['md5value'] = $content;
        $dat['content'] = $oldContent;
        $dat['hash'] = $res['data'];
        $dat['gasUsed'] = $gasUsed;
        $dat['blockNumber'] = $transaction['blockNumber'];
        $dat['code'] = $res['code'];//200正常有hash，其他错误及原因
        if($dat['code']==200){
            $dat['qrcode'] = 'https://render.antfin.com/p/s/miniapp-web/?type=trans&from=antcloud&bizid='.$this->bizid.'&hash='.$dat['hash'];
            SysBlockchain::mk()->insert($dat);
        }
        if($gasUsed){
            $tentant = SysTenant::mk()->where(['id'=>$this->app->request->header('TenantId')])->findOrEmpty();
            //dec字段减去，inc字段增加
            $tentant->dec('gas_total',$gasUsed)->inc('gas_used',$gasUsed)->update(['updated_by'=>$uid]);
        }
        return $result;
    }
    
    /*
    *查询交易(区块值和内容)
    */
    public function queryTransaction($token,$hash)
    {
        $params = [   //固定的参数部分
            'bizid' => $this->bizid,
            'method' => 'QUERYTRANSACTION',
            'accessId' => $this->accessId,
            'token' => $token,
            'hash' => $hash
        ];
        list($code, $result) = http_post_data($this->url.'/api/contract/chainCall', json_encode($params));
        $res = json_decode($result,true);
        return json_decode($res['data'],true);
    }
    
    /*
    *查询交易回执
    */
    public function TransactionReceipt($token,$hash)
    {
        $params = [
            'bizid' => $this->bizid,
            'method' => 'QUERYRECEIPT',
            'accessId' => $this->accessId,
            'token' => $token,
            'hash'  => $hash
        ];
        list($code, $result) = http_post_data($this->url.'/api/contract/chainCall', json_encode($params));
        $res = json_decode($result,true);
        $r = json_decode($res['data'],true);
        return $r['gasUsed'];
    }
    
    /*
    *查询块头
    */
    public function BlockHeader($token,$requestStr)
    {
        $params = [
            'bizid' => $this->bizid,
            'requestStr' => '77207026',
            'method' => 'QUERYBLOCK',
            'accessId' => $this->accessId,
            'token' => $token
        ];
        list($code, $result) = http_post_data($this->url.'/api/contract/chainCall', json_encode($params));
        //echo $result;
        return $result;
    }
    
    /*
    *查询块体
    */
    public function BlockBody($token,$requestStr)
    {
        $params = [
            'bizid' => $this->bizid,
            'requestStr' => '77207026',
            'method' => 'QUERYBLOCKBODY',
            'accessId' => $this->accessId,
            'token' => $token
        ];
        list($code, $result) = http_post_data($this->url.'/api/contract/chainCall', json_encode($params));
        //echo $result;
        return $result;
    }
    
    /*
    *查询最新块高
    */
    public function NewBlockHeight($token)
    {
        $params = [
            'bizid' => $this->bizid,
            'method' => 'QUERYLASTBLOCK',
            'accessId' => $this->accessId,
            'token' => $token
        ];
        list($code, $result) = http_post_data($this->url.'/api/contract/chainCall', json_encode($params));
        //echo $result;
        return $result;
    }
    
    /*
    *查询账户
    */
    public function queryAccount($token)
    {
        $params = [
            'bizid' => $this->bizid,
            'requestStr' => "{\"queryAccount\":\"rotoos\"}",
            'method' => 'QUERYACCOUNT',
            'accessId' => $this->accessId,
            'token' => $token
        ];
        list($code, $result) = http_post_data($this->url.'/api/contract/chainCall', json_encode($params));
        //echo $result;
        return $result;
    }
    
    /*
    *异步调用 Solidity 合约 Api
    */
    public function solidityAdd($unitcode,$token)
    {
        $this->checkGas($unitcode);
        $params = [
            'orderId' => CodeExtend::uniqidNumber(10),
            'bizid' => $this->bizid,
            'account' => 'rotoos',
            'contractName' => '关联批次',
            'methodSignature' => 'insertPihao(string,string,string,string,string,string,string,string)',
            'inputParamListStr' => "['1000111','789878777223','我是个商品','img.jpg','8.90','20110202','商家名','2021-09-24']",
            'outTypes' => '[]',
            'mykmsKeyId' => $this->mykmsKeyId,
            'method' => 'CALLCONTRACTBIZASYNC',
            'accessId' => $this->accessId,
            'token' => $token,
            'gas' => 2000000,
            'tenantid' => $this->ant_tenantid 
        ];
        $params = json_encode($params);
        //p($params);
        list($code, $result) = http_post_data($this->url.'/api/contract/chainCallForBiz', $params);
        //p($result);
        //echo $result;
        /*if($result){
            $this->success('操作成功',$result);
        }else{
            $this->error('操作失败');
        }*/
    }
}