<?php

namespace App\Models;

use Core\Model\Model;

final class Guest extends Model
{
    protected $table = 'guests';

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'rsvp_status',
        'guest_count',
    ];

    protected $casts = [
        'guest_count' => 'int',
    ];
}