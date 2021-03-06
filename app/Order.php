<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    //
    public $guarded = [];

    public function getCreatedAtAttribute($time)
    {
        return date('Y-m-d H:i:s', $time);
    }
    
    public function getUpdatedAtAttribute($time)
    {
        return date('Y-m-d H:i:s', $time);
    }


    
}
