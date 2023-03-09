<?php

declare (strict_types=1);

namespace think\admin\extend;

use rotoos\colorQrcode\Factory;
use rotoos\colorQrcode\QrCodePlus;

/**
 * 彩色二维码生成管理扩展
 * Class ColorQrcodeExtend
 * @package think\admin\extend
 */
class ColorQrcodeExtend
{
    /**
     * @var string
     */
    private $code;

    /**
     * 生成彩色二维码
     * @param array $colors 16进制颜色码[4个、9个]
     * @param string $text 二维码文本
     * @return string
     */
    public function createColorQrcode(array $colors, string $text): string
    {
        $this->code = md5($text);
        $color = Factory::color($colors);
        (new QrCodePlus)
            ->setText($text) //生成的文本
            ->setMargin(10)
            ->setOutput(function($handle){
                header('Content-Type: image/png');
                $path = './qrcode/';
                if(!is_dir($path)) mkdir($path,0777,true);
                $img = $path . $this->code . ".png"; //生成的文件地址
                imagepng($handle,$img);
            })
            ->output($color);
        return $qrcode = '/qrcode/'.$this->code.'.png';
    }
}