<?php

declare(strict_types=1);

namespace think\admin\extend;

use Symfony\Component\Process\Process;

/**
 * 基础支持
 * 服务器物理信息获取
 * @class PhysicalExtend
 * @package think\admin\extend
 */
abstract class PhysicalExtend
{
    /**
     * 获取服务地址
     * @return string
     */
    public static function getServer(): string
    {
        return base64_decode('aHR0cHM6Ly9wbHVnaW4udGhpbmthZG1pbi50b3Av');
    }

    /**
     * 获取系统序号
     * @return string
     */
    public static function getSysId(): string
    {
        static $sysid;
        if ($sysid) return $sysid;
        [$cpuid, $macid, $root] = ['', '', dirname(__DIR__, 4)];
        if (file_exists($file = "{$root}/vendor/binarys.php")) {
            if (($info = include $file) && is_array($info)) {
                [$cpuid, $macid] = [$info['cpu'] ?? '', $info['mac'] ?? ''];
            }
        }
        $cpuid = $cpuid ?: static::getCpuId();
        $macid = $macid ?: static::getMacId();
        return $sysid = strtoupper(md5("{$macid}#{$cpuid}#{$root}"));
    }

    /**
     * 获取处理器序号
     * @return string
     */
    public static function getCpuId(): string
    {
        static $cpuid;
        if ($cpuid) return $cpuid;
        $command = self::isWin() ? 'wmic cpu get ProcessorID' : 'dmidecode -t processor | grep ID';
        $process = Process::fromShellCommandline($command);
        $process->run(static function ($type, $line) use ($process, &$cpuid) {
            if (preg_match('|[0-9A-F]{16}|', preg_replace('#[^:\w\n]#', '', $line), $match)) {
                ($cpuid = strtoupper($match[0])) && $process->stop();
            }
        });
        if (empty($cpuid)) {
            $tmpfile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '.deadmin.cpuid';
            if (!is_file($tmpfile) || !($cpuid = file_get_contents($tmpfile))) {
                $cpuid = strtoupper(substr(md5(uniqid(strval(rand(1, 100)))), -16));
                @file_put_contents($tmpfile, $cpuid);
            }
        }
        return $cpuid;
    }

    /**
     * 获取MAC地址
     * @return string
     */
    public static function getMacId(): string
    {
        static $macid;
        if ($macid) return $macid;
        $process = Process::fromShellCommandline(self::isWin() ? 'ipconfig /all' : 'ifconfig -a');
        $process->run(static function ($type, $line) use ($process, &$macid) {
            $value = preg_replace('#[^:\-\w\n]#', '', $line);
            if (preg_match("#((00|FF)[:-]){5}(00|FF)#i", $value)) return;
            if (preg_match('#([0-9A-F]{2}[:-]){5}[0-9A-F]{2}#', $value, $match)) {
                ($macid = $match[0]) && $process->stop();
            }
        });
        if (empty($macid)) {
            $tmpfile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '.deadmin.macid';
            if (!is_file($tmpfile) || !($macid = file_get_contents($tmpfile))) {
                @file_put_contents($tmpfile, $macid = static::randMacAddress());
            }
        }
        return $macid = strtoupper(strtr($macid, ':', '-'));
    }

    /**
     * 判断运行环境
     * @return boolean
     */
    public static function isWin(): bool
    {
        return defined('PHP_WINDOWS_VERSION_BUILD');
    }

    /**
     * 生成随机MAC地址
     * @return string
     */
    private static function randMacAddress(): string
    {
        $attr = [
            mt_rand(0x00, 0x7f), mt_rand(0x00, 0x7f), mt_rand(0x00, 0x7f),
            mt_rand(0x00, 0x7f), mt_rand(0x00, 0xff), mt_rand(0x00, 0xff)
        ];
        return join('-', array_map(function ($v) {
            return sprintf('%02X', $v);
        }, $attr));
    }
}