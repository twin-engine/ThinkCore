<?php

declare (strict_types=1);

namespace think\admin\command;

use think\admin\service\BlockService;
use think\admin\Command;

/**
 * 上链数据管理指令
 * Class Block
 * @package app\blockchain\command
 */
class Block extends Command
{
    /**
     * 配置指令
     */
    protected function configure()
    {
        $this->setName('xadmin:blockchainall');
        $this->setDescription('BlockChain Data Synchronize for DualEngine');
    }

    /**
     * 任务执行处理
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function handle()
    {
        $message = $this->_list();
        $message .= $this->_gas();
        $this->setQueueSuccess($message);
    }

    /**
     * 同步上链数据列表
     * @param string $next
     * @param integer $done
     * @return string
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function _list(int $done = 0): string
    {
        $token = BlockService::instance()->check();
        $this->output->comment('开始获取上链数据');
        $result = BlockService::instance()->getBlockList();
        $total = count($result);
        if (is_array($result) && !empty($result)) {
            $dat = [];
            foreach (array_chunk($result, 100) as $hashs) {
                foreach ($hashs as $hash){
                    $transaction = BlockService::instance()->queryTransaction($token,$hash);
                    $dat['blockNumber'] = $transaction['blockNumber'];
                    $dat['content'] = base64_decode($transaction['transactionDO']['data']);
                    $dat['date'] = $transaction['transactionDO']['timestamp'];
                    $dat['hash'] = $hash;
                    sleep(1);
                    if (isset($dat['blockNumber'])) {
                        $this->queue->message($total, ++$done, "-> {$dat['hash']} {$dat['blockNumber']}");
                        BlockService::instance()->setBlock($dat);
                    }
                }
            }
        }
        $this->output->comment($done > 0 ? '上链数据获取完成' : '未获取到上链数据');
        $this->output->newLine();
        return "共获取 {$done} 个上链数据";
    }

    /**
     * 同步gas列表
     * @param string $next
     * @param integer $done
     * @return string
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     */
    public function _gas(int $done = 0): string
    {
        $token = BlockService::instance()->check();
        $result = BlockService::instance()->getBlockGasList();
        $total = count($result);
        if (is_array($result)) {
            $dat = [];
            $gasData = [];
            foreach (array_chunk($result, 100) as $res) {
                foreach ($res as $key=>$item){
                    if($item['hash'] && $item['code']==200){
                        $dat['gasUsed'] = BlockService::instance()->TransactionReceipt($token,$item['hash']);
                        $dat['hash'] = $item['hash'];
                        $company = $this->app->db->name('DataCompany')->where(['id' => $item['unitcode']])->find();
                        sleep(1);
                        if (isset($dat['gasUsed'])) {
                            $gasData['gas_total'] = $company['gas_total']-$dat['gasUsed'];
                            $gasData['gas_used'] = $company['gas_used'] + $dat['gasUsed'];
                            $this->queue->message($total, ++$done, "-> {$dat['hash']} {$dat['gasUsed']}");
                            BlockService::instance()->setBlock($dat);
                            $this->app->db->name('DataCompany')->where(['id'=>$item['unitcode']])->update($gasData);
                        }
                    }else{
                        unset($res[$key]);
                    }
                }
            }
        }
        $this->setQueueProgress('完成更新', null, -1);
        $this->output->newLine();
        if (empty($total)) {
            return ', 燃料费无需更新';
        } else {
            return ", 燃料费更新 {$total} 个";
        }
    }

}
