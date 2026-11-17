<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';

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

const loginInputRef = ref(null);
const passwordToggleRef = ref(null);
const forgotPasswordBtnRef = ref(null);
const helpModalRef = ref(null);
const helpModalCloseBtnRef = ref(null);
const isHttps = ref(false);

// Modal state for Lupa Kata Sandi / Helpdesk
const showHelpModal = ref(false);

const handlePasswordToggle = (toggleCallback) => {
    const keepFocus = document.activeElement === passwordToggleRef.value;
    toggleCallback();
    if (keepFocus) {
        nextTick(() => {
            passwordToggleRef.value?.focus();
        });
    }
};

const openHelpModal = () => {
    showHelpModal.value = true;
    nextTick(() => {
        helpModalCloseBtnRef.value?.focus();
    });
};

const closeHelpModal = () => {
    showHelpModal.value = false;
    nextTick(() => {
        forgotPasswordBtnRef.value?.focus();
    });
};

const handleHelpModalKeydown = (event) => {
    if (!showHelpModal.value) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        closeHelpModal();
        return;
    }

    if (event.key !== 'Tab' || !helpModalRef.value) return;

    const focusable = Array.from(
        helpModalRef.value.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ),
    );

    if (focusable.length === 0) {
        event.preventDefault();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey) {
        if (document.activeElement === first || !helpModalRef.value.contains(document.activeElement)) {
            event.preventDefault();
            last.focus();
        }
    } else if (document.activeElement === last || !helpModalRef.value.contains(document.activeElement)) {
        event.preventDefault();
        first.focus();
    }
};

watch(showHelpModal, (isOpen) => {
    if (isOpen) {
        document.addEventListener('keydown', handleHelpModalKeydown);
        document.body.style.overflow = 'hidden';
    } else {
        document.removeEventListener('keydown', handleHelpModalKeydown);
        document.body.style.overflow = '';
    }
});

onMounted(() => {
    isHttps.value = window.location.protocol === 'https:';
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleHelpModalKeydown);
    document.body.style.overflow = '';
});

