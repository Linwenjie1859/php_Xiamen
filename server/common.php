<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (config) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

/*
*	检查一个字符串或数组是否为空
*	@
*/
function is_empty($str)
{
    return empty($str)?false:true;
}

/*
*
*	比较函数,$oper为比较运算符
*/
function compare($num1,$num2,$oper=">")
{
    switch ($oper) {
        case '==':
            $result = ($num1==$num2);
            break;
        case '===':
            $result = ($num1===$num2);
            break;
        case '<':
            $result = ($num1<$num2);
            break;
        case '<=':
            $result = ($num1<=$num2);
            break;
        case '!=':
            $result = ($num1!=$num2);
            break;
        case '<>':
            $result = ($num1<>$num2);
            break;
        case '>=':
            $result = ($num1>=$num2);
            break;
        case '>':
            $result = ($num1>$num2);
            break;
        default:
            $result = false;
            break;
    }
    return $result;
}

/*
*	检查两个指是否相等
*/
function eq($arg1,$arg2)
{
    return ($arg1 == $arg2)?true:false;
}


/*
*	检查两个指是否相等
*/
function gt($arg1,$arg2)
{
    return ($arg1 > $arg2)?true:false;
}

/*
*	检查两个指是否相等
*/
function lt($arg1,$arg2)
{
    return ($arg1 < $arg2)?true:false;
}

/*
*	检查两个指是否相等
*/
function ge($arg1,$arg2)
{
    return ($arg1 >= $arg2)?true:false;
}

/*
*	检查两个指是否相等
*/
function le($arg1,$arg2)
{
    return ($arg1 <= $arg2)?true:false;
}

/*
*	获取目录下的文件名
*/
function get_filename($url)
{
    $filename = '';
    $arr = explode("/",$url);
    $arr_count = count($arr);
    if ($arr_count<2)
    {
        $filename = $url;
    }
    else
    {
        $filename = $arr[$arr_count-1];
    }
    return $filename;
}

/*
*	格式化响应结构体
*	@param		string		$struct		结构体名称
*	@param		array		$data			数据
*	@param		bool		$traversal	是否遍历
*/
function format_struct($struct,$data,$traversal = false)
{
    $stand = config('struct');
    $stand = $stand[$struct];
    if (!$stand) return false;
    $rs = array();
    if (!$traversal)
    {
        foreach($stand as $k)
        {
            $rs[$k] = isset($data[$k])?$data[$k]:'';
        }
    }
    else
    {
        foreach($data as $key=>$value)
        {
            foreach($stand as $k)
            {
                $rs[$key][$k] = isset($value[$k])?$value[$k]:'';
            }
        }
    }
    return $rs;
}

//生成短链接
function getShortUrl($url)
{
    $gate = 'http://api.t.sina.com.cn/short_url/shorten.json';
    $appkey = '1721829182';
    $rs = "{$gate}?source={$appkey}&url_long={$url}";
    $arr = json_decode(file_get_contents($rs), true);
    return $arr[0]['url_short'];
}

//发送短信
function sendsms($mobile,$content)
{
    Vendor('SMS.NSMSHelper');
    return NSMSHelper::sendSMS($mobile, $content);
}

//判断相应图片是否存在,不存在则赋值为空
function imgCheck($url,$default='')
{
    if (is_file($url) || is_file(ROOT_PATH.$url) ) {
        return $url;
    }
    else {
        return $default;
    }
}

/*
/**
*	数组值全部转为字符串
*/
function array_values_to_string(&$array)
{
    foreach($array as $key=>&$value)
    {
        if (is_array($value))
        {
            array_values_to_string($value);
        }
        else
        {
            $value = (string) $value;
        }
    }
    // return $array;
}

/*
*	递归创建目录
*	必须是绝对目录
*/

function xmkdir($pathurl)
{
    $path = "";
    $str = explode("/",$pathurl);
    foreach($str as $dir)
    {
        if (empty($dir)) continue;
        $path .= "/".$dir;
        if (!is_dir($path))
        {
            mkdir($path);
        }
    }
}

/*
*	用数组中的某个值重设数组键值
*
*/
function reset_array_key($array,$key,$field = '')
{
    $_array = array();
    foreach($array as $value)
    {
        $_array[$value[$key]] = !empty($field)?$value[$field]:$value;
    }
    return $_array;
}

/*
*	随机码生成
*	@param			int 		$length		长度
*/
function randcode($length)
{
    if (SMSDEBUG) return 5555;
    $start = pow(10,($length-1));
    $end = pow(10,$length)-1;
    return rand($start,$end);
}

/*	查询物流明细
	logistics 物流名称
	logistics_no 物流单号
*/
function getExpress($logistics,$logistics_no)
{
    if ($logistics && $logistics_no) {

        $exp = new Express();
        $express = $exp->getorder($logistics,$logistics_no);
    }
    if ($express['status']!='200') $express['data'][1]['context'] = '暂无物流信息';
    return $express;
}
/**
 * 判断是否是在微信浏览器里
 * @return boolean
 */
