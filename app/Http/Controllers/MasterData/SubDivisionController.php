<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SubDivision;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SubDivisionController extends Controller
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

        $query = SubDivision::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
            $query->orWhere('code', 'like', "%{$search}%");
        }

        $subDivisions = $query->paginate($perPage)
        ->withQueryString()
        ->through(fn ($subDivision) => [
            'id' => $subDivision->id,
            'code' => $subDivision->code,
            'name' => $subDivision->name,
            'description' => $subDivision->description,
            ]);

        return Inertia::render('Admin/MasterData/SubDivision/Index', [
            'subDivisions' => $subDivisions,
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
            'code' => 'required|string|max:10|unique:sub_divisions,code',
            'name' => 'required|string|max:100',
        ]);

        $request->merge(['division_id' => 1]);

        SubDivision::create($request->only('code', 'name', 'division_id'));

        return redirect()->route('admin.master_data.sub_division.index')
            ->with('success', 'Data Sub-Bagian berhasil ditambahkan.');
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

        $subDivision = SubDivision::findOrFail($id);

        $request->validate([
            'code' => 'required|string|max:10|unique:sub_divisions,code,' . $subDivision->id,
            'name' => 'required|string|max:100',
        ]);

        $subDivision->update($request->only('code', 'name'));

        return redirect()->route('admin.master_data.sub_division.index')
            ->with('success', 'Data Sub-Bagian berhasil diperbarui.');
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

        $subDivision = SubDivision::findOrFail($id);

        // Check if the sub-division is associated with any employees
        $employeeCount = DB::table('employees')->where('sub_division_id', $subDivision->id)->count();

        if ($employeeCount > 0) {
            return redirect()->route('admin.master_data.sub_division.index')
                ->with('error', 'Data Sub-Bagian tidak dapat dihapus karena masih terkait dengan data pegawai.');
        }

        $subDivision->delete();

        return redirect()->route('admin.master_data.sub_division.index')
            ->with('success', 'Data Bagian berhasil dihapus.');
    }
}
