<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'documents';
    protected $guarded = ['id'];

    public function employeeDocuments()
    {
        return $this->hasMany(EmployeeDocument::class, 'document_id');
    }
}
