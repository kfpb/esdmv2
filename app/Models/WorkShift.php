<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    use HasFactory;

    protected $table = 'work_shifts';

    protected $guarded = ['id'];

    protected $casts = [
        'is_night_shift' => 'boolean',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'work_shift_id');
    }
}
