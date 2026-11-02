<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Legacy table: tbl_absen2 (Poligon Geofence Gedung & Area Kerja KFPB)
     */
    public function up(): void
    {
        Schema::create('office_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id_abs2 lama
            $table->string('code', 50)->nullable()->index();
            $table->string('name', 100); // office, pharma, herbal, utility, wwtp, parkiran
            $table->string('label', 150)->nullable();
            $table->json('polygon_coordinates'); // array of [lat, lon] titik poligon
            $table->decimal('latitude', 11, 7)->nullable(); // titik tengah (centroid)
            $table->decimal('longitude', 11, 7)->nullable();
            $table->integer('radius')->default(50); // radius meter (default 50 dari tbl_absen2)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed 6 data poligon riil dari tabel legacy tbl_absen2
        $buildings = [
            [
                'legacy_id' => 1,
                'code'      => 'OFFICE',
                'name'      => 'office',
                'label'     => 'Gedung Administrasi & Kantor Pusat (Office)',
                'polygon_coordinates' => json_encode([
                    [-7.03825, 107.5954],
                    [-7.03838, 107.5957],
                    [-7.03870, 107.5958],
                    [-7.03902, 107.5957],
                    [-7.03915, 107.5954],
                    [-7.03902, 107.5950],
                    [-7.03870, 107.5949],
                    [-7.03838, 107.5950],
                ]),
                'latitude'  => -7.03870,
                'longitude' => 107.5954,
                'radius'    => 50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'legacy_id' => 2,
                'code'      => 'PHARMA',
                'name'      => 'pharma',
                'label'     => 'Gedung Produksi Farmasi (Pharma)',
                'polygon_coordinates' => json_encode([
                    [-7.03907, 107.5966],
                    [-7.03876, 107.5965],
                    [-7.03863, 107.5962],
                    [-7.03876, 107.5959],
                    [-7.03907, 107.5957],
                    [-7.03939, 107.5959],
                    [-7.03952, 107.5962],
                    [-7.03939, 107.5965],
                ]),
                'latitude'  => -7.03907,
                'longitude' => 107.5962,
                'radius'    => 50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'legacy_id' => 3,
                'code'      => 'HERBAL',
                'name'      => 'herbal',
                'label'     => 'Gedung Produksi Herbal',
                'polygon_coordinates' => json_encode([
                    [-7.03837, 107.5971],
                    [-7.03851, 107.5974],
                    [-7.03882, 107.5976],
                    [-7.03914, 107.5974],
                    [-7.03927, 107.5971],
                    [-7.03914, 107.5968],
                    [-7.03882, 107.5967],
                    [-7.03851, 107.5968],
                ]),
                'latitude'  => -7.03882,
                'longitude' => 107.5971,
                'radius'    => 50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'legacy_id' => 4,
                'code'      => 'UTILITY',
                'name'      => 'utility',
                'label'     => 'Gedung Sarana & Utility',
                'polygon_coordinates' => json_encode([
                    [-7.03875, 107.5972],
                    [-7.03888, 107.5975],
                    [-7.03920, 107.5977],
                    [-7.03952, 107.5975],
                    [-7.03965, 107.5972],
                    [-7.03952, 107.5969],
                    [-7.03920, 107.5968],
                    [-7.03888, 107.5969],
                ]),
                'latitude'  => -7.03920,
                'longitude' => 107.5972,
                'radius'    => 50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'legacy_id' => 5,
                'code'      => 'WWTP',
                'name'      => 'wwtp',
                'label'     => 'Area Pengolahan Air Limbah (WWTP)',
                'polygon_coordinates' => json_encode([
                    [-7.03954, 107.5969],
                    [-7.03967, 107.5972],
                    [-7.03999, 107.5973],
                    [-7.04031, 107.5972],
                    [-7.04044, 107.5969],
                    [-7.04031, 107.5965],
                    [-7.03999, 107.5964],
                    [-7.03967, 107.5965],
                ]),
                'latitude'  => -7.03999,
                'longitude' => 107.5969,
                'radius'    => 50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'legacy_id' => 6,
                'code'      => 'PARKIRAN',
                'name'      => 'parkiran',
                'label'     => 'Area Parkiran & Akses Masuk Gerbang Utama',
                'polygon_coordinates' => json_encode([
                    [-7.03719, 107.5963],
                    [-7.03775, 107.5977],
                    [-7.03912, 107.5982],
                    [-7.04048, 107.5977],
                    [-7.04105, 107.5963],
                    [-7.04048, 107.5949],
                    [-7.03912, 107.5943],
                    [-7.03775, 107.5949],
                ]),
                'latitude'  => -7.03912,
                'longitude' => 107.5963,
                'radius'    => 50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('office_locations')->insert($buildings);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('office_locations');
    }
};

