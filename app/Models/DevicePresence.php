<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DevicePresence extends Model
{
    use HasFactory;

    protected $table = 'device_presence';

    protected $primaryKey = 'device_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'device_id',
        'is_parked',
        'blocks_untracked_leave_until_arrive',
        'last_action_at',
    ];

    protected $casts = [
        'is_parked' => 'boolean',
        'blocks_untracked_leave_until_arrive' => 'boolean',
        'last_action_at' => 'datetime',
    ];
}
