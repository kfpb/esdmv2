<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Echelon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EchelonController extends Controller
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

        $query = Echelon::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
            $query->orWhere('description', 'like', "%{$search}%");
        }

        $echelons = $query->paginate($perPage)
        ->withQueryString()
        ->through(fn ($echelon) => [
            'id' => $echelon->id,
            'name' => $echelon->name,
            'description' => $echelon->description,
        ]);

        return Inertia::render('Admin/MasterData/Echelon/Index', [
            'echelons' => $echelons,
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
            'description' => 'nullable|string|max:255',
        ]);

        Echelon::create($request->only('name', 'description'));

        return redirect()->route('admin.master_data.echelon.index')
            ->with('success', 'Data Eselon berhasil ditambahkan.');
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

        $echelon = Echelon::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        $echelon->update($request->only('name', 'description'));

        return redirect()->route('admin.master_data.echelon.index')
            ->with('success', 'Data Eselon berhasil diperbarui.');
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

        $echelon = Echelon::findOrFail($id);

        // Check if the echelon is associated with any users
        if ($echelon->employees()->exists()) {
            return redirect()->route('admin.master_data.echelon.index')
                ->withErrors(['delete' => 'Data Eselon tidak dapat dihapus karena terkait dengan data pengguna.']);
        }

        $echelon->delete();

        return redirect()->route('admin.master_data.echelon.index')
            ->with('success', 'Data Eselon berhasil dihapus.');
    }
}
