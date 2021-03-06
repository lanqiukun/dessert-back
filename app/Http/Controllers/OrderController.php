<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Order;
use App\User;

class OrderController extends Controller
{
    function order() 
    {   
        $token = request()->token;
        $user = User::where('token', $token)->first();


        $product_id = request()->product_id;
        $quantity = request()->quantity ?? 1;

        $order = Order::create([
            'product_id' => 1,
            'quantity' => 1,
            'user_id' => 1,
        ]);

        $payment = new WechatController();


        $order_result = $payment->prepay();
        logger($order_result);


        return $order_result;
    
    }

}
