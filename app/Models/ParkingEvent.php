<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'action',
        'delta',
        'old_value',
        'new_value',
        'created_at',
        'actor',
        'user_name',
        'device_id',
    ];
}
