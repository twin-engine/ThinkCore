<?php

declare (strict_types=1);

namespace think\admin\service;

use think\admin\Service;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use think\admin\model\SysEmail;
/**
 * 邮件服务
 * Class EmailService
 * @package think\admin\service
 */
class EmailService extends Service
{
    /**
     * emailHost
     */
    protected $emailHost;

    /**
     * emailUsername
     */
    protected $emailUsername;
    
    /**
     * emailPassword
     */
    protected $emailPassword;
    
    /**
     * emailPort
     */
    protected $emailPort;
    
    /**
     * emailFrom
     */
    protected $emailFrom;
    
    /**
     * emailFromName
     */
    protected $emailFromName;

    /**
     * 控制器初始化
     */
    protected function initialize()
    {
        $this->emailHost = config('EMAIL','emailHost');
        $this->emailUsername = config('EMAIL','emailUsername');
        $this->emailPassword = config('EMAIL','emailPassword');
        $this->emailPort = config('EMAIL','emailPort');
        $this->emailFrom = config('EMAIL','emailFrom');
        $this->emailFromName = config('EMAIL','emailFromName');
    }
    
    /**
     * 邮件发送
     */
    public function sendEmail($to, $subject='',$content='',$addAttachment='',$code='')
    {
        $mail = new PHPMailer(true);

        try {
            //Server settings
            //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //启用详细调试输出
            $mail->isSMTP();                                            //使用 SMTP 发送
            $mail->CharSet  = 'UTF-8';
            $mail->Host       = $this->emailHost;                     //设置SMTP服务器通过
            $mail->SMTPAuth   = true;                                   //启用SMTP认证
            $mail->Username   = $this->emailUsername;                     //SMTP 用户名
            $mail->Password   = $this->emailPassword;                               //SMTP 密码
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //启用隐式 TLS 加密
            $mail->Port       = $this->emailPort;                                    //要连接的TCP端口；如果您设置了 `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`，则使用 587 
            
            //Recipients
            if(is_array($to)){
            foreach ($to as $v){
                    $mail->addAddress($v);
                }
            }else{
                $mail->addAddress($to);
            }
            
            $mail->setFrom($this->emailFrom, $this->emailFromName);
        
            //Attachments
            if (!empty($addAttachment)){
                $mail->addAttachment($addAttachment);
            }
            
            //Content
            $mail->isHTML(true);                                  //Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $content;
            
            
            if($mail->send()){
                $state = 1;
                $message = '邮件发送成功！';
            }else{
                $state = 0;
                $message = '邮件发送失败，请稍候再试！';
            }
            
            $dat = [];
            if(is_array($to)){
            foreach ($to as $v){
                  $value['email'] =  $v;
                  $value['code'] = $code;
                  $value['content'] = $content;
                  $value['result'] = $message;
                  $value['status'] = $state;
                  array_push($dat, $value);
                }
            }else{
                $dat['email'] =  $to;
                $dat['code'] = $code;
                $dat['content'] = $content;
                $dat['result'] = $message;
                $dat['status'] = $state;
            }
            
            SysEmail::mk()->insertAll($dat);
            
            return [$state, $message, ['time' => time()]];
            
        } catch (Exception $e) {
            
            return [0, '邮件发送失败，请稍候再试！{$mail->ErrorInfo}', []];
            
        }
    }
    
    /**
     * 验证邮件验证码
     * @param string $code 验证码
     * @param string $email 邮箱验证
     * @return boolean
     */
    public function checkVerifyCode(string $code, string $email): bool
    {
        $cache = $this->app->cache->get(md5("code-{$email}"), []);
        return is_array($cache) && isset($cache['code']) && $cache['code'] == $code;
    }

    /**
     * 发送邮箱验证码
     * @param string $email 邮箱验证码
     * @param integer $wait 等待时间
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function sendVerifyCode(string $email, int $wait = 60): array
    {
        $content = '您的验证码为{code}，请在十分钟内完成操作！';
        $cache = $this->app->cache->get($ckey = md5("code-{$email}"), []);
        // 检查是否已经发送
        if (is_array($cache) && isset($cache['time']) && $cache['time'] > time() - $wait) {
            $dtime = ($cache['time'] + $wait < time()) ? 0 : ($wait - time() + $cache['time']);
            return [1, '邮箱验证码已经发送！', ['time' => $dtime]];
        }
        // 生成新的验证码
        [$code, $time] = [rand(1000, 9999), time()];
        $this->app->cache->set($ckey, ['code' => $code, 'time' => $time], 600);
        // 尝试发送邮件内容
        [$state] = $this->sendEmail($email, '您的邮件验证码', preg_replace_callback("|{(.*?)}|", function ($matches) use ($code) {
            return $matches[1] === 'code' ? $code : $matches[1];
        }, $content),'',$code);
        if ($state) return [1, '邮件验证码发送成功！', [
            'time' => ($time + $wait < time()) ? 0 : ($wait - time() + $time)],
        ]; else {
            $this->app->cache->delete($ckey);
            return [0, '邮件发送失败，请稍候再试！', []];
        }
    }
    
    /**
     * 发送邮件通知公告
     * @param array $email 邮箱
     * @param string $title 主题
     * @param string $content 内容
     * @param integer $wait 等待时间
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function sendNotice(array $email, string $title, string $content, int $wait = 60): array
    {
        // 尝试发送邮件内容
        [$state] = $this->sendEmail($email, $title, $content,'','');
        if ($state) return [1, '邮件发送成功！', []]; else {
            return [0, '邮件发送失败，请稍候再试！', []];
        }
    }
}