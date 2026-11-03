<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionType extends Model
{
    use HasFactory;

    protected $table = 'permission_types';

    protected $guarded = ['id'];

    protected $casts = [
        'legacy_id' => 'integer',
        'is_salary_deducted' => 'boolean',
    ];

    public function permissionRequests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class, 'permission_type_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'permission_type_id');
    }
}

