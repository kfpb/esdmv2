<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Tampilkan halaman formulir login.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Proses autentikasi login pengguna (mendukung Username, NIP, atau Email).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'NIP, Username, atau Email wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $loginInput = trim($request->input('login'));
        $password = $request->input('password');
        $remember = $request->boolean('remember');

        // Cari pengguna berdasarkan username, employee_nip, atau email
        $user = User::where('username', $loginInput)
            ->orWhere('employee_nip', $loginInput)
            ->orWhere('email', $loginInput)
            ->first();

        // Validasi keberadaan akun dan kecocokan hash Bcrypt password
        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => 'NIP/Password yang Anda masukkan tidak sesuai dengan catatan sistem.',
            ]);
        }

        // Cek status keaktifan user
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'login' => 'Akun Anda sedang dinonaktifkan oleh administrator. Silakan hubungi bagian SDM.',
            ]);
        }

        // Lakukan login sesi
        Auth::login($user, $remember);
        $request->session()->regenerate();

        // Rekam riwayat login terakhir
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return redirect()->intended('/');
    }

    /**
     * Logout sesi aktif pengguna.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}