function isWeixinBrowser(){
    $agent = $_SERVER ['HTTP_USER_AGENT'];
    if (! strpos ( $agent, "MicroMessenger" )) {
        return false;
    }
    return true;
}
function get_open_id($appid,$appsecret){
    $isWeixinBrowser = isWeixinBrowser ();
    vendor('Wxpay.WxPay#pub#config');
    if (! $isWeixinBrowser || $appid) {
        return false;
    }
    $param ['appid'] = $appid;
    $callback = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    if (! isset ( $_GET ['getOpenId'] )) {
        $param ['redirect_uri'] = $callback . '&getOpenId=1';
        $param ['response_type'] = 'code';
        $param ['scope'] = 'snsapi_base';
        $param ['state'] = 123;
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?' . http_build_query ( $param ) . '#wechat_redirect';
        redirect ( $url );
    } elseif ($_GET ['state']) {
        $param ['secret'] = $appsecret;
        $param ['code'] = input ( 'code' );
        $param ['grant_type'] = 'authorization_code';

        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query ( $param );
        $content = file_get_contents ( $url );
        $content = json_decode ( $content, true );
        if($content['openid']){
            return $content['openid'];
        }
        return false;
    }
}

/*
*	日志记录
*/
function eblog($tag,$content,$file='')
{
    $path = ROOT_PATH."/tmp/log/index/";
    if (is_array($content))
    {
        ob_start();
        print_r($content);
        $content = ob_get_contents();
        ob_end_clean();
        $content = "\n".$content."\n";
    }
    if (!is_dir($path)) xmkdir($path);
    if ($file)
    {
        $log_file = $path.$file.date('Ymd').".log";
    }
    else
    {
        $log_file = $path.date('Ymd').".log";
    }

    $log = "[".date("YmdHis")."] ".$tag.":".$content;
    $f = fopen($log_file,"ab+");
    fwrite($f,$log."\n");
    fclose($f);
}

/*
*	状态描述
*/
function status_desc($type = 'STATUS',$status)
{
    $status_arr = config($type);
    if (!$status_arr)
    {
        return false;
    }
    return $status_arr[$status];
}

/*
*	视图模板中输出时间
*/
function vtime($format="Y-m-d",$time)
{
    if (!$time)
    {
        return "-";
    }
    return date($format,$time);
}

/**
 * 订单号生产
 * @param $prefix  订单号前缀
 * @return string
 */
function createOrderSn($prefix){
    return $prefix.date("YmdHis").rand(1000,9999);
}


function vpost($url, $data)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, "Content-type:application/json");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    //curl_setopt($curl, CURLOPT_REFERER, $_SERVER['HTTP_REFERER']);
    curl_setopt($curl, CURLOPT_REFERER, 'http://www.fhtoto.com/index.html');
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    curl_setopt($curl,  CURLOPT_SSLVERSION,6);//TLSv1.2
    $response = curl_exec($curl);
    if (curl_errno($curl)) {

        exit;
    }
    curl_close($curl);
    return $response;
}

//二维码生产
function get_code($url){
    Vendor("phpqrcode.phpqrcode");
    // 纠错级别：L、M、Q、H
    $level = 'L';
    // 点的大小：1到10,用于手机端4就可以了
    $size = 4;
    // 下面注释了把二维码图片保存到本地的代码,如果要保存图片,用$fileName替换第二个参数false
    $path = ROOT_PATH;
    // 生成的文件名
    $file = "/tmp/upload/".time().rand(10000,99999).'.png';
    $fileName = $path.$file;
    QRcode::png($url, $fileName, $level, $size);
    return $file;
}

function create_uuid($prefix = ""){    //可以指定前缀
    $str = md5(uniqid(mt_rand(), true));
    $uuid  = substr($str,0,8) . '-';
    $uuid .= substr($str,8,4) . '-';
    $uuid .= substr($str,12,4) . '-';
    $uuid .= substr($str,16,4) . '-';
    $uuid .= substr($str,20,12);
    return $prefix . $uuid;
}

//获取IP地址
function getIP() {
    if (getenv('HTTP_CLIENT_IP')) {
        $ip = getenv('HTTP_CLIENT_IP');
    }
    elseif (getenv('HTTP_X_FORWARDED_FOR')) {
        $ip = getenv('HTTP_X_FORWARDED_FOR');
    }
    elseif (getenv('HTTP_X_FORWARDED')) {
        $ip = getenv('HTTP_X_FORWARDED');
    }
    elseif (getenv('HTTP_FORWARDED_FOR')) {
        $ip = getenv('HTTP_FORWARDED_FOR');

    }
    elseif (getenv('HTTP_FORWARDED')) {
        $ip = getenv('HTTP_FORWARDED');
    }
    else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

/**
 * base64转图片
 * @param $img
 * @return int
 */
function base64ToImg($img,$path){
    $img = base64_decode($img);
    $path_new = ROOT_PATH.$path;
    if (!is_dir($path_new))
    {
        xmkdir($path_new);
    }
    $filename = $path.create_uuid().'.jpg';
    $rs = file_put_contents(ROOT_PATH.$filename, $img);//返回的是字节数
    return $filename;
}

/**
 * 发送HTTP请求方法
 * @param  string $url    请求URL
 * @param  array  $params 请求参数
 * @param  string $method 请求方法GET/POST
 * @return mixed  $data   响应数据
 */
function http($url, $params, $method = 'POST', $header = array(), $timeout = 10){
    $opts = array(
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => $header
    );
    /* 根据请求类型设置特定参数 */
    switch(strtoupper($method)){
        case 'GET':
            $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
            break;
        case 'POST':
            //判断是否传输文件
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_POST] = 1;
            $opts[CURLOPT_POSTFIELDS] = $params;
            break;
        default:
            return '不支持的请求方式！';
    }
    /* 初始化并执行curl请求 */
    $ch = curl_init();
    curl_setopt_array($ch, $opts);
    $data  = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if($error) {
        return '请求发生错误：'.$error;
    }
    return  $data;
}