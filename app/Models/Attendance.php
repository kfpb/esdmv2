<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    protected $guarded = ['id'];

    protected $casts = [
        'attendance_date' => 'date',
        'is_present_in' => 'boolean',
        'is_present_out' => 'boolean',
        'is_late' => 'boolean',
        'is_early_leave' => 'boolean',
        'is_overtime' => 'boolean',
        'is_overtime_verified' => 'boolean',
        'overtime_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_nip', 'nip');
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }

    public function permissionType(): BelongsTo
    {
        return $this->belongsTo(PermissionType::class, 'permission_type_id');
    }
}
