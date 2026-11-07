<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $table = 'holidays';

    protected $fillable = [
        'date',
        'name',
        'is_collective_leave',
    ];

    protected $casts = [
        'date' => 'date',
        'is_collective_leave' => 'boolean',
    ];
}
