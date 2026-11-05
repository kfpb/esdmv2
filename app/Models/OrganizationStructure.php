<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationStructure extends Model
{
    protected $table = 'organization_structures';

    protected $fillable = [
        'parent_id',
        'division_id',
        'position_id',
        'title',
    ];

    /**
     * Relasi atasan struktural (Parent Node)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganizationStructure::class, 'parent_id');
    }

    /**
     * Relasi bawahan struktural langsung (Children Nodes)
     */
    public function children(): HasMany
    {
        return $this->hasMany(OrganizationStructure::class, 'parent_id');
    }

    /**
     * Relasi ke Divisi / Bagian
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    /**
     * Relasi ke Jabatan / Posisi
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * Helper: Cari node organisasi berdasarkan NIP pegawai
     */
    public static function findByNip(?string $nip): ?self
    {
        if (empty($nip)) {
            return null;
        }

        return static::where('title', 'like', "[{$nip}]%")->first();
    }

    /**
     * Helper: Ekstrak NIP pegawai dari title unit
     */
    public function getEmployeeNip(): ?string
    {
        if (preg_match('/^\[([^\]]+)\]/', $this->title, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Helper: Dapatkan NIP atasan langsung dari parent node
     */
    public function getSupervisorNip(): ?string
    {
        if (! $this->parent) {
            return null;
        }

        return $this->parent->getEmployeeNip();
    }

    /**
     * Helper: Ambil seluruh daftar NIP bawahan langsung dari children nodes
     */
    public function getSubordinateNips(): array
    {
        $nips = [];
        $children = $this->children()->get(['id', 'title']);

        foreach ($children as $child) {
            $nip = $child->getEmployeeNip();
            if ($nip) {
                $nips[] = $nip;
            }
        }

        return $nips;
    }
}
