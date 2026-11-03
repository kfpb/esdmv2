# eSDMv2-KFPB

Aplikasi web modern dengan integrasi **Laravel 13**, **Inertia.js v2**, **Vue 3**, **Tailwind CSS v4**, dan **PrimeVue (Aura Theme)**.

---

## 🚀 Tech Stack

| Layer | Package / Tool | Versi |
|---|---|---|
| Backend | `laravel/laravel` (Laravel 13) | v13.x |
| Glue | `inertiajs/inertia-laravel` | v2.x |
| Frontend | `vue` (Composition API `<script setup>`) | v3.5 |
| UI Framework | `primevue` + `@primeuix/themes` (Preset Aura) + `primeicons` | v5.x |
| Build Tool | `vite` + `@vitejs/plugin-vue` | v8.x |
| Styling | `tailwindcss` + `@tailwindcss/vite` | v4.x |
| Routes Helper | `tightenco/ziggy` | v2.x |
| Charts | `chart.js` | v4.x |

---

## 📂 Struktur Project

```
eSDMv2-KFPB/
├── app/
│   └── Http/Middleware/
│       └── HandleInertiaRequests.php      # Middleware shared data Inertia
├── resources/
│   ├── css/
│   │   └── app.css                        # CSS Layers Tailwind v4 & PrimeVue
│   ├── js/
│   │   ├── Layouts/
│   │   │   └── AppLayout.vue              # Layout Admin (Sidebar, Topbar, User menu)
│   │   ├── Pages/
│   │   │   ├── Home.vue                   # Dashboard (Stats, Revenue chart, Orders table)
│   │   │   ├── Components.vue             # Showcase komponen PrimeVue (Buttons, Inputs, dll)
│   │   │   ├── Datatable.vue              # Data table dengan filtering, pagination, sorting
│   │   │   ├── Forms.vue                  # Form builder (FloatLabel, DatePicker, MultiSelect)
│   │   │   └── Charts.vue                 # Chart.js showcase (Bar, Line, Pie, Radar)
│   │   ├── app.js                         # Inisialisasi Inertia + PrimeVue Aura theme
│   │   └── bootstrap.js                   # Axios setup
│   └── views/
│       └── app.blade.php                  # Root template blade
├── routes/
│   └── web.php                            # Definisi rute dashboard & halaman demo
└── vite.config.js                         # Vite plugin konfigurasi
```

---

## 🏃 Menjalankan Aplikasi

### 1. Development Mode
Jalankan server Laravel dan Vite secara berdampingan:

```bash
# Terminal 1 — Laravel Dev Server
php artisan serve

# Terminal 2 — Vite Dev Server (HMR)
npm run dev
```

Buka browser di: **http://127.0.0.1:8000**

### 2. Production Build
```bash
npm run build
php artisan serve
```

---

## 🧭 Halaman Demo Template

1. **Dashboard (`/`)**: Kartu metrik, grafik statistik penjualan, dan ringkasan tabel order.
2. **Components (`/components`)**: Variasi button, tag, badge, toggle switch, slider, rating, dan message alert.
3. **Data Table (`/datatable`)**: Pengelolaan data tabel interaktif dengan search filter, status dropdown, checkbox selection, dan pagination.
4. **Forms (`/forms`)**: Form input interaktif dengan FloatLabel, IftaLabel, DatePicker, MultiSelect, Textarea, Password masking, dan FileUpload.
5. **Charts (`/charts`)**: Grafik Bar, Line, Pie, dan Radar bertenaga Chart.js.
