<?php

namespace think\admin\support\command;

use think\admin\Command;
use think\admin\Exception;
use think\admin\extend\PhinxExtend;
use think\admin\service\SystemService;
use think\console\input\Argument;
use think\console\input\Option;

/**
 * 生成数据安装包
 * Class Package
 * @package think\admin\support\command
 */
class Package extends Command
{
    /**
     * 系统指定配置
     * @return void
     */
    public function configure()
    {
        $this->setName('xadmin:package');
        $this->addOption('all', 'a', Option::VALUE_NONE, 'Packaging All Tables');
        $this->addArgument('table', Argument::OPTIONAL, 'Packaging Custom Tables', '');
        $this->setDescription('Generate System Install Package for ThinkAdmin');
    }

    /**
     * 生成系统安装数据包
     * @return void
     * @throws \think\admin\Exception
     */
    public function handle()
    {
        try {
            // 创建数据库迁移脚本目录
            $dirname = syspath('database/migrations');
            file_exists($dirname) or mkdir($dirname, 0755, true);
            // 开始创建数据库迁移脚本
            $this->output->writeln('--- 开始创建数据库迁移脚本 ---');
            if ($this->createPackage() && $this->createScheme()) {
                $this->setQueueSuccess('--- 数据库迁移脚本创建成功 ---');
            } else {
                $this->setQueueError('--- 数据库迁移脚本创建失败 ---');
            }
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            trace_file($exception);
            $this->setQueueError($exception->getMessage());
        }
    }

    /**
     * 创建数据表
     * @return boolean
     * @throws \Exception
     */
    private function createScheme(): bool
    {
        $this->setQueueMessage(4, 3, '开始创建数据表创建脚本！');
        $phinx = PhinxExtend::create2phinx();
        $target = syspath("database/migrations/{$phinx['file']}");
        if (file_put_contents($target, $phinx['text']) !== false) {
            $this->setQueueMessage(4, 4, '成功创建数据表创建脚本！');
            return true;
        } else {
            $this->setQueueMessage(4, 4, '创建数据表创建脚本失败！');
            return false;
        }
    }

    /**
     * 创建数据包
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function createPackage(): bool
    {
        $this->setQueueMessage(4, 1, '开始创建数据包安装脚本！');
        // 接收指定打包数据表
        $tables = str2arr(strtr($this->input->getArgument('table'), '|', ','));
        if (empty($tables) && $this->input->getOption('all')) {
            [$tables] = SystemService::getTables();
        }
        // 创建数据包安装脚本
        $phinx = PhinxExtend::create2package($tables);
        $target = syspath("database/migrations/{$phinx['file']}");
        if (file_put_contents($target, $phinx['text']) !== false) {
            $this->setQueueMessage(4, 2, '成功创建数据包安装脚本！');
            return true;
        } else {
            $this->setQueueMessage(4, 2, '创建数据包安装脚本失败！');
            return false;
        }
    }
}