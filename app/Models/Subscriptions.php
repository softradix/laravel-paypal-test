<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriptions extends Model
{
    use HasFactory;

    // public function Subscriptions(){       
    //     return $this->belongsTo("Transactions", "baseproduct_id");
    // }
    public function transactions(){
        return $this->hasMany('App\Models\Transactions', 'subscription_id');
    }
}