const submit = () => {
    form.clearErrors();
    const trimmedLogin = form.login.trim();

    if (!trimmedLogin) {
        form.setError('login', 'NIP / Username wajib diisi.');
    }
    if (!form.password) {
        form.setError('password', 'Kata sandi wajib diisi.');
    }

    if (!trimmedLogin || !form.password) {
        nextTick(() => {
            if (!trimmedLogin) {
                loginInputRef.value?.focus();
            } else {
                document.getElementById('password')?.focus();
            }
        });
        return;
    }

    form.post('/login', {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="E-SDM KFPB" />

    <div class="min-h-screen min-h-[100dvh] w-full flex flex-col lg:flex-row bg-[#eef2f6] lg:bg-[#f5f5f9] font-sans antialiased text-slate-700">

        <!-- =============================================================== -->
        <!-- LEFT PANEL: Enterprise Showcase & Branding (Desktop only)       -->
        <!-- =============================================================== -->
        <aside aria-label="Informasi Portal E-SDM" class="hidden lg:flex lg:w-1/2 xl:w-7/12 relative bg-[#0b1c34] text-white flex-col justify-between p-12 overflow-hidden select-none border-r border-[#172c4d]">

            <!-- Top Brand Header -->
            <div class="relative z-10 flex items-center gap-3.5">
                <div class="size-11 rounded-2xl bg-white border border-white/30 flex items-center justify-center shadow-sm p-1.5 shrink-0">
                    <img src="/images/esdm-icon.png" alt="eSDM Icon" width="96" height="96" class="size-7 object-contain" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xl font-black tracking-tight text-white flex items-baseline leading-none">
                            <span class="text-[#F37021] font-extrabold italic mr-0.5">e</span>SDM
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-[#c2410c] text-white shadow-xs">
                            KFPB
                        </span>
                    </div>
                    <p class="text-xs text-blue-200/80 font-medium mt-0.5">
                        PT Kimia Farma Plant Banjaran
                    </p>
                </div>
            </div>

            <!-- Middle Value Propositions -->
            <div class="relative z-10 my-auto py-8 max-w-xl">

                <p class="text-3xl xl:text-4xl font-extrabold text-white leading-tight tracking-tight mb-4">
                    Kelola Presensi, Izin, Lembur, dan Karir dalam Satu Portal.
                </p>

                <p class="text-sm xl:text-base text-blue-100/80 leading-relaxed mb-8">
                    Efisiensi dan transparansi manajemen kepegawaian modern untuk seluruh staf dan unit kerja di fasilitas manufaktur farmasi Plant Banjaran.
                </p>
            </div>

            <!-- Bottom Operational Info -->
            <div class="relative z-10 pt-6 border-t border-white/10 flex items-center justify-between text-xs text-blue-200/80">
                <div v-if="isHttps" class="flex items-center gap-1.5 text-[11px]">
                    <i class="pi pi-lock text-[10px] text-emerald-300" aria-hidden="true" />
                    <span>Koneksi Terenkripsi (HTTPS)</span>
                </div>
                <div v-else class="text-[11px]">
                    <span>Portal Internal Kepegawaian KFPB</span>
                </div>
            </div>
        </aside>

        <!-- =============================================================== -->
        <!-- RIGHT PANEL: Authentic Responsive Login Form                    -->
        <!-- =============================================================== -->
        <div class="flex-1 w-full lg:w-1/2 xl:w-5/12 flex flex-col lg:min-h-screen lg:overflow-y-auto">

            <!-- Mobile / Tablet Hero Cover Backdrop (< 1024px) -->
            <header class="lg:hidden bg-[#0b1c34] text-white px-5 pt-6 pb-16 sm:pt-10 sm:pb-24 text-center select-none shrink-0">
                <div class="max-w-md mx-auto flex flex-col items-center">
                    <div class="size-12 rounded-2xl bg-white border border-white/30 shadow-xs flex items-center justify-center p-2 mb-2.5">
                        <img src="/images/esdm-icon.png" alt="eSDM Icon" width="96" height="96" class="size-8 object-contain" />
                    </div>
                    <div class="flex items-center justify-center gap-2">
                        <span class="text-2xl font-black tracking-tight text-white flex items-baseline leading-none">
                            <span class="text-[#F37021] font-extrabold italic mr-0.5">e</span>SDM
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-[#c2410c] text-white">
                            KFPB
                        </span>
                    </div>
                    <p class="text-xs text-blue-200 font-medium mt-1">
                        PT Kimia Farma Plant Banjaran
                    </p>
                </div>
            </header>

            <div class="relative z-10 flex-1 flex flex-col justify-between px-4 pb-5 -mt-10 sm:px-8 sm:pb-6 sm:-mt-14 lg:mt-0 lg:p-12 xl:px-16 xl:py-8">

                <!-- Login Form Container (Overlapping white card on mobile/tablet; open split panel on desktop) -->
                <main class="w-full max-w-md mx-auto lg:my-auto bg-white border border-slate-200 rounded-2xl shadow-md p-5 sm:p-8 lg:bg-transparent lg:border-0 lg:rounded-none lg:shadow-none lg:p-0">

                <!-- Welcome & Iconic Brand Header -->
                <div class="mb-5 lg:mb-6">
                    <!-- Brand Logo Signature Unit (Desktop split panel) -->
                    <div class="hidden lg:flex items-center gap-3.5 mb-5 select-none">
                        <div class="size-12 rounded-2xl bg-white border border-slate-300 shadow-xs flex items-center justify-center shrink-0 p-2">
                            <img src="/images/esdm-icon.png" alt="eSDM Icon" width="96" height="96" class="size-8 object-contain" />
                        </div>

                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <span class="text-3xl xl:text-4xl font-black tracking-tight text-[#005BAC] flex items-baseline leading-none">
                                    <span class="text-[#c2410c] font-extrabold italic mr-0.5">e</span>SDM
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-[#c2410c] text-white shadow-xs">
                                    KFPB
                                </span>
                            </div>
                            <span class="text-[11px] font-medium text-slate-600 mt-1">
                                Elektronik Sumber Daya Manusia - PT Kimia Farma Plant Banjaran
                            </span>
                        </div>
                    </div>

                    <div class="pb-3.5 border-b border-slate-200 lg:pb-0 lg:border-b-0 lg:pt-3 lg:border-t lg:border-slate-300/80">
                        <h1 class="text-lg sm:text-xl lg:text-base font-bold text-slate-800 tracking-tight">
                            Masuk ke Portal Pegawai
                        </h1>
                        <p class="text-xs text-slate-600 mt-1 lg:hidden">
                            Gunakan NIP 8 digit atau username akun E-SDM Anda.
                        </p>
                    </div>
                </div>

                <!-- Session Flash Status (e.g. Password reset success) -->
                <div
                    v-if="status"
                    role="status"
                    class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs flex items-center gap-2.5"
                >
                    <i class="pi pi-check-circle text-base text-emerald-600 shrink-0" aria-hidden="true" />
                    <span>{{ status }}</span>
                </div>

                <!-- Validation Error Alert -->
                <div
                    v-if="form.errors.login"
                    id="login-error"
                    role="alert"
                    class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-300 text-rose-800 text-xs flex items-start gap-2.5 animate-shake"
                >
                    <i class="pi pi-exclamation-triangle text-base text-rose-700 shrink-0 mt-0.5" aria-hidden="true" />
                    <div class="leading-snug">
                        <span class="font-bold block">Gagal Masuk:</span>
                        <span>{{ form.errors.login }}</span>
                    </div>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" novalidate class="space-y-4">

                    <!-- Input 1: NIP / Username / Email -->
                    <div>
                        <label for="login" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            NIP / Username
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-slate-500 pointer-events-none">
                                <i class="pi pi-user text-sm" aria-hidden="true" />
                            </span>
                            <input
                                ref="loginInputRef"
                                id="login"
                                v-model="form.login"
                                type="text"
                                autocomplete="username"
                                autofocus
                                required
                                :aria-invalid="Boolean(form.errors.login)"
                                :aria-describedby="form.errors.login ? 'login-error' : undefined"
                                placeholder="Masukkan NIP / Username"
                                @input="form.clearErrors('login')"
                                :class="[
                                    'w-full min-h-[44px] pl-10 pr-3.5 py-2.5 rounded-xl border text-base sm:text-sm text-slate-800 placeholder:text-slate-500 transition-all motion-reduce:transition-none outline-none bg-white',
                                    form.errors.login
                                        ? 'border-rose-600 focus:border-rose-600 focus:ring-2 focus:ring-rose-200'
                                        : 'border-[#75859c] hover:border-slate-600 focus:border-[#005BAC] focus:ring-2 focus:ring-[#005BAC]/20',
                                ]"
                            />
                        </div>
                    </div>

                    <!-- Input 2: Kata Sandi (Password) -->
                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            Kata Sandi
                        </label>
                        <div class="w-full">
                            <Password
                                inputId="password"
                                v-model="form.password"
                                :feedback="false"
                                promptLabel="Masukkan kata sandi"
                                toggleMask
                                :invalid="Boolean(form.errors.password)"
                                class="w-full"
                                :inputClass="[
                                    'w-full !min-h-[44px] !py-2.5 !pr-12 !rounded-xl !shadow-none !text-base sm:!text-sm !text-slate-800 placeholder:!text-slate-500 focus:!border-[#005BAC] focus:!ring-2 focus:!ring-[#005BAC]/20 motion-reduce:!transition-none',
                                    form.errors.password
                                        ? '!border-rose-600 focus:!border-rose-600 focus:!ring-rose-200'
                                        : '!border-[#75859c] hover:!border-slate-600',
                                ].join(' ')"
                                :inputProps="{
                                    autocomplete: 'current-password',
                                    'aria-expanded': null,
                                    'aria-haspopup': null,
                                    'aria-invalid': Boolean(form.errors.password),
                                    'aria-describedby': form.errors.password ? 'password-error' : undefined,
                                }"
                                placeholder="Masukkan kata sandi"
                                required
                                @update:modelValue="form.clearErrors('password')"
                            >
                                <template #maskicon="{ toggleCallback }">
                                    <button
                                        ref="passwordToggleRef"
                                        type="button"
                                        class="absolute right-0.5 top-1/2 -translate-y-1/2 z-10 size-11 flex items-center justify-center rounded-lg text-slate-500 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#005BAC] cursor-pointer"
                                        aria-label="Sembunyikan kata sandi"
                                        aria-pressed="true"
                                        @click="handlePasswordToggle(toggleCallback)"
                                    >
                                        <i class="pi pi-eye-slash text-sm" aria-hidden="true" />
                                    </button>
                                </template>
                                <template #unmaskicon="{ toggleCallback }">
                                    <button
                                        ref="passwordToggleRef"
                                        type="button"
                                        class="absolute right-0.5 top-1/2 -translate-y-1/2 z-10 size-11 flex items-center justify-center rounded-lg text-slate-500 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#005BAC] cursor-pointer"
                                        aria-label="Tampilkan kata sandi"
                                        aria-pressed="false"
                                        @click="handlePasswordToggle(toggleCallback)"
                                    >
                                        <i class="pi pi-eye text-sm" aria-hidden="true" />
                                    </button>
                                </template>
                            </Password>
                        </div>
                        <p v-if="form.errors.password" id="password-error" role="alert" class="text-rose-700 text-xs mt-1.5 flex items-center gap-1.5">
                            <i class="pi pi-exclamation-circle text-xs shrink-0" aria-hidden="true" />
                            <span>{{ form.errors.password }}</span>
                        </p>
                    </div>

                    <!-- Row 3: Remember Me & Forgot Password -->
                    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1 pt-0.5">
                        <label class="inline-flex items-center gap-1.5 min-h-[44px] py-2 cursor-pointer select-none">
                            <Checkbox v-model="form.remember" :binary="true" inputId="remember" class="login-checkbox" />
                            <span class="text-xs text-slate-600 font-medium">Ingat sesi saya di perangkat ini</span>
                        </label>
                        <button
                            ref="forgotPasswordBtnRef"
                            type="button"
                            class="inline-flex items-center min-h-[44px] px-0 text-xs font-semibold text-[#005BAC] hover:text-[#004b8e] hover:underline rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#005BAC] cursor-pointer whitespace-nowrap"
                            @click="openHelpModal"
                        >
                            Lupa kata sandi?
                        </button>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-1">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            :class="[
                                'w-full min-h-[44px] py-2.5 px-4 rounded-xl font-bold text-sm text-white flex items-center justify-center gap-2 shadow-sm transition-all motion-reduce:transition-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#003e75]',
                                form.processing
                                    ? 'bg-[#003e75] cursor-not-allowed'
                                    : 'bg-[#005BAC] hover:bg-[#004b8e] active:scale-[0.99] motion-reduce:active:scale-100 cursor-pointer',
                            ]"
                        >
                            <i v-if="form.processing" class="pi pi-spin pi-spinner text-sm" aria-hidden="true" />
                            <i v-else class="pi pi-sign-in text-sm" aria-hidden="true" />
                            <span>{{ form.processing ? 'Memverifikasi Akun...' : 'Masuk ke Portal E-SDM' }}</span>
                        </button>
                    </div>
                </form>

                <!-- Helper Card: Login Instructions for KFPB Employees -->
                <div class="mt-5 p-3.5 sm:p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-1.5">
                    <div class="flex items-center gap-2 font-bold text-slate-800">
                        <i class="pi pi-info-circle text-[#005BAC]" aria-hidden="true" />
                        <span>Petunjuk Masuk Akun:</span>
                    </div>
                    <ul class="space-y-1 text-[11px] leading-relaxed text-slate-600 pl-5 list-disc">
                        <li>
                            Kata sandi akun tetap sama dengan sistem E-SDM sebelumnya (telah dimigrasikan).
                        </li>
                    </ul>
                </div>

                </main>

                <!-- Clean Footer (Always visible & responsive on mobile) -->
                <footer class="pt-4 pb-2 sm:pt-5 border-t border-slate-300/60 lg:border-slate-200/60 text-center text-xs text-slate-600 shrink-0 mt-5">
                    <div class="flex items-center justify-center gap-1.5 text-slate-700 font-medium mb-1">
                        <span>PT Kimia Farma Plant Banjaran</span>
                    </div>
                    <p class="text-[11px] text-slate-600 leading-normal">
                        © 2026 E-SDM KFPB • Seluruh hak cipta dilindungi.
                    </p>
                </footer>
            </div>
        </div>

        <!-- =============================================================== -->
        <!-- MODAL: Bantuan Reset / Lupa Kata Sandi                           -->
        <!-- =============================================================== -->
        <div
            v-if="showHelpModal"
            class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto transition-opacity motion-reduce:transition-none"
            @click.self="closeHelpModal"
        >
            <div
                ref="helpModalRef"
                role="dialog"
                aria-modal="true"
                aria-labelledby="help-modal-title"
                class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-md w-full my-auto p-6 transform transition-all motion-reduce:transition-none"
            >
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-200">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl bg-amber-50 border border-amber-200/80 text-amber-700 flex items-center justify-center shrink-0">
                            <i class="pi pi-key text-lg" aria-hidden="true" />
                        </div>
                        <div>
                            <h2 id="help-modal-title" class="text-sm font-bold text-slate-800">Bantuan Lupa Kata Sandi</h2>
                            <p class="text-[11px] text-slate-600">Layanan Pemulihan Akun E-SDM KFPB</p>
                        </div>
                    </div>
                    <button
                        ref="helpModalCloseBtnRef"
                        type="button"
                        aria-label="Tutup dialog bantuan kata sandi"
                        class="size-11 -mt-1.5 -mr-1.5 flex items-center justify-center rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#005BAC] transition-colors motion-reduce:transition-none cursor-pointer shrink-0"
                        @click="closeHelpModal"
                    >
                        <i class="pi pi-times text-sm" aria-hidden="true" />
                    </button>
                </div>

                <div class="py-4 text-xs text-slate-600 space-y-3 leading-relaxed">
                    <p>
                        Untuk menjaga keamanan data perusahaan dan privasi slip gaji, pemulihan kata sandi pegawai dilakukan melalui verifikasi unit kerja:
                    </p>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 space-y-2">
                        <div class="flex items-start gap-2">
                            <span class="size-5 rounded-full bg-[#005BAC]/10 text-[#005BAC] font-bold flex items-center justify-center shrink-0 text-[10px]">1</span>
                            <span>Datang langsung ke <strong>Divisi SDM & Organisasi</strong> (Gedung Manajemen Lt. 2 Plant Banjaran).</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="size-5 rounded-full bg-[#005BAC]/10 text-[#005BAC] font-bold flex items-center justify-center shrink-0 text-[10px]">2</span>
                            <span>Atau hubungi Helpdesk Internal melalui WhatsApp Hotline SDM KFPB dengan menyertakan <strong>NIP, Nama Lengkap, dan Unit Kerja</strong>.</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-blue-50/70 border border-blue-100 text-blue-900 flex items-center gap-3">
                        <i class="pi pi-phone text-lg text-[#005BAC] shrink-0" aria-hidden="true" />
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-[11px]">Helpdesk SDM & IT KFPB:</div>
                            <div class="text-[11px] text-blue-800">Ext: 214 / 215 (Jam Kerja Operasional)</div>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-200 flex justify-end">
                    <button
                        type="button"
                        class="min-h-[44px] px-5 py-2.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#005BAC] transition-colors motion-reduce:transition-none cursor-pointer"
                        @click="closeHelpModal"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.login-checkbox {
    --p-checkbox-border-color: #75859c;
    --p-checkbox-hover-border-color: #45556c;
    --p-checkbox-checked-background: #005bac;
    --p-checkbox-checked-border-color: #005bac;
    --p-checkbox-checked-hover-background: #004b8e;
    --p-checkbox-checked-hover-border-color: #004b8e;
    --p-checkbox-focus-border-color: #005bac;
    --p-checkbox-checked-focus-border-color: #003e75;
    --p-checkbox-focus-ring-width: 2px;
    --p-checkbox-focus-ring-style: solid;
    --p-checkbox-focus-ring-color: #005bac;
    --p-checkbox-focus-ring-offset: 2px;
}

.login-checkbox :deep(.p-checkbox-input) {
    top: -12px;
    left: 0;
    width: 44px;
    height: 44px;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-4px); }
    40%, 80% { transform: translateX(4px); }
}

.animate-shake {
    animation: shake 0.4s ease-in-out;
}

@media (prefers-reduced-motion: reduce) {
    .animate-shake {
        animation: none;
    }
}
</style>

