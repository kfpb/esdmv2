<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Division;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DivisionController extends Controller
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

        $query = Division::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
            $query->orWhere('code', 'like', "%{$search}%");
        }

        $divisions = $query->paginate($perPage)
        ->withQueryString()
        ->through(fn ($division) => [
            'id' => $division->id,
            'code' => $division->code,
            'name' => $division->name,
            'description' => $division->description,
            ]);

        return Inertia::render('Admin/MasterData/Division/Index', [
            'divisions' => $divisions,
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
            'code' => 'required|string|max:10|unique:divisions,code',
            'name' => 'required|string|max:100',
        ]);

        Division::create($request->only('code', 'name'));

        return redirect()->route('admin.master_data.division.index')
            ->with('success', 'Data Bagian berhasil ditambahkan.');
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

        $division = Division::findOrFail($id);

        $request->validate([
            'code' => 'required|string|max:10|unique:divisions,code,' . $division->id,
            'name' => 'required|string|max:100',
        ]);

        $division->update($request->only('code', 'name'));

        return redirect()->route('admin.master_data.division.index')
            ->with('success', 'Data Bagian berhasil diperbarui.');
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

        $division = Division::findOrFail($id);

        // Check if the division is associated with any employees
        $employeeCount = DB::table('employees')->where('division_id', $division->id)->count();

        if ($employeeCount > 0) {
            return redirect()->route('admin.master_data.division.index')
                ->with('error', 'Data Bagian tidak dapat dihapus karena masih terkait dengan data pegawai.');
        }

        $division->delete();

        return redirect()->route('admin.master_data.division.index')
            ->with('success', 'Data Bagian berhasil dihapus.');
    }
}
