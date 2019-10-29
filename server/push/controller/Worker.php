<?php

namespace app\push\controller;

use think\Log;
use think\worker\Server;

class Worker extends Server
{

    protected $worker;
    protected $mqtt;
    protected $socket = 'websocket://0.0.0.0:2346';
    protected $context = [
        'ssl' => [
            'local_cert' => '/home/wwwroot/wisdomlights/web/public/cert/ssl.pem',
            'local_pk' => '/home/wwwroot/wisdomlights/web/public/cert/ssl.key',
            'verify_peer' => false,
        ]
    ];
    protected $transport = 'ssl';
    protected $processes = 1;
    protected $uidConnections = array();

    /**
     * 收到信息
     * @param $connection
     * @param $data
     */
    public function onMessage($connection, $data)
    {
        eblog("",$data,"test");
        $data = json_decode($data,true);
        if($data['topic'] == 'login'){

            if(!isset($connection->uid))
            {
                // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
                $connection->uid = $data['content'];
                /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
                 * 实现针对特定uid推送数据
                 */
                $this->uidConnections[$connection->uid] = $connection;
//                $connection->send('login success, your uid is ' . $connection->uid);
            }
        }else {
            $this->mqtt->publish($data['topic'], $data['content']);
        }
    }

    /**
     * 当连接建立时触发的回调函数
     * @param $connection
     */
    public function onConnect($connection)
    {
//        eblog("开始连接",$connection,"test");
    }

    /**
     * 当连接断开时触发的回调函数
     * @param $connection
     */
    public function onClose($connection)
    {
//        eblog("断开连接",$connection,"test");
        if(isset($connection->uid))
        {
            // 连接断开时删除映射
            unset($this->uidConnections[$connection->uid]);
        }
    }
    /**
     * 当客户端的连接上发生错误时触发
     * @param $connection
     * @param $code
     * @param $msg
     */
    public function onError($connection, $code, $msg)
    {
        echo "error $code $msg\n";
    }

    /**
     * 每个进程启动
     * @param $worker
     */
    public function onWorkerStart($worker)
    {
        $option['client_id'] = '123123'.rand(10000,999999);
        $option['username'] = 'adminWu';
        $option['password'] = '123456';
        $this->mqtt = new \Workerman\Mqtt\Client('mqtt://39.98.183.239:1883',$option);
        $this->mqtt->onConnect = function($mqtt) {
            echo "connect success";

            $subarr = ['SmartStreeLamp/POST/dailyTask','SmartStreeLamp/POST/warning','SmartStreeLamp/POST/collect','SmartStreeLamp/POST/rtuStatus','SmartStreeLamp/POST/systemInfo',];
            foreach ($subarr as $value){
                $this->mqtt->subscribe($value);
            }
        };
        $this->mqtt->onMessage = function($topic, $content){
            eblog($topic, $content,"test");
            switch ($topic) {
                case "SmartStreeLamp/POST/dailyTask":
//                        $this->dailyTask($content);
                    break;
                case "SmartStreeLamp/POST/warning":
//                        warning($content);
                    break;
                case "SmartStreeLamp/POST/collect":
                    $this->collect($content);
                    break;
                case "SmartStreeLamp/POST/rtuStatus":
                    $this->rtuStatus($content);
                    break;
                case "SmartStreeLamp/POST/systemInfo":
                    $this->systemInfo($content);
                    break;
            }

            $data = json_decode($content,true);

            $res['topic'] = $topic;
            $res['content'] = $content;
            $this->sendMessageByUid($data['ID'],json_encode($res));
        };
        $this->mqtt->connect();
    }

    //传感器信息采集
    public function collect($content){
        $content = json_decode($content,true);

        $data['street_light_id'] = $content['ID'];
        $data['tempertrue'] = $content['Data']['Tempertrue'];
        $data['humidity'] = $content['Data']['Humidity'];
        $data['pm25'] = $content['Data']['PM25'];
        $data['pm10'] = $content['Data']['PM10'];
        $data['collect_time'] = strtotime($content['Data']['Time']);
        $data['add_time'] = time();

        $rs = db("street_light_collect")->insertGetId($data);
//        echo $rs;
    }

    //设备状态变更
    public function rtuStatus($content){
        $content = json_decode($content,true);

        $arr = array('LEDSwitch'=>'Led','FanSwitch'=>'Fan','DisPlaySwitch'=>'DisPlay','SensorSwitch'=>'Sensor');

        foreach ($content['Data'] as $k => $v){
            $status = $v == '1' ? '0' : '1';
            $device = db("device")->where(array('key'=>$arr[$k]))->select();

            $deviceid = '';
            foreach ($device as $value){
                $deviceid = $value['id'].',';
            }

            db("street_light_device")->where(array('street_light_id'=>$content['ID'],'device_id'=>array('in',$deviceid)))->update(array("status"=>$status));
        }
    }

    public function systemInfo($content){
        $content = json_decode($content,true);

        $data['men_percent'] = $content['Data']['menPercent'];
        $data['disk_precent'] = $content['Data']['diskPrecent'];
        $data['net_work_received'] = $content['Data']['netWorkReceived'];
        $data['cpu_percent'] = $content['Data']['cpuPercent'];
        $data['net_work_send'] = $content['Data']['netWorkSend'];
        $data['last_update_time'] = strtotime($content['Data']['Time']);
        db("street_light")->where(array('street_light_id'=>$content['ID']))->update($data);
    }

    // 向所有验证的用户推送数据
    function broadcast($message)
    {

        foreach($this->uidConnections as $connection)
        {
            $connection->send($message);
        }
    }

    // 针对uid推送数据
    function sendMessageByUid($uid, $message)
    {
        if(isset($this->uidConnections[$uid]))
        {
            $connection = $this->uidConnections[$uid];
            $connection->send($message);
        }
    }
}