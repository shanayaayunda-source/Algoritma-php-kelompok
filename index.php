<?php
/**
 * Aplikasi Sistem Pemesanan Tiket Kereta Api
 * Mengimplementasikan 5 Struktur Data (ArrayList, Stack, Queue, Graph, Tree)
 * dan 3 Kategori Algoritma (Sorting, Searching, Traversal), disertai Profiling & Optimasi.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Import seluruh modul dan struktur data
require_once __DIR__ . '/src/Models/Stasiun.php';
require_once __DIR__ . '/src/Models/Kereta.php';
require_once __DIR__ . '/src/Models/Pemesanan.php';
require_once __DIR__ . '/src/DataStructures/Queue.php';
require_once __DIR__ . '/src/Algorithms/Sorting.php';
require_once __DIR__ . '/src/Algorithms/Searching.php';
require_once __DIR__ . '/src/Services/Dataset.php';
require_once __DIR__ . '/src/Services/Profiler.php';
require_once __DIR__ . '/src/DataStructures/Tree.php';

// Inisialisasi State di Session jika pertama kali dibuka
if (!isset($_SESSION['booking_queue'])) {
    $_SESSION['booking_queue'] = []; // Array penampung Queue (FIFO)
}
if (!isset($_SESSION['confirmed_orders'])) {
    $_SESSION['confirmed_orders'] = []; // Array penampung ArrayList pemesanan
}
if (!isset($_SESSION['passenger_history_stack'])) {
    $_SESSION['passenger_history_stack'] = []; // Stack untuk Undo (LIFO)
}
if (!isset($_SESSION['passenger_redo_stack'])) {
    $_SESSION['passenger_redo_stack'] = []; // Stack untuk Redo (LIFO)
}
if (!isset($_SESSION['passenger_draft'])) {
    $_SESSION['passenger_draft'] = [
        'nama' => '',
        'nik' => '',
        'no_hp' => ''
    ];
}

$notification = null;
$notificationType = 'info';

// Penanganan Form Action (POST)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Aksi Antrean Pemesanan (Queue: Enqueue)
    if ($action === 'enqueue_booking') {
        $nama = trim($_POST['nama_penumpang'] ?? '');
        $nik = trim($_POST['nik'] ?? '');
        $keretaId = $_POST['kereta_id'] ?? '';
        $kelas = $_POST['kelas'] ?? 'Eksekutif';

        if (!empty($nama) && !empty($nik) && !empty($keretaId)) {
            // Cari data kereta
            $keretaList = Dataset::getDaftarKereta();
            $selectedKereta = null;
            foreach ($keretaList as $k) {
                if ($k->id === $keretaId) {
                    $selectedKereta = $k;
                    break;
                }
            }

            if ($selectedKereta) {
                // Buat instance Queue
                $queue = new Queue($_SESSION['booking_queue']);
                $ticketId = 'TKT-' . rand(1000, 9999);
                $multiplier = ($kelas === 'Eksekutif') ? 1.5 : (($kelas === 'Bisnis') ? 1.2 : 1.0);
                $totalHarga = (int) ($selectedKereta->harga * $multiplier);

                $pesanan = new Pemesanan(
                    $ticketId,
                    $nama,
                    $nik,
                    $selectedKereta->id,
                    $selectedKereta->nama,
                    $kelas,
                    "Gerbong {$kelas} 1 (Kursi 0" . rand(1, 9) . "A)",
                    $totalHarga,
                    'MENUNGGU_ANTREAN'
                );

                // ENQUEUE: Masukkan ke antrean belakang
                $queue->enqueue($pesanan->toArray());
                $_SESSION['booking_queue'] = $queue->toArray();

                $notification = "Pesanan atas nama <strong>{$nama}</strong> berhasil masuk ke antrean pemrosesan tiket (Posisi antrean: #" . $queue->size() . ").";
                $notificationType = 'success';
            }
        } else {
            $notification = "Mohon lengkapi formulir pemesanan tiket.";
            $notificationType = 'warning';
        }
    }

    // 2. Aksi Pemrosesan Tiket dari Antrean (Queue: Dequeue -> ArrayList)
    elseif ($action === 'dequeue_booking') {
        $queue = new Queue($_SESSION['booking_queue']);
        if (!$queue->isEmpty()) {
            // DEQUEUE: Mengambil pesanan pertama sesuai FIFO
            $processedItem = $queue->dequeue();
            $_SESSION['booking_queue'] = $queue->toArray();

            // Ubah status dan simpan ke ArrayList pemesanan yang sukses
            $processedItem['status'] = 'BERHASIL_DIPROSES';
            $arrayList = new ArrayList($_SESSION['confirmed_orders']);
            $arrayList->add($processedItem);
            $_SESSION['confirmed_orders'] = $arrayList->toArray();

            $notification = "Tiket <strong>{$processedItem['id']}</strong> ({$processedItem['namaPenumpang']}) berhasil diproses dari antrean dan diterbitkan!";
            $notificationType = 'success';
        } else {
            $notification = "Antrean pemesanan kosong, tidak ada yang dapat diproses.";
            $notificationType = 'info';
        }
    }

    // 3. Aksi Simpan Draft Penumpang (Stack: Push untuk Undo)
    elseif ($action === 'save_passenger_draft') {
        $currDraft = $_SESSION['passenger_draft'];
        $newDraft = [
            'nama' => trim($_POST['draft_nama'] ?? ''),
            'nik' => trim($_POST['draft_nik'] ?? ''),
            'no_hp' => trim($_POST['draft_hp'] ?? '')
        ];

        // Jika ada perubahan, simpan versi lama ke Undo Stack (LIFO)
        if ($currDraft !== $newDraft) {
            $undoStack = new Stack($_SESSION['passenger_history_stack']);
            $undoStack->push($currDraft);
            $_SESSION['passenger_history_stack'] = $undoStack->toArray();

            // Reset Redo Stack setiap ada input baru
            $_SESSION['passenger_redo_stack'] = [];
            $_SESSION['passenger_draft'] = $newDraft;

            $notification = "Data draft penumpang berhasil disimpan. Riwayat perubahan dicatat ke Stack.";
            $notificationType = 'success';
        }
    }

    // 4. Aksi Undo Draft Penumpang (Stack: Pop dari Undo -> Push ke Redo)
    elseif ($action === 'undo_passenger_draft') {
        $undoStack = new Stack($_SESSION['passenger_history_stack']);
        if (!$undoStack->isEmpty()) {
            $redoStack = new Stack($_SESSION['passenger_redo_stack']);
            // Push draft saat ini ke redo stack
            $redoStack->push($_SESSION['passenger_draft']);
            $_SESSION['passenger_redo_stack'] = $redoStack->toArray();

            // Pop versi sebelumnya dari undo stack
            $previousDraft = $undoStack->pop();
            $_SESSION['passenger_history_stack'] = $undoStack->toArray();
            $_SESSION['passenger_draft'] = $previousDraft;

            $notification = "Berhasil melakukan Undo! Formulir dikembalikan ke versi sebelumnya.";
            $notificationType = 'info';
        } else {
            $notification = "Tidak ada riwayat untuk di-Undo.";
            $notificationType = 'warning';
        }
    }

    // 5. Aksi Redo Draft Penumpang (Stack: Pop dari Redo -> Push ke Undo)
    elseif ($action === 'redo_passenger_draft') {
        $redoStack = new Stack($_SESSION['passenger_redo_stack']);
        if (!$redoStack->isEmpty()) {
            $undoStack = new Stack($_SESSION['passenger_history_stack']);
            // Push draft saat ini ke undo stack
            $undoStack->push($_SESSION['passenger_draft']);
            $_SESSION['passenger_history_stack'] = $undoStack->toArray();

            // Pop dari redo stack
            $nextDraft = $redoStack->pop();
            $_SESSION['passenger_redo_stack'] = $redoStack->toArray();
            $_SESSION['passenger_draft'] = $nextDraft;

            $notification = "Berhasil melakukan Redo! Perubahan dikembalikan.";
            $notificationType = 'info';
        } else {
            $notification = "Tidak ada riwayat untuk di-Redo.";
            $notificationType = 'warning';
        }
    }

    // 6. Reset Seluruh State Demo
    elseif ($action === 'reset_all') {
        $_SESSION['booking_queue'] = [];
        $_SESSION['confirmed_orders'] = [];
        $_SESSION['passenger_history_stack'] = [];
        $_SESSION['passenger_redo_stack'] = [];
        $_SESSION['passenger_draft'] = ['nama' => '', 'nik' => '', 'no_hp' => ''];
        $notification = "Seluruh antrean, tumpukan undo/redo, dan riwayat pesanan berhasil direset.";
        $notificationType = 'info';
    }
}


// Menentukan Tab Aktif
$tab = $_GET['tab'] ?? 'jadwal';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pemesanan Tiket Kereta Api</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="container">
        <header>
            <h1>Sistem Pemesanan Tiket Kereta Api</h1>
            <p>Aplikasi Demonstrasi Struktur Data & Algoritma (PHP Native OOP)</p>
        </header>

        <?php if ($notification): ?>
            <div class="alert alert-<?= htmlspecialchars($notificationType) ?>">
                <?= $notification ?>
            </div>
        <?php endif; ?>

        <!-- Menu Tab Navigasi -->
        <div class="tabs">
            <a href="?tab=jadwal" class="tab-btn <?= $tab === 'jadwal' ? 'active' : '' ?>">1. Jadwal & Rute (Graph &
                Sort/Search)</a>
            <a href="?tab=antrean" class="tab-btn <?= $tab === 'antrean' ? 'active' : '' ?>">3. Antrean Pemesanan
                (Queue)</a>
        </div>

        <!-- ================= TAB 1: JADWAL & RUTE ================= -->
        <?php if ($tab === 'jadwal'): ?>
            <?php
            $stasiunList = Dataset::getDaftarStasiun();
            $keretaList = Dataset::getDaftarKereta();

            // Parameter filter dan pencarian
            $searchQuery = $_GET['q'] ?? '';
            $sortParam = $_GET['sort'] ?? 'none';
            $asalParam = $_GET['asal'] ?? '';
            $tujuanParam = $_GET['tujuan'] ?? '';

            // Terapkan Algoritma Pencarian (Linear Search)
            $displayKereta = $keretaList;
            if (!empty($searchQuery)) {
                $displayKereta = Searching::linearSearch($displayKereta, 'nama', $searchQuery, true);
            }

            // Terapkan Algoritma Sorting
            if ($sortParam === 'harga_asc') {
                // Menggunakan Bubble Sort teroptimasi
                $displayKereta = Sorting::bubbleSort($displayKereta, 'harga', 'asc');
            } elseif ($sortParam === 'harga_desc') {
                $displayKereta = Sorting::bubbleSort($displayKereta, 'harga', 'desc');
            } elseif ($sortParam === 'waktu_asc') {
                // Menggunakan Quick Sort
                $displayKereta = Sorting::quickSort($displayKereta, 'jamBerangkat', 'asc');
            }

            // Jalankan Algoritma Graf Dijkstra jika ada input rute
            $dijkstraResult = null;
            if (!empty($asalParam) && !empty($tujuanParam)) {
                $dijkstraResult = $graph->dijkstra($asalParam, $tujuanParam);
            }
            ?>


            <div class="panel">
                <h2>Katalog Jadwal Kereta Api (Sorting & Searching)</h2>

                <form method="GET" class="inline-form">
                    <input type="hidden" name="tab" value="jadwal">
                    <div>
                        <label for="q">Cari Nama Kereta (Linear Search):</label>
                        <input type="text" name="q" id="q" placeholder="Ketik kata kunci (misal: Argo)..."
                            value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                    <div>
                        <label for="sort">Urutkan Jadwal (Sorting):</label>
                        <select name="sort" id="sort">
                            <option value="none" <?= $sortParam === 'none' ? 'selected' : '' ?>>Default</option>
                            <option value="harga_asc" <?= $sortParam === 'harga_asc' ? 'selected' : '' ?>>Harga Termurah
                                (Bubble Sort)</option>
                            <option value="harga_desc" <?= $sortParam === 'harga_desc' ? 'selected' : '' ?>>Harga Termahal
                                (Bubble Sort)</option>
                            <option value="waktu_asc" <?= $sortParam === 'waktu_asc' ? 'selected' : '' ?>>Waktu Berangkat
                                Paling Awal (Quick Sort)</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                        <a href="?tab=jadwal" class="btn">Reset</a>
                    </div>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>ID Kereta</th>
                            <th>Nama Kereta Api</th>
                            <th>Rute Perjalanan</th>
                            <th>Jam Berangkat</th>
                            <th>Jam Tiba</th>
                            <th>Tarif Dasar</th>
                            <th>Sisa Kuota</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($displayKereta)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #888;">Tidak ada kereta yang cocok dengan
                                    kriteria pencarian.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($displayKereta as $k): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($k->id) ?></strong></td>
                                    <td><?= htmlspecialchars($k->nama) ?></td>
                                    <td><?= htmlspecialchars($k->stasiunAsal) ?> &rarr; <?= htmlspecialchars($k->stasiunTujuan) ?>
                                    </td>
                                    <td><?= htmlspecialchars($k->jamBerangkat) ?></td>
                                    <td><?= htmlspecialchars($k->jamTiba) ?></td>
                                    <td>Rp <?= number_format($k->harga, 0, ',', '.') ?></td>
                                    <td><?= $k->kuota ?> Kursi</td>
                                    <td>
                                        <a href="?tab=antrean&pilih_id=<?= urlencode($k->id) ?>" class="btn btn-success"
                                            style="font-size: 11px; padding: 3px 8px;">Pesan</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>



            <!-- ================= TAB 3: ANTREAN PEMESANAN (QUEUE) ================= -->
        <?php elseif ($tab === 'antrean'): ?>
            <?php
            $selectedKeretaId = $_GET['pilih_id'] ?? 'KA-101';
            $keretaList = Dataset::getDaftarKereta();
            $queueObj = new Queue($_SESSION['booking_queue']);
            $queueItems = $queueObj->toArray();
            ?>
            <div class="panel">
                <h2>Form Pendaftaran Tiket ke Antrean (Queue: Enqueue)</h2>
                <p>Menerapkan prinsip <strong>FIFO (First In, First Out)</strong>: Pemesan yang masuk antrean lebih awal
                    akan diproses terlebih dahulu.</p>

                <form method="POST" style="margin-top: 14px;">
                    <input type="hidden" name="action" value="enqueue_booking">

                    <div class="form-group">
                        <label for="kereta_id">Pilih Jadwal Kereta:</label>
                        <select name="kereta_id" id="kereta_id" required>
                            <?php foreach ($keretaList as $k): ?>
                                <option value="<?= $k->id ?>" <?= $selectedKeretaId === $k->id ? 'selected' : '' ?>>
                                    <?= $k->id ?> - <?= $k->nama ?> (<?= $k->stasiunAsal ?> &rarr; <?= $k->stasiunTujuan ?>, Rp
                                    <?= number_format($k->harga, 0, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="kelas">Pilih Kelas Tiket:</label>
                        <select name="kelas" id="kelas">
                            <option value="Eksekutif">Eksekutif (Pengali Tarif 1.5x)</option>
                            <option value="Bisnis">Bisnis (Pengali Tarif 1.2x)</option>
                            <option value="Ekonomi">Ekonomi (Pengali Tarif 1.0x)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nama_penumpang">Nama Lengkap Penumpang:</label>
                        <input type="text" name="nama_penumpang" id="nama_penumpang" placeholder="Contoh: Dzaka Pratama"
                            value="<?= htmlspecialchars($_SESSION['passenger_draft']['nama']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nik">Nomor Induk Kependudukan (NIK):</label>
                        <input type="text" name="nik" id="nik" placeholder="Contoh: 3201123456780001"
                            value="<?= htmlspecialchars($_SESSION['passenger_draft']['nik']) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Masukkan ke Antrean Pemesanan (Enqueue)</button>
                </form>
            </div>

            <div class="panel">
                <h2>Daftar Antrean Tiket Menunggu Pemrosesan (Queue: FIFO)</h2>
                <p>Total antrean saat ini: <strong><?= $queueObj->size() ?> antrean</strong></p>

                <?php if (!$queueObj->isEmpty()): ?>
                    <form method="POST" style="margin-bottom: 12px;">
                        <input type="hidden" name="action" value="dequeue_booking">
                        <button type="submit" class="btn btn-success">Proses Tiket Terdepan (Dequeue)</button>
                    </form>

                    <table>
                        <thead>
                            <tr>
                                <th>Nomor Antrean</th>
                                <th>Kode Tiket</th>
                                <th>Nama Penumpang</th>
                                <th>NIK</th>
                                <th>Kereta & Rute</th>
                                <th>Kelas & Gerbong</th>
                                <th>Total Tarif</th>
                                <th>Status Antrean</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($queueItems as $index => $item): ?>
                                <tr style="<?= $index === 0 ? 'background-color: #e8f4fd; font-weight: bold;' : '' ?>">
                                    <td>Antrean #<?= $index + 1 ?>             <?= $index === 0 ? '(Paling Depan)' : '' ?></td>
                                    <td><code><?= htmlspecialchars($item['id']) ?></code></td>
                                    <td><?= htmlspecialchars($item['namaPenumpang']) ?></td>
                                    <td><?= htmlspecialchars($item['nik']) ?></td>
                                    <td><?= htmlspecialchars($item['namaKereta']) ?></td>
                                    <td><?= htmlspecialchars($item['kelas']) ?> - <?= htmlspecialchars($item['gerbong']) ?></td>
                                    <td>Rp <?= number_format($item['totalHarga'], 0, ',', '.') ?></td>
                                    <td><span class="badge-tag"><?= htmlspecialchars($item['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">Saat ini antrean pemesanan tiket kosong. Silakan isi form di atas untuk
                        menambah antrean.</div>
                <?php endif; ?>

                <div style="font-size: 12px; color: #555; margin-top: 14px;">
                    <span class="badge-tag">Struktur Data: Queue (FIFO)</span>
                    <span class="badge-tag">Operasi: Enqueue, Dequeue, Peek</span>
                </div>
            </div>

            <!-- ================= TAB 4: FORM & RIWAYAT (STACK & ARRAYLIST) ================= -->

            <!-- ================= TAB 5: PROFILING & OPTIMASI ================= -->
        <?php elseif ($tab === 'profiling'): ?>
            <?php
            $keretaList = Dataset::getDaftarKereta();
            $iterationsSort = (int) ($_GET['iter_sort'] ?? 300);
            $iterationsSearch = (int) ($_GET['iter_search'] ?? 1000);

            $sortBenchmark = Profiler::benchmarkSorting($keretaList, $iterationsSort);
            $searchBenchmark = Profiler::benchmarkSearching($keretaList, $iterationsSearch);
            ?>
            <div class="panel">
                <h2>Hasil Profiling Kinerja Algoritma (Microtime & Memory)</h2>
                <p>Mengukur durasi waktu eksekusi presisi tinggi (dalam milidetik) dan penggunaan memori RAM untuk
                    membandingkan efisiensi algoritma.</p>

                <h3>1. Komparasi Algoritma Pengurutan (Sorting)</h3>
                <p>Dataset: 12 Jadwal Kereta Api | Jumlah Iterasi Pengujian: <strong><?= $iterationsSort ?>x</strong></p>
                <table>
                    <thead>
                        <tr>
                            <th>Algoritma</th>
                            <th>Kompleksitas Teoretis</th>
                            <th>Total Waktu (ms)</th>
                            <th>Rata-rata Waktu / Iterasi (ms)</th>
                            <th>Tambahan Memori (KB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Bubble Sort (Teroptimasi Early Break)</strong></td>
                            <td>O(N<sup>2</sup>)</td>
                            <td><?= $sortBenchmark['bubble_sort']['total_time_ms'] ?> ms</td>
                            <td><?= $sortBenchmark['bubble_sort']['avg_time_ms'] ?> ms</td>
                            <td><?= $sortBenchmark['bubble_sort']['memory_kb'] ?> KB</td>
                        </tr>
                        <tr>
                            <td><strong>Quick Sort (Divide & Conquer)</strong></td>
                            <td>O(N log N)</td>
                            <td><?= $sortBenchmark['quick_sort']['total_time_ms'] ?> ms</td>
                            <td><?= $sortBenchmark['quick_sort']['avg_time_ms'] ?> ms</td>
                            <td><?= $sortBenchmark['quick_sort']['memory_kb'] ?> KB</td>
                        </tr>
                    </tbody>
                </table>

                <h3>2. Komparasi Algoritma Pencarian (Searching)</h3>
                <p>Target Pencarian: ID Kereta <code>KA-110</code> | Jumlah Iterasi Pengujian:
                    <strong><?= $iterationsSearch ?>x</strong>
                </p>
                <table>
                    <thead>
                        <tr>
                            <th>Algoritma</th>
                            <th>Kompleksitas Teoretis</th>
                            <th>Total Waktu (ms)</th>
                            <th>Rata-rata Waktu / Iterasi (ms)</th>
                            <th>Tambahan Memori (KB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Linear Search (Early Break)</strong></td>
                            <td>O(N)</td>
                            <td><?= $searchBenchmark['linear_search']['total_time_ms'] ?> ms</td>
                            <td><?= $searchBenchmark['linear_search']['avg_time_ms'] ?> ms</td>
                            <td><?= $searchBenchmark['linear_search']['memory_kb'] ?> KB</td>
                        </tr>
                        <tr>
                            <td><strong>Binary Search (Divide & Conquer)</strong></td>
                            <td>O(log N)</td>
                            <td><?= $searchBenchmark['binary_search']['total_time_ms'] ?> ms</td>
                            <td><?= $searchBenchmark['binary_search']['avg_time_ms'] ?> ms</td>
                            <td><?= $searchBenchmark['binary_search']['memory_kb'] ?> KB</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="panel">
                <h2>Dokumentasi Optimasi Lokal yang Diterapkan</h2>
                <ul>
                    <li><strong>Optimasi Bubble Sort:</strong> Menambahkan flag <code>$swapped</code>. Jika tidak ada
                        pertukaran posisi elemen pada suatu pass iterasi luar, algoritma melakukan <em>early break</em>
                        karena data sudah pasti terurut sempurna.</li>
                    <li><strong>Optimasi Linear Search:</strong> Menambahkan parameter <code>$stopAtFirst</code> untuk
                        pencarian ID unik, sehingga langsung melakukan penghentian iterasi begitu kecocokan pertama
                        ditemukan.</li>
                    <li><strong>Optimasi Graf Dijkstra:</strong> Menggunakan <em>early exit</em> pada loop relaksasi saat
                        simpul terkecil yang diekstraksi adalah simpul tujuan (<em>end vertex</em>), tanpa perlu
                        menyelesaikan seluruh simpul graf.</li>
                    <li><strong>Optimasi Representasi Graf:</strong> Menggunakan struktur <em>Adjacency List</em> dengan
                        indeks array asosiatif (Hash Map) untuk akses tetangga simpul dalam O(1).</li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Tombol Reset Sesi Demo -->
        <div style="margin-top: 20px; text-align: right;">
            <form method="POST"
                onsubmit="return confirm('Apakah Anda yakin ingin mereset seluruh data antrean dan riwayat demo?');">
                <input type="hidden" name="action" value="reset_all">
                <button type="submit" class="btn btn-danger" style="font-size: 11px;">Reset Seluruh Sesi Demo</button>
            </form>
        </div>

        <footer>
            <p>&copy; 2026 Tugas Kuliah Struktur Data & Algoritma - Sistem Pemesanan Tiket Kereta Api (PHP Native)</p>
        </footer>
</div> 

    <div style="background: #fff; padding: 20px; margin-top: 20px; border-radius: 8px;">
        <h3>Visualisasi Struktur Tree Kereta Api</h3>
        <?php
        $sampleData = [
            'id' => 'ROOT_01',
            'name' => 'KA Argo Wilis',
            'type' => 'ROOT',
            'children' => [
                [
                    'id' => 'KLS_01',
                    'name' => 'Eksekutif',
                    'type' => 'KELAS',
                    'children' => [
                        ['id' => 'GBG_01', 'name' => 'Gerbong 1 (K1)', 'type' => 'GERBONG'],
                        ['id' => 'GBG_02', 'name' => 'Gerbong 2 (K1)', 'type' => 'GERBONG']
                    ]
                ]
            ]
        ];

        $tree = Tree::buildFromArray($sampleData);
        echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px; text-align: left;'>";
        echo htmlspecialchars($tree->renderHierarchy());
        echo "</pre>";
        ?>
    </div>

</body>
</html>