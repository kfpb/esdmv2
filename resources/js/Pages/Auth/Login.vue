<script setup>
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

// Modal state for Lupa Kata Sandi / Helpdesk
const showHelpModal = ref(false);

const submit = () => {
    form.post('/login', {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="E-SDM KFPB" />

    <div class="min-h-screen w-full flex bg-[#f5f5f9] font-sans antialiased text-slate-700">

        <!-- =============================================================== -->
        <!-- LEFT PANEL: Enterprise Showcase & Branding (Desktop only)       -->
        <!-- =============================================================== -->
        <div class="hidden lg:flex lg:w-1/2 xl:w-7/12 relative bg-[#0b1c34] text-white flex-col justify-between p-12 overflow-hidden select-none border-r border-[#172c4d]">

            <!-- Top Brand Header -->
            <div class="relative z-10 flex items-center gap-3.5">
                <div class="size-11 rounded-2xl bg-white border border-white/30 flex items-center justify-center shadow-sm p-1.5 shrink-0">
                    <img src="/images/esdm-icon.png" alt="eSDM Icon" class="size-7 object-contain" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xl font-black tracking-tight text-white flex items-baseline leading-none">
                            <span class="text-sky-400 font-extrabold italic mr-0.5">e</span>SDM
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-white/15 text-sky-300 border border-white/20">
                            KFPB
                        </span>
                    </div>
                    <p class="text-xs text-blue-200/80 font-medium mt-0.5">
                        PT Kimia Farma Tbk. — Plant Banjaran
                    </p>
                </div>
            </div>

            <!-- Middle Value Propositions -->
            <div class="relative z-10 my-auto py-8 max-w-xl">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/15 text-blue-200 text-xs font-semibold mb-6">
                    <span class="size-2 rounded-full bg-emerald-400" />
                    <span>Sistem Informasi Sumber Daya Manusia</span>
                </div>

                <h1 class="text-3xl xl:text-4xl font-extrabold text-white leading-tight tracking-tight mb-4">
                    Kelola Presensi, Izin, Lembur, dan Karir dalam Satu Portal.
                </h1>

                <p class="text-sm xl:text-base text-blue-100/80 leading-relaxed mb-8">
                    Menghadirkan efisiensi dan transparansi manajemen kepegawaian modern untuk seluruh staf dan unit kerja di fasilitas manufaktur farmasi Plant Banjaran.
                </p>

                <!-- 3 Operational Feature Cards -->
                <div class="grid grid-cols-1 gap-3.5">
                    <div class="p-3.5 rounded-2xl bg-white/[0.05] border border-white/10 flex items-start gap-3.5 hover:bg-white/[0.08] transition-colors">
                        <div class="size-9 rounded-xl bg-blue-500/20 border border-blue-400/30 text-blue-200 flex items-center justify-center shrink-0 mt-0.5">
                            <i class="pi pi-calendar-check text-base" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white mb-0.5">Presensi Multi-Shift & Biometrik</h4>
                            <p class="text-[11px] text-blue-100/70 leading-snug">
                                Penjadwalan otomatis regu shift produksi, non-shift kantor, serta validasi kehadiran real-time.
                            </p>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-white/[0.05] border border-white/10 flex items-start gap-3.5 hover:bg-white/[0.08] transition-colors">
                        <div class="size-9 rounded-xl bg-amber-500/20 border border-amber-400/30 text-amber-200 flex items-center justify-center shrink-0 mt-0.5">
                            <i class="pi pi-clock text-base" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white mb-0.5">Alur Persetujuan Lembur (SPL) & Cuti Online</h4>
                            <p class="text-[11px] text-blue-100/70 leading-snug">
                                Verifikasi berjenjang dari Supervisor Unit hingga HRD secara cepat tanpa berkas manual.
                            </p>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-white/[0.05] border border-white/10 flex items-start gap-3.5 hover:bg-white/[0.08] transition-colors">
                        <div class="size-9 rounded-xl bg-emerald-500/20 border border-emerald-400/30 text-emerald-200 flex items-center justify-center shrink-0 mt-0.5">
                            <i class="pi pi-shield text-base" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white mb-0.5">Akses Privat Slip Gaji & Portofolio Karir</h4>
                            <p class="text-[11px] text-blue-100/70 leading-snug">
                                Privasi data penggajian terlindungi dengan enkripsi standar industri dan rekam jejak audit.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Operational Stats Pill -->
            <div class="relative z-10 pt-6 border-t border-white/10 flex items-center justify-between text-xs text-blue-200/80">
                <div class="flex items-center gap-6">
                    <div>
                        <span class="block text-base font-extrabold text-white">1,095+</span>
                        <span class="text-[10px] text-blue-200/60 uppercase tracking-wider">Pegawai Aktif</span>
                    </div>
                    <div class="w-px h-6 bg-white/15" />
                    <div>
                        <span class="block text-base font-extrabold text-white">100%</span>
                        <span class="text-[10px] text-blue-200/60 uppercase tracking-wider">Paperless</span>
                    </div>
                    <div class="w-px h-6 bg-white/15" />
                    <div>
                        <span class="block text-base font-extrabold text-white">24 / 7</span>
                        <span class="text-[10px] text-blue-200/60 uppercase tracking-wider">Akses Portal</span>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 text-[11px]">
                    <i class="pi pi-lock text-[10px] text-emerald-300" />
                    <span>Enkripsi Bcrypt 60-bit</span>
                </div>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- RIGHT PANEL: Authentic Responsive Login Form                    -->
        <!-- =============================================================== -->
        <div class="w-full lg:w-1/2 xl:w-5/12 flex flex-col justify-between p-6 sm:p-10 lg:p-12 xl:p-16 min-h-screen">

            <!-- Login Form Container -->
            <div class="w-full max-w-md mx-auto my-auto py-4">

                <!-- Welcome & Iconic Brand Header -->
                <div class="mb-8">
                    <!-- Brand Logo Signature Unit -->
                    <div class="flex items-center gap-3.5 mb-5 select-none">
                        <div class="size-12 sm:size-14 rounded-2xl bg-white border border-slate-200 shadow-xs flex items-center justify-center shrink-0 p-2 sm:p-2.5">
                            <img src="/images/esdm-icon.png" alt="eSDM Icon" class="size-8 sm:size-9 object-contain" />
                        </div>

                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <span class="text-3xl sm:text-4xl font-black tracking-tight text-[#0b1c34] flex items-baseline leading-none">
                                    <span class="text-sky-600 font-extrabold italic mr-0.5">e</span>SDM
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-[#0b1c34] text-sky-300 border border-[#172c4d] shadow-xs">
                                    KFPB
                                </span>
                            </div>
                            <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase mt-1">
                                Kimia Farma Plant Banjaran
                            </span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-200/70">
                        <h2 class="text-base font-bold text-slate-800 tracking-tight">
                            Masuk ke Portal Pegawai
                        </h2>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Silakan masukkan <strong>NIP</strong> atau <strong>Username</strong> dan kata sandi Anda untuk mengakses portal kerja.
                        </p>
                    </div>
                </div>

                <!-- Session Flash Status (e.g. Password reset success) -->
                <div
                    v-if="status"
                    class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs flex items-center gap-2.5"
                >
                    <i class="pi pi-check-circle text-base text-emerald-600 shrink-0" />
                    <span>{{ status }}</span>
                </div>

                <!-- Validation Error Alert -->
                <div
                    v-if="form.errors.login"
                    class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200/80 text-rose-800 text-xs flex items-start gap-2.5 animate-shake"
                >
                    <i class="pi pi-exclamation-triangle text-base text-rose-600 shrink-0 mt-0.5" />
                    <div class="leading-snug">
                        <span class="font-bold block">Gagal Masuk:</span>
                        <span>{{ form.errors.login }}</span>
                    </div>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-4">

                    <!-- Input 1: NIP / Username / Email -->
                    <div>
                        <label for="login" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            NIP / Username
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-slate-400 pointer-events-none">
                                <i class="pi pi-user text-sm" />
                            </span>
                            <input
                                id="login"
                                v-model="form.login"
                                type="text"
                                autocomplete="username"
                                autofocus
                                required
                                placeholder="Masukkan NIP/ Username"
                                :class="[
                                    'w-full pl-10 pr-3.5 py-2.5 rounded-xl border text-sm transition-all outline-none bg-white',
                                    form.errors.login
                                        ? 'border-rose-400 focus:border-rose-500 focus:ring-2 focus:ring-rose-200'
                                        : 'border-slate-200 hover:border-slate-300 focus:border-[#696cff] focus:ring-2 focus:ring-[#696cff]/20 text-slate-800',
                                ]"
                            />
                        </div>
                    </div>

                    <!-- Input 2: Kata Sandi (Password) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-semibold text-slate-700">
                                Kata Sandi
                            </label>
                            <button
                                type="button"
                                class="text-xs font-medium text-[#696cff] hover:text-[#5254cc] hover:underline cursor-pointer"
                                @click="showHelpModal = true"
                            >
                                Lupa kata sandi?
                            </button>
                        </div>
                        <div class="w-full">
                            <Password
                                id="password"
                                v-model="form.password"
                                :feedback="false"
                                toggleMask
                                class="w-full"
                                inputClass="w-full !py-2.5 !rounded-xl !text-sm !border-slate-200 hover:!border-slate-300 focus:!border-[#696cff] focus:!ring-2 focus:!ring-[#696cff]/20"
                                placeholder="Masukkan kata sandi akun Anda"
                                autocomplete="current-password"
                                required
                            />
                        </div>
                        <small v-if="form.errors.password" class="text-rose-500 text-xs mt-1 block">
                            {{ form.errors.password }}
                        </small>
                    </div>

                    <!-- Row 3: Remember Me -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <Checkbox v-model="form.remember" :binary="true" inputId="remember" />
                            <span class="text-xs text-slate-600 font-medium">Ingat sesi saya di perangkat ini</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            :class="[
                                'w-full py-2.5 px-4 rounded-xl font-bold text-sm text-white flex items-center justify-center gap-2 shadow-[0_4px_12px_rgba(105,108,255,0.35)] transition-all cursor-pointer',
                                form.processing
                                    ? 'bg-[#8587ff] cursor-not-allowed opacity-80'
                                    : 'bg-[#696cff] hover:bg-[#5b5ee6] active:scale-[0.99]',
                            ]"
                        >
                            <i v-if="form.processing" class="pi pi-spin pi-spinner text-sm" />
                            <i v-else class="pi pi-sign-in text-sm" />
                            <span>{{ form.processing ? 'Memverifikasi Akun...' : 'Masuk ke Portal E-SDM' }}</span>
                        </button>
                    </div>
                </form>

                <!-- Helper Card: Login Instructions for KFPB Employees -->
                <div class="mt-6 p-4 rounded-2xl bg-slate-50/80 border border-slate-200/70 text-xs text-slate-600 space-y-2">
                    <div class="flex items-center gap-2 font-bold text-slate-800">
                        <i class="pi pi-info-circle text-[#696cff]" />
                        <span>Petunjuk Masuk Akun:</span>
                    </div>
                    <ul class="space-y-1.5 text-[11px] leading-relaxed text-slate-500 pl-5 list-disc">
                        <li>
                            Kata sandi akun tetap sama dengan sistem E-SDM sebelumnya (telah dimigrasikan).
                        </li>
                    </ul>
                </div>

            </div>

            <!-- Clean Footer -->
            <div class="pt-6 border-t border-slate-200/60 text-center text-xs text-slate-400">
                <div class="flex items-center justify-center gap-2 mb-1">
                    <span>PT Kimia Farma Plant Banjaran</span>
                </div>
                <p class="text-[11px] text-slate-400">
                    © 2026 Sumber Daya Manusia. Seluruh hak cipta dilindungi.
                </p>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- MODAL: Bantuan Reset / Lupa Kata Sandi                           -->
        <!-- =============================================================== -->
        <div
            v-if="showHelpModal"
            class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 transition-opacity"
            @click.self="showHelpModal = false"
        >
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-md w-full p-6 transform transition-all">
                <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center">
                            <i class="pi pi-key text-lg" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">Bantuan Lupa Kata Sandi</h3>
                            <p class="text-[11px] text-slate-400">Layanan Pemulihan Akun E-SDM KFPB</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
                        @click="showHelpModal = false"
                    >
                        <i class="pi pi-times text-sm" />
                    </button>
                </div>

                <div class="py-4 text-xs text-slate-600 space-y-3 leading-relaxed">
                    <p>
                        Untuk menjaga keamanan data perusahaan dan privasi slip gaji, pemulihan kata sandi pegawai dilakukan melalui verifikasi unit kerja:
                    </p>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/60 space-y-2">
                        <div class="flex items-start gap-2">
                            <span class="size-5 rounded-full bg-[#696cff]/10 text-[#696cff] font-bold flex items-center justify-center shrink-0 text-[10px]">1</span>
                            <span>Datang langsung ke <strong>Divisi SDM & Organisasi</strong> (Gedung Manajemen Lt. 2 Plant Banjaran).</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="size-5 rounded-full bg-[#696cff]/10 text-[#696cff] font-bold flex items-center justify-center shrink-0 text-[10px]">2</span>
                            <span>Atau hubungi Helpdesk Internal melalui WhatsApp Hotline SDM KFPB dengan menyertakan <strong>NIP, Nama Lengkap, dan Unit Kerja</strong>.</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-indigo-50/70 border border-indigo-100 text-indigo-900 flex items-center gap-3">
                        <i class="pi pi-phone text-lg text-indigo-600 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-[11px]">Helpdesk SDM & IT KFPB:</div>
                            <div class="text-[11px] text-indigo-700">Ext: 214 / 215 (Jam Kerja Operasional)</div>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex justify-end">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors cursor-pointer"
                        @click="showHelpModal = false"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-4px); }
    40%, 80% { transform: translateX(4px); }
}
.animate-shake {
    animation: shake 0.4s ease-in-out;
}
</style>

