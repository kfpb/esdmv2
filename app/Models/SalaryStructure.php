<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryStructure extends Model
{
    use HasFactory;

    protected $table = 'salary_structures';
    protected $guarded = ['id'];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'position_allowance' => 'decimal:2',
        'family_allowance' => 'decimal:2',
        'attendance_allowance' => 'decimal:2',
        'performance_allowance' => 'decimal:2',
        'health_insurance_deduction' => 'decimal:2',
        'employment_insurance_deduction' => 'decimal:2',
        'tax_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_nip', 'nip');
    }
}
