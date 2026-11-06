<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Religion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReligionController extends Controller
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

        $query = Religion::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $religions = $query->paginate($perPage)
        ->withQueryString()
        ->through(fn ($religion) => [
            'id' => $religion->id,
            'name' => $religion->name,
            ]);

        return Inertia::render('Admin/MasterData/Religion/Index', [
            'religions' => $religions,
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
        ]);

        Religion::create($request->only('code', 'name'));

        return redirect()->route('admin.master_data.religion.index')
            ->with('success', 'Data Agama berhasil ditambahkan.');
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

        $religion = Religion::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $religion->update($request->only('code', 'name'));

        return redirect()->route('admin.master_data.religion.index')
            ->with('success', 'Data Agama berhasil diperbarui.');
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

        $religion = Religion::findOrFail($id);

        // Check if the religion is associated with any employees
        $employeeCount = DB::table('employees')->where('religion_id', $religion->id)->count();

        if ($employeeCount > 0) {
            return redirect()->route('admin.master_data.religion.index')
                ->with('error', 'Data Agama tidak dapat dihapus karena masih terkait dengan data pegawai.');
        }

        $religion->delete();

        return redirect()->route('admin.master_data.religion.index')
            ->with('success', 'Data Agama berhasil dihapus.');
    }
}
