<?php
// Load konfigurasi token (tidak di-commit ke git)
if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';

$db_file = __DIR__ . '/database.sqlite';
$db = new PDO("sqlite:$db_file");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    address TEXT NOT NULL,
    detail TEXT NOT NULL,
    category TEXT NOT NULL,
    latitude REAL NOT NULL,
    longitude REAL NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// sample data
$count = $db->query("SELECT COUNT(*) FROM records")->fetchColumn();
if ($count == 0) {
    $samples = [
        ['Nichi Izakaya Bar & Yakiniku', 'Jl. Sumbawa No.12, Merdeka, Kota Bandung', 'Restoran ala jepang dengan vibes hommie yang begitu cozy', 'Kuliner', -6.914644, 107.618136],
        ['Universitas Padjajaran', 'Jl. Dipatiukur No.35, Bandung', 'Universitas negeri terkemuka di Jawa Barat dengan berbagai program studi.', 'Pendidikan', -6.893451, 107.617076],
        ['Kawah Putih', 'Jl. Raya Ciwidey, Bandung Selatan', 'Danau vulkanik dengan pemandangan menakjubkan di ketinggian 2.430 mdpl.', 'Pariwisata', -7.166130, 107.402244],
        ['RSUP Dr. Hasan Sadikin', 'Jl. Pasteur No.38, Bandung', 'Rumah sakit pemerintah rujukan utama di Jawa Barat.', 'Kesehatan', -6.898091, 107.598430],
        ['Trans Studio Bandung', 'Jl. Gatot Subroto No.289, Bandung', 'Theme park indoor terbesar di Bandung dengan berbagai wahana seru.', 'Hiburan', -6.925729, 107.636520],
        ['Paskal 23', 'Jl. Pasir Kaliki No.25-27, Kb. Jeruk, Kec. Andir, Kota Bandung', 'Pusat perbelanjaan di Bandung.', 'Perbelanjaan', -6.915344, 107.594228],
        ['Masjid Raya Bandung', 'Jl. Dalem Kaum No.14, Kota Bandung', 'Masjid agung kebanggaan kota Bandung yang terletak di alun-alun kota.', 'Ibadah', -6.921731, 107.606285],
        ['Gedung Sate', 'Jl. Diponegoro No.22, Kota Bandung', 'Ikon kota Bandung yang menjadi pusat pemerintahan Provinsi Jawa Barat.', 'Pariwisata', -6.902560, 107.618781],
    ];
    $stmt = $db->prepare("INSERT INTO records (title, address, detail, category, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($samples as $s) $stmt->execute($s);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gmaps</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- HEADER -->
<header class="app-header">
    <div class="header-brand">
        <div class="brand-icon"><i class="fa-solid fa-location-dot"></i></div>
        <div class="brand-text">
            <span class="brand-name">Gmaps</span>
            <span class="brand-tagline">Mapping</span>
        </div>
    </div>
    <div class="header-actions">
        <button class="btn-add" id="btnAddRecord">
            <i class="fa-solid fa-plus"></i> Tambah Record
        </button>
    </div>
</header>

<!-- MAIN LAYOUT -->
<div class="app-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <!-- Filter -->
        <div class="sidebar-section">
            <div class="section-label">Filter</div>
            <div class="filter-chips" id="filterChips">
                <button class="chip chip-all active" data-cat="all">
                    <i class="fa-solid fa-layer-group"></i> Semua
                </button>
            </div>
        </div>

        <!-- Record Table -->
        <div class="sidebar-section flex-grow">
            <div class="section-label">
                Daftar Lokasi
                <span class="record-count" id="recordCount">0 record</span>
            </div>
            <div class="record-list" id="recordList">
                <div class="loading-state">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <span>Memuat data...</span>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAP -->
    <main class="map-container">
        <div id="map"></div>
        <div class="map-info-bar" id="mapInfoBar">
            <i class="fa-solid fa-circle-info"></i>
            <span id="mapInfoText">Geser peta untuk melihat lokasi di area tersebut</span>
        </div>
    </main>
</div>

<!-- ADD RECORD -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">
                <i class="fa-solid fa-map-pin"></i>
                <span id="modalTitleText">Tambah Lokasi Baru</span>
            </h2>
            <button class="modal-close" id="modalClose"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="recordForm">
                <input type="hidden" id="recordId" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label>Judul Kegiatan / Tempat <span class="req">*</span></label>
                        <input type="text" id="fTitle" placeholder="Contoh: Warung Makan Bu Siti" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori <span class="req">*</span></label>
                        <select id="fCategory" required>
                            <option value="">Pilih Kategori</option>
                            <option value="Kuliner">🍜Kuliner</option>
                            <option value="Pendidikan">📚Pendidikan</option>
                            <option value="Pariwisata">🌄Pariwisata</option>
                            <option value="Kesehatan">🏥Kesehatan</option>
                            <option value="Hiburan">🎭Hiburan</option>
                            <option value="Perbelanjaan">🛍️Perbelanjaan</option>
                            <option value="Ibadah">🕌Ibadah</option>
                            <option value="Olahraga">⚽Olahraga</option>
                            <option value="Perkantoran">💻Perkantoran</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Alamat <span class="req">*</span></label>
                    <input type="text" id="fAddress" placeholder="Alamat lengkap lokasi" required>
                </div>
                <div class="form-group">
                    <label>Detail Kegiatan / Deskripsi <span class="req">*</span></label>
                    <textarea id="fDetail" rows="3" placeholder="Deskripsi singkat tentang lokasi atau kegiatan ini..." required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Latitude <span class="req">*</span></label>
                        <input type="number" id="fLat" step="any" placeholder="-6.9175" required>
                    </div>
                    <div class="form-group">
                        <label>Longitude <span class="req">*</span></label>
                        <input type="number" id="fLng" step="any" placeholder="107.6191" required>
                    </div>
                </div>
                <div class="coord-hint">
                    <i class="fa-solid fa-crosshairs"></i>
                    <span>Klik pada peta di bawah untuk memilih koordinat secara otomatis</span>
                </div>
                <!-- Coordinate -->
                <div class="mini-map-wrapper">
                    <div id="miniMap"></div>
                    <div class="mini-map-marker"><i class="fa-solid fa-location-dot"></i></div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" id="btnCancel">Batal</button>
            <button class="btn-save" id="btnSave">
                <i class="fa-solid fa-floppy-disk"></i> Simpan Record
            </button>
        </div>
    </div>
</div>

<!-- INFO BOX POPUP -->
<div class="info-popup" id="infoPopup" style="display:none;">
    <button class="info-popup-close" id="infoPopupClose"><i class="fa-solid fa-xmark"></i></button>
    <div class="info-popup-cat" id="ipCat"></div>
    <h3 class="info-popup-title" id="ipTitle"></h3>
    <div class="info-popup-addr"><i class="fa-solid fa-location-dot"></i> <span id="ipAddr"></span></div>
    <p class="info-popup-detail" id="ipDetail"></p>
    <div class="info-popup-actions">
        <button class="btn-edit-rec" id="btnEditRec"><i class="fa-solid fa-pen"></i> Edit</button>
        <button class="btn-del-rec" id="btnDelRec"><i class="fa-solid fa-trash"></i> Hapus</button>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>