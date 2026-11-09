\<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
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

        $query = Document::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $documents = $query->paginate($perPage)
        ->withQueryString()
        ->through(fn ($document) => [
            'id' => $document->id,
            'name' => $document->name,
            ]);

        return Inertia::render('Admin/MasterData/Document/Index', [
            'documents' => $documents,
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

        Document::create($request->only('name'));

        return redirect()->route('admin.master_data.document.index')
            ->with('success', 'Data Berkas berhasil ditambahkan.');
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

        $document = Document::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $document->update($request->only('name'));

        return redirect()->route('admin.master_data.document.index')
            ->with('success', 'Data Berkas berhasil diperbarui.');
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

        $document = Document::findOrFail($id);

        // Check if the document is associated with any employee_documents
        $isAssociated = DB::table('employee_documents')
            ->where('document_id', $document->id)
            ->exists();

        if ($isAssociated) {
            return redirect()->route('admin.master_data.document.index')
                ->withErrors(['delete' => 'Data Berkas tidak dapat dihapus karena masih digunakan oleh data Karyawan.']);
        }

        $document->delete();

        return redirect()->route('admin.master_data.document.index')
            ->with('success', 'Data Berkas berhasil dihapus.');
    }
}
