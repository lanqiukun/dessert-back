<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use EasyWeChat\Factory;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;



class WechatController extends Controller
{
    protected $config = [
        'app_id' => null,
        'secret' => null,
        'mch_id' => null,
        'key'    => null,
        'notify_url' => null, 
    ];

    protected $app = null;

    public function __construct() {
        $this->config['app_id'] = env('APPID');
        $this->config['secret'] = env('APPSECRET');
        $this->config['mch_id'] = env('MCHID');
        $this->config['key']    = env('APIKEY');
        $this->config['notify_url'] = env('APP_URL') . '/api/notify';

        $this->app = Factory::miniProgram($this->config);
    }


    public function login() 
    {
        $code = request()->code;
        $vi = request()->iv;
        $encryptedData = request()->encryptedData;



        $response = $this->app->auth->session($code);

        $session_key = $response['session_key'];

        $result = $this->decrypte($session_key, $vi, $encryptedData);


        $openid = $result['openId'];
        $name = $result['nickName'];
        $gender = $result['gender'];
        $country = $result['country'];
        $avatarUrl = $result['avatarUrl'];
        
        $exist = User::where('openid', $openid)->first();



        if ($exist) 
            return ['status' => 0, 'token' => $exist->token];

        try {
            DB::beginTransaction();

            $token = Str::random(20);

            User::create([
                'openid' => $openid,
                'name' => $name,
                'gender' => $gender,
                'country' => $country,
                'avatarUrl' => $avatarUrl,
                'token' => $token,
            ]);
            
            DB::commit();
            return ['status' => 0, 'token' => $token];

        } catch (\Exception $e) {
            logger($e->getMessage());
            return ['status' => 1, 'msg' => $e->getMessage()];
        }

    }

    public function decrypte($session, $iv, $encryptedData) 
    {
        $decryptedData = $this->app->encryptor->decryptData($session, $iv, $encryptedData);

        return $decryptedData;
    }



    public function prepay() 
    {
        $product_id = request()->product_id;
        $quantity = request()->quantity ?? 1;
        
        
        $app = Factory::payment($this->config);

        $result = $app->order->unify([
            'body' => "烘焙糕点 x$quantity",
            'out_trade_no' => mt_rand(pow(2, 54), pow(2, 55)),
            'total_fee' => 1,
            'notify_url' => $this->config['notify_url'], 
            'trade_type' => 'JSAPI',
            'openid' => 'o5cnl5PdaixIVhrOjK7RoPCAZB7Q',
        ]);

        $params = [
            'appId' => $this->config['app_id'],
            'timeStamp' => strval(time()),
            'nonceStr' => $result['nonce_str'],
            'package' => 'prepay_id=' . $result['prepay_id'],
            'signType' => 'MD5',
        ];
        

        $params['paySign'] = $this->generate_sign($params, env('APIKEY'));


        logger($params);


        return $params;
    }
    
    public function notify()
    {
        $response = $this->app->handlePaidNotify(function($message, $fail){
            // 使用通知里的 "微信支付订单号" 或者 "商户订单号" 去自己的数据库找到订单
            $order = Order::where('order_no', ($message['out_trade_no']));
        
            if (!$order || $order->paid_at) { // 如果订单不存在 或者 订单已经支付过了
                return true; // 告诉微信，我已经处理完了，订单没找到，别再通知我了
            }
        
            ///////////// <- 建议在这里调用微信的【订单查询】接口查一下该笔订单的情况，确认是已经支付 /////////////
        
            if ($message['return_code'] === 'SUCCESS') { // return_code 表示通信状态，不代表支付状态
                // 用户是否支付成功
                if (array_get($message, 'result_code') === 'SUCCESS') {
                    $order->pay_status = 1;
                    $order->paid_at = date('Y-m-d H:i:s'); // 更新支付时间为当前时间
                }
            } else {
                return $fail('通信失败，请稍后再通知我');
            }
        
            $order->save(); // 保存订单
        
            return true; // 返回处理完成
        });
        
        $response->send(); // return $response;
    }


    static function generate_sign(array $attributes, $key, $encryptMethod = 'md5')
    {
        ksort($attributes);

        $attributes['key'] = $key;

        return strtoupper(call_user_func_array($encryptMethod, [urldecode(http_build_query($attributes))]));
    }

}
