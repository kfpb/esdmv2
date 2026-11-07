<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkShift;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WorkShiftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if (! $user->hasAdminAccess()) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Administrator & Personalia HRD.');
        }

        $search = trim($request->input('search', ''));
        $perPage = max(10, min(100, (int) $request->input('per_page', 25)));

        $query = WorkShift::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $workShifts = $query->paginate($perPage)
        ->withQueryString()
        ->through(fn ($workShift) => [
            'id' => $workShift->id,
            'name' => $workShift->name,
            'check_in_time' => $workShift->check_in_time,
            'check_out_time' => $workShift->check_out_time,
            'late_tolerance_time' => $workShift->late_tolerance_time,
            'is_night_shift' => $workShift->is_night_shift,
            ]);

        return Inertia::render('Admin/MasterData/WorkShift/Index', [
            'workShifts' => $workShifts,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (! $user->hasAdminAccess()) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Administrator & Personalia HRD.');
        }
        
        $request->validate([
            'name' => 'required|string|max:100',
            'check_in_time' => 'required|date_format:H:i:s',
            'check_out_time' => 'required|date_format:H:i:s',
            'late_tolerance_time' => 'required|date_format:H:i:s',
            'is_night_shift' => 'required|boolean',
        ]);

        WorkShift::create($request->only('name', 'check_in_time', 'check_out_time', 'late_tolerance_time', 'is_night_shift'));

        return redirect()->route('admin.master_data.work_shift.index')
            ->with('success', 'Data Shift Kerja berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();

        if (! $user->hasAdminAccess()) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Administrator & Personalia HRD.');
        }

        $workShift = WorkShift::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100',
            'check_in_time' => 'required|date_format:H:i:s',
            'check_out_time' => 'required|date_format:H:i:s',
            'late_tolerance_time' => 'required|date_format:H:i:s',
            'is_night_shift' => 'required|boolean',
        ]);

        $workShift->update($request->only('name', 'check_in_time', 'check_out_time', 'late_tolerance_time', 'is_night_shift'));

        return redirect()->route('admin.master_data.work_shift.index')
            ->with('success', 'Data Shift Kerja berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = Auth::user();

        if (! $user->hasAdminAccess()) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Administrator & Personalia HRD.');
        }

        $workShift = WorkShift::findOrFail($id);

        // Check if the work shift is associated with any attendance records
        if ($workShift->attendances()->exists()) {
            return redirect()->route('admin.master_data.work_shift.index')
                ->withErrors(['delete' => 'Data Shift Kerja tidak dapat dihapus karena terkait dengan data absensi.']);
        }

        $workShift->delete();

        return redirect()->route('admin.master_data.work_shift.index')
            ->with('success', 'Data Shift Kerja berhasil dihapus.');
    }
}
