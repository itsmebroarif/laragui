# ⚡ Localhost Studio — Laragon Developer Dashboard

> **Modern, Minimal Neo-Brutalist Developer Workspace & Project Manager untuk Laragon (Localhost).**

Transformasi tampilan default `http://localhost` Laragon menjadi workspace developer yang elegan, responsif, interaktif, dan fungsional. Dilengkapi dengan deteksi project cerdas, visualisasi dependensi berbasis **D3.js**, interaksi mulus dengan **SweetAlert2**, built-in **Source Code Viewer & Editor**, serta **Scaffolding/Instalasi Project Instan** (Laravel, Vue, React, CodeIgniter 4, dsb).

---

## 💡 Mengapa Harus Menggunakan Localhost Studio?

Halaman default Laragon standar biasanya hanya menampilkan teks statis atau direktori raw file tanpa konteks. **Localhost Studio** hadir untuk memberikan pengalaman developer layaknya IDE/Workspace terpadu:

1. 🎯 **Semua Project dalam Satu Tempat**: Tidak perlu lagi membuka explorer berulang kali mencari folder project di `C:\laragon\www`.
2. 🚀 **Scaffolding Proyek Cepat**: Buat project baru (Laravel, Vue, React, CodeIgniter) langsung dengan 1 klik tanpa harus mengetik perintah terminal manual.
3. 👁️ **Preview & Edit Source Code Langsung**: Lihat dan ubah isi file tanpa harus membuka VS Code atau editor eksternal untuk edit cepat.
4. 📊 **Visualisasi Arsitektur dengan D3.js**: Pahami hierarki direktori dan keterhubungan file melalui *Force-Directed Graph* yang interaktif.
5. 🛡️ **Aman & Ringan**: Dibuat dengan PHP Native murni dan Vanilla JavaScript tanpa bloatware framework berat, serta dilengkapi perlindungan denylist untuk file rahasia (`.env`, `.git`, kunci privat).
6. 🎨 **Estetika Minimal Neo-Brutalism**: Desain bold dengan border tegas, hard shadow, dan kontras tinggi yang ramah mata serta responsif di desktop maupun perangkat mobile.

---

## ✨ Fitur Unggulan

### 1. 🚀 Project Discovery & Direct Navigation
- Otomatis mendeteksi tipe project: **Laravel**, **CodeIgniter 4**, **Vue.js**, **React**, **Vite App**, **PHP Web**, atau **Static HTML**.
- Tombol **Visit Page** langsung mengarahkan ke entry point yang sesuai (seperti `/public` untuk Laravel dan CI4).
- Akses cepat langsung ke direktori khusus kode seperti `index.php?dir=code`.

### 2. 📦 Project Scaffolding & Framework Installer
Install project baru langsung dari antarmuka web ke folder tujuan:
- 🔥 **Laravel**: `composer create-project laravel/laravel`
- 🟢 **Vue.js**: Template modern via Vite (`npm create vite@latest -- --template vue`)
- ⚛️ **React**: Template modern via Vite (`npm create vite@latest -- --template react`)
- ⚡ **CodeIgniter 4**: `composer create-project codeigniter4/appstarter`
- 🐘 **Clean PHP Starter**: Boilerplate aplikasi PHP bersih siap pakai.
- 🌐 **Modern HTML5 Starter**: Template web statis dengan struktur CSS & JS rapi.

### 3. 📝 File Management & In-Browser Code Editor
- **Create File & Folder**: Buat file atau folder baru di direktori manapun dengan modal SweetAlert2 interaktif.
- **Source Code Viewer & Editor**: Buka file teks (`.php`, `.js`, `.css`, `.json`, `.sql`, `.html`, `.md`, dll), periksa kodenya, lakukan perubahan, dan simpan langsung ke disk server.
- **Delete Management**: Hapus file atau folder yang tidak terpakai dengan dialog konfirmasi aman.
- **Proteksi File Rahasia**: File `.env`, `.git`, SSH keys, dan credential lainnya otomatis diblokir dari akses baca dan edit demi keamanan lokal.

### 4. 🌐 Interactive Workspace Graph (D3.js)
- Visualisasi hubungan antara root document, direktori, project, dan file menggunakan algoritma fisika interaktif D3.js (*Force-Directed Graph*).
- Node dapat ditarik (*drag & drop*) dan dilengkapi dengan indikator warna sesuai ekosistem teknologi.

### 5. ⚙️ Real-time Runtime & Environment Checker
- Deteksi otomatis toolchain developer lokal:
  - **PHP CLI & SAPI** (deteksi versi aktif di Laragon)
  - **Composer** (via `composer.phar` bawaan Laragon)
  - **Node.js & npm**
  - **Git Version Control**
  - **Database Service (MySQL / MariaDB)**
- Status real-time web server (Apache/Nginx).

### 6. 📱 Desain Responsif & Neo-Brutalism UI
- Menyesuaikan sempurna dengan berbagai ukuran layar (Desktop, Laptop, Tablet, Smartphone).
- Sidebar navigasi yang fleksibel (berubah menjadi mobile drawer di layar kecil).
- Real-time Clock Widget yang menunjukkan waktu dan tanggal lokal.
- Live Search & Filter untuk mencari file/project secara instan tanpa reload halaman.
- Pilihan layout **Grid View** atau **List View**.

---

## 🛠️ Stack Teknologi

- **Backend**: Native PHP 8.x (Ringan, tanpa dependensi vendor/framework tambahan)
- **Frontend Core**: HTML5, Modern CSS3 (Variables, Flexbox, Grid), Vanilla JavaScript (ES6+)
- **Visualisasi**: [D3.js v7](https://d3js.org/)
- **Dialog & UI Alerts**: [SweetAlert2 (swal2)](https://sweetalert2.github.io/)
- **Syntax Highlighting**: [Prism.js](https://prismjs.com/)
- **Icons & Typography**: [Bootstrap Icons](https://icons.getbootstrap.com/), Google Fonts (*Space Grotesk*, *Inter*, *JetBrains Mono*)

---

## 🚀 Cara Instalasi & Penggunaan

### Langkah 1: Penempatan File
Letakkan file `index.php` ke direktori web root Laragon Anda:
```text
C:\laragon\www\
├── index.php
├── prd.md
└── code\
```

### Langkah 2: Buka di Browser
Buka browser favorit Anda dan akses:
```
http://localhost
```
atau
```
http://localhost/index.php
```

### Langkah 3: Mulai Bekerja!
- Klik **"Install Project"** untuk membuat project Laravel, Vue, React, atau CodeIgniter baru.
- Klik **"Create File"** untuk membuat file kode baru.
- Jelajahi folder, klik **"Visit"** untuk membuka web project Anda di tab baru.
- Klik **"View Source"** pada file apa pun untuk membaca atau mengedit kodenya.

---

## 📋 Keamanan (Security Rules)

- **Workspace Sandboxing**: Seluruh akses pembacaan, pembuatan, dan penghapusan file dikunci agar tidak dapat keluar dari canonical path `C:\laragon\www`.
- **Anti Directory Traversal**: Parameter path yang mengandung upaya eksploitasi traversal (`../`) otomatis ditolak.
- **Sensitive File Protection**: File rahasia seperti `.env`, credential, dan private keys otomatis disembunyikan dan dicegah dari pembacaan API.

---

## 📄 Lisensi

Open-source di bawah lisensi [MIT](LICENSE). Bebas digunakan dan dimodifikasi untuk kebutuhan local development environment Anda.

