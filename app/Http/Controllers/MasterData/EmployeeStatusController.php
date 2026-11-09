<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeeStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeStatusController extends Controller
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

        $query = EmployeeStatus::query();

        if ($search) {
            $query->where('status', 'like', "%{$search}%");
        }

        $employee_statuses = $query->paginate($perPage)
        ->withQueryString()
        ->through(fn ($employee_status) => [
            'id' => $employee_status->id,
            'status' => $employee_status->status,
            ]);

        return Inertia::render('Admin/MasterData/EmployeeStatus/Index', [
            'employee_statuses' => $employee_statuses,
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
            'status' => 'required|string|max:150',
        ]);

        EmployeeStatus::create($request->only('status'));

        return redirect()->route('admin.master_data.employee_status.index')
            ->with('success', 'Data Status Pegawai berhasil ditambahkan.');
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

        $employee_status = EmployeeStatus::findOrFail($id);

        $request->validate([
            'status' => 'required|string|max:150',
        ]);

        $employee_status->update($request->only('status'));

        return redirect()->route('admin.master_data.employee_status.index')
            ->with('success', 'Data Status Pegawai berhasil diperbarui.');
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

        $employee_status = EmployeeStatus::findOrFail($id);

        // Check if the employee_status is associated with any employees
        $isAssociated = DB::table('employees')
            ->where('employee_status_id', $employee_status->id)
            ->exists();

        if ($isAssociated) {
            return redirect()->route('admin.master_data.employee_status.index')
                ->withErrors(['delete' => 'Data Status Pegawai tidak dapat dihapus karena masih digunakan oleh data Karyawan.']);
        }

        $employee_status->delete();

        return redirect()->route('admin.master_data.employee_status.index')
            ->with('success', 'Data Status Pegawai berhasil dihapus.');
    }
}
