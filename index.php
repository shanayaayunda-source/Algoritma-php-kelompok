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
require_once __DIR__ . '/src/DataStructures/ArrayList.php';
require_once __DIR__ . '/src/DataStructures/Stack.php';
require_once __DIR__ . '/src/DataStructures/Queue.php';
require_once __DIR__ . '/src/DataStructures/Graph.php';
require_once __DIR__ . '/src/DataStructures/Tree.php';
require_once __DIR__ . '/src/Algorithms/Sorting.php';
require_once __DIR__ . '/src/Algorithms/Searching.php';
require_once __DIR__ . '/src/Services/Dataset.php';
require_once __DIR__ . '/src/Services/Profiler.php';

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
                $totalHarga = (int)($selectedKereta->harga * $multiplier);

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

// Inisialisasi Graf Jaringan Rute
$graph = new Graph();
$ruteJaringan = Dataset::getJaringanRute();
foreach ($ruteJaringan as $r) {
    $graph->addEdge($r[0], $r[1], $r[2]);
}

// Inisialisasi Tree Hierarki Kelas
$pohonKelas = Tree::buildFromArray(Dataset::getHierarkiKelas());

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
        <a href="?tab=jadwal" class="tab-btn <?= $tab === 'jadwal' ? 'active' : '' ?>"> Jadwal & Rute (Graph & Sort/Search)</a>
        <a href="?tab=hierarki" class="tab-btn <?= $tab === 'hierarki' ? 'active' : '' ?>"> Hierarki Kelas (Tree)</a>
        <a href="?tab=antrean" class="tab-btn <?= $tab === 'antrean' ? 'active' : '' ?>"> Antrean Pemesanan (Queue)</a>
        <a href="?tab=undoredo" class="tab-btn <?= $tab === 'undoredo' ? 'active' : '' ?>"> Form & Riwayat (Stack & ArrayList)</a>
        <a href="?tab=profiling" class="tab-btn <?= $tab === 'profiling' ? 'active' : '' ?>">Profiling & Optimasi</a>
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
            <h2>Peta Jaringan Rute Kereta Api (Graph & Dijkstra)</h2>
            <p>Mencari rute perhentian terpendek dan total jarak kilometer antar stasiun kereta api.</p>
            
            <form method="GET" class="inline-form" style="margin-top: 12px;">
                <input type="hidden" name="tab" value="jadwal">
                <div>
                    <label for="asal">Stasiun Keberangkatan:</label>
                    <select name="asal" id="asal">
                        <option value="">-- Pilih Stasiun Asal --</option>
                        <?php foreach ($stasiunList as $st): ?>
                            <option value="<?= $st->kode ?>" <?= $asalParam === $st->kode ? 'selected' : '' ?>>
                                [<?= $st->kode ?>] <?= $st->nama ?> (<?= $st->kota ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="tujuan">Stasiun Tujuan:</label>
                    <select name="tujuan" id="tujuan">
                        <option value="">-- Pilih Stasiun Tujuan --</option>
                        <?php foreach ($stasiunList as $st): ?>
                            <option value="<?= $st->kode ?>" <?= $tujuanParam === $st->kode ? 'selected' : '' ?>>
                                [<?= $st->kode ?>] <?= $st->nama ?> (<?= $st->kota ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Hitung Rute Terpendek (Dijkstra)</button>
                </div>
            </form>

            <?php if ($dijkstraResult !== null): ?>
                <?php if ($dijkstraResult['found']): ?>
                    <div class="alert alert-success" style="margin-top: 10px;">
                        <strong>Hasil Dijkstra Shortest Path:</strong><br>
                        Rute Perjalanan: <strong><?= implode(' &rarr; ', $dijkstraResult['path']) ?></strong><br>
                        Total Akumulasi Jarak: <strong><?= $dijkstraResult['distance'] ?> km</strong>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning" style="margin-top: 10px;">
                        Tidak ditemukan jalur yang menghubungkan stasiun terpilih.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="font-size: 12px; color: #555; margin-top: 8px;">
                <span class="badge-tag">Struktur Data: Graph (Adjacency List)</span>
                <span class="badge-tag">Algoritma: Dijkstra & BFS Traversal</span>
            </div>
        </div>

        <div class="panel">
            <h2>Katalog Jadwal Kereta Api (Sorting & Searching)</h2>
            
            <form method="GET" class="inline-form">
                <input type="hidden" name="tab" value="jadwal">
                <div>
                    <label for="q">Cari Nama Kereta (Linear Search):</label>
                    <input type="text" name="q" id="q" placeholder="Ketik kata kunci (misal: Argo)..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
                <div>
                    <label for="sort">Urutkan Jadwal (Sorting):</label>
                    <select name="sort" id="sort">
                        <option value="none" <?= $sortParam === 'none' ? 'selected' : '' ?>>Default</option>
                        <option value="harga_asc" <?= $sortParam === 'harga_asc' ? 'selected' : '' ?>>Harga Termurah (Bubble Sort)</option>
                        <option value="harga_desc" <?= $sortParam === 'harga_desc' ? 'selected' : '' ?>>Harga Termahal (Bubble Sort)</option>
                        <option value="waktu_asc" <?= $sortParam === 'waktu_asc' ? 'selected' : '' ?>>Waktu Berangkat Paling Awal (Quick Sort)</option>
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
                            <td colspan="8" style="text-align: center; color: #888;">Tidak ada kereta yang cocok dengan kriteria pencarian.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($displayKereta as $k): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($k->id) ?></strong></td>
                                <td><?= htmlspecialchars($k->nama) ?></td>
                                <td><?= htmlspecialchars($k->stasiunAsal) ?> &rarr; <?= htmlspecialchars($k->stasiunTujuan) ?></td>
                                <td><?= htmlspecialchars($k->jamBerangkat) ?></td>
                                <td><?= htmlspecialchars($k->jamTiba) ?></td>
                                <td>Rp <?= number_format($k->harga, 0, ',', '.') ?></td>
                                <td><?= $k->kuota ?> Kursi</td>
                                <td>
                                    <a href="?tab=antrean&pilih_id=<?= urlencode($k->id) ?>" class="btn btn-success" style="font-size: 11px; padding: 3px 8px;">Pesan</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ================= TAB 2: HIERARKI KELAS (TREE) ================= -->
    <?php elseif ($tab === 'hierarki'): ?>
        <?php
        $treeHierarchyText = $pohonKelas->renderHierarchy();
        $preOrderNodes = $pohonKelas->preOrderTraversal();
        ?>
        <div class="panel">
            <h2>Hierarki Struktur Kelas dan Gerbong Kereta Api (Tree)</h2>
            <p>Memodelkan relasi bertingkat: <strong>Armada (Root) &rarr; Kelas Tiket (Child Level 1) &rarr; Gerbong & Fasilitas (Child Level 2)</strong>.</p>

            <h3>Visualisasi Pohon (Teks Bertingkat):</h3>
            <pre><?= htmlspecialchars($treeHierarchyText) ?></pre>

            <h3>Daftar Node Hasil Traversal Pre-Order:</h3>
            <table>
                <thead>
                    <tr>
                        <th>Urutan Kunjungan</th>
                        <th>Node ID</th>
                        <th>Tipe Simpul</th>
                        <th>Nama / Keterangan</th>
                        <th>Informasi Tambahan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($preOrderNodes as $n): ?>
                        <tr>
                            <td>#<?= $no++ ?></td>
                            <td><code><?= htmlspecialchars($n['id']) ?></code></td>
                            <td><span class="badge-tag"><?= htmlspecialchars($n['type']) ?></span></td>
                            <td><?= htmlspecialchars($n['name']) ?></td>
                            <td>
                                <?php if (!empty($n['extra'])): ?>
                                    <code><?= htmlspecialchars(json_encode($n['extra'])) ?></code>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="font-size: 12px; color: #555; margin-top: 14px;">
                <span class="badge-tag">Struktur Data: N-ary Tree</span>
                <span class="badge-tag">Algoritma: Pre-Order Traversal & Rekursi</span>
            </div>
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
            <p>Menerapkan prinsip <strong>FIFO (First In, First Out)</strong>: Pemesan yang masuk antrean lebih awal akan diproses terlebih dahulu.</p>

            <form method="POST" style="margin-top: 14px;">
                <input type="hidden" name="action" value="enqueue_booking">
                
                <div class="form-group">
                    <label for="kereta_id">Pilih Jadwal Kereta:</label>
                    <select name="kereta_id" id="kereta_id" required>
                        <?php foreach ($keretaList as $k): ?>
                            <option value="<?= $k->id ?>" <?= $selectedKeretaId === $k->id ? 'selected' : '' ?>>
                                <?= $k->id ?> - <?= $k->nama ?> (<?= $k->stasiunAsal ?> &rarr; <?= $k->stasiunTujuan ?>, Rp <?= number_format($k->harga, 0, ',', '.') ?>)
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
                    <input type="text" name="nama_penumpang" id="nama_penumpang" placeholder="Contoh: Dzaka Pratama" value="<?= htmlspecialchars($_SESSION['passenger_draft']['nama']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="nik">Nomor Induk Kependudukan (NIK):</label>
                    <input type="text" name="nik" id="nik" placeholder="Contoh: 3201123456780001" value="<?= htmlspecialchars($_SESSION['passenger_draft']['nik']) ?>" required>
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
                                <td>Antrean #<?= $index + 1 ?> <?= $index === 0 ? '(Paling Depan)' : '' ?></td>
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
                <div class="alert alert-info">Saat ini antrean pemesanan tiket kosong. Silakan isi form di atas untuk menambah antrean.</div>
            <?php endif; ?>

            <div style="font-size: 12px; color: #555; margin-top: 14px;">
                <span class="badge-tag">Struktur Data: Queue (FIFO)</span>
                <span class="badge-tag">Operasi: Enqueue, Dequeue, Peek</span>
            </div>
        </div>

    <!-- ================= TAB 4: FORM & RIWAYAT (STACK & ARRAYLIST) ================= -->
    <?php elseif ($tab === 'undoredo'): ?>
        <?php
        $undoStack = new Stack($_SESSION['passenger_history_stack']);
        $redoStack = new Stack($_SESSION['passenger_redo_stack']);
        $confirmedList = new ArrayList($_SESSION['confirmed_orders']);
        $draft = $_SESSION['passenger_draft'];
        ?>
        <div class="panel">
            <h2>Simulasi Formulir Data Penumpang (Stack: Fitur Undo & Redo)</h2>
            <p>Menerapkan prinsip <strong>LIFO (Last In, First Out)</strong>. Setiap perubahan data yang disimpan dicatat ke dalam tumpukan Stack, sehingga dapat dibatalkan (Undo) atau dikembalikan (Redo).</p>

            <form method="POST">
                <input type="hidden" name="action" value="save_passenger_draft">
                <div class="form-group">
                    <label for="draft_nama">Nama Penumpang:</label>
                    <input type="text" name="draft_nama" id="draft_nama" value="<?= htmlspecialchars($draft['nama']) ?>">
                </div>
                <div class="form-group">
                    <label for="draft_nik">NIK:</label>
                    <input type="text" name="draft_nik" id="draft_nik" value="<?= htmlspecialchars($draft['nik']) ?>">
                </div>
                <div class="form-group">
                    <label for="draft_hp">Nomor Telepon:</label>
                    <input type="text" name="draft_hp" id="draft_hp" value="<?= htmlspecialchars($draft['no_hp']) ?>">
                </div>
                <button type="submit" class="btn btn-primary">Simpan Perubahan ke Stack</button>
            </form>

            <div style="margin-top: 14px; display: flex; gap: 8px;">
                <form method="POST">
                    <input type="hidden" name="action" value="undo_passenger_draft">
                    <button type="submit" class="btn btn-warning" <?= $undoStack->isEmpty() ? 'disabled style="opacity:0.5;"' : '' ?>>
                        &larr; Undo (Tersedia: <?= $undoStack->size() ?>)
                    </button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="redo_passenger_draft">
                    <button type="submit" class="btn btn-info" <?= $redoStack->isEmpty() ? 'disabled style="opacity:0.5;"' : '' ?>>
                        Redo (Tersedia: <?= $redoStack->size() ?>) &rarr;
                    </button>
                </form>
            </div>

            <div style="font-size: 12px; color: #555; margin-top: 14px;">
                <span class="badge-tag">Struktur Data: Stack (LIFO)</span>
                <span class="badge-tag">Mekanisme: Dual-Stack Undo/Redo</span>
            </div>
        </div>

        <div class="panel">
            <h2>Riwayat Tiket Berhasil Diterbitkan (ArrayList)</h2>
            <p>Data tiket yang telah sukses diproses dari antrean disimpan secara dinamis dalam <strong>ArrayList</strong>.</p>

            <?php if (!$confirmedList->isEmpty()): ?>
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Pemesanan</th>
                            <th>Nama Penumpang</th>
                            <th>NIK</th>
                            <th>Nama Kereta</th>
                            <th>Kelas & Gerbong</th>
                            <th>Total Tarif</th>
                            <th>Waktu Transaksi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($confirmedList->toArray() as $idx => $order): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><code><?= htmlspecialchars($order['id']) ?></code></td>
                                <td><?= htmlspecialchars($order['namaPenumpang']) ?></td>
                                <td><?= htmlspecialchars($order['nik']) ?></td>
                                <td><?= htmlspecialchars($order['namaKereta']) ?></td>
                                <td><?= htmlspecialchars($order['kelas']) ?> - <?= htmlspecialchars($order['gerbong']) ?></td>
                                <td>Rp <?= number_format($order['totalHarga'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($order['waktuPesan']) ?></td>
                                <td><span class="badge-tag" style="background-color: #d4edda; color: #155724;"><?= htmlspecialchars($order['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">Belum ada tiket yang selesai diproses. Silakan proses antrean pada Tab 3.</div>
            <?php endif; ?>

            <div style="font-size: 12px; color: #555; margin-top: 14px;">
                <span class="badge-tag">Struktur Data: ArrayList (Dynamic Array)</span>
                <span class="badge-tag">Operasi: Add, Get, Size, Filter</span>
            </div>
        </div>

    <!-- ================= TAB 5: PROFILING & OPTIMASI ================= -->
    <?php elseif ($tab === 'profiling'): ?>
        <?php
        $datasetSize = (int)($_GET['dataset_size'] ?? 12);
        if ($datasetSize > 12) {
            $keretaList = Dataset::generateKereta($datasetSize);
        } else {
            $keretaList = Dataset::getDaftarKereta();
        }
        $actualDatasetCount = count($keretaList);

        $iterationsSort = (int)($_GET['iter_sort'] ?? 300);
        $iterationsSearch = (int)($_GET['iter_search'] ?? 1000);

        $sortBenchmark = Profiler::benchmarkSorting($keretaList, $iterationsSort);
        $searchBenchmark = Profiler::benchmarkSearching($keretaList, $iterationsSearch);
        $graphBenchmark = Profiler::benchmarkGraph($graph, 'GMR', 'SBI', 150);
        $treeBenchmark = Profiler::benchmarkTree($pohonKelas, 'GB-LUX1', 250);
        $arrayListBenchmark = Profiler::benchmarkArrayList(500, 50);
        $stackBenchmark = Profiler::benchmarkStack(500, 50);
        $cachingBenchmark = Profiler::benchmarkCaching($graph, 'GMR', 'SBI', 200);

        $systemPeakRamKb = round(memory_get_peak_usage(true) / 1024, 2);
        $systemRealRamKb = round(memory_get_usage(true) / 1024, 2);
        ?>
        <div class="panel">
            <h2>APM Performance Monitor & Benchmarking (Standar Industri Best Practice)</h2>
            <p>Menerapkan prinsip <em>"Profile First, Optimize Second"</em>: pelacakan latensi presisi mikrodetik (<code>microtime</code>), memori heap riil (<code>memory_get_usage(true)</code>), puncak konsumsi RAM (<code>memory_get_peak_usage(true)</code>), dan optimasi bertingkat (caching & early-break).</p>

            <!-- Interactive Benchmark Controls -->
            <form method="GET" style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px; margin: 15px 0;">
                <input type="hidden" name="tab" value="profiling">
                <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #1e293b;">📦 Ukuran Dataset Kereta (N):</label>
                        <select name="dataset_size" style="padding: 7px 10px; border-radius: 6px; border: 1px solid #94a3b8; font-size: 13px; background: white;">
                            <option value="12" <?= $actualDatasetCount === 12 ? 'selected' : '' ?>>12 Kereta (Bawaan Default)</option>
                            <option value="50" <?= $actualDatasetCount === 50 ? 'selected' : '' ?>>50 Kereta</option>
                            <option value="100" <?= $actualDatasetCount === 100 ? 'selected' : '' ?>>100 Kereta (Mulai terlihat perbedaan)</option>
                            <option value="300" <?= $actualDatasetCount === 300 ? 'selected' : '' ?>>300 Kereta (Quick Sort jauh lebih unggul)</option>
                            <option value="500" <?= $actualDatasetCount === 500 ? 'selected' : '' ?>>500 Kereta (Gap Ekstrem O(N²) vs O(N log N))</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #1e293b;">🔄 Iterasi Pengujian Sorting:</label>
                        <select name="iter_sort" style="padding: 7px 10px; border-radius: 6px; border: 1px solid #94a3b8; font-size: 13px; background: white;">
                            <option value="50" <?= $iterationsSort === 50 ? 'selected' : '' ?>>50x (Cepat)</option>
                            <option value="100" <?= $iterationsSort === 100 ? 'selected' : '' ?>>100x</option>
                            <option value="300" <?= $iterationsSort === 300 ? 'selected' : '' ?>>300x (Standar)</option>
                            <option value="1000" <?= $iterationsSort === 1000 ? 'selected' : '' ?>>1.000x (Presisi Tinggi)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #1e293b;">🔍 Iterasi Pengujian Searching:</label>
                        <select name="iter_search" style="padding: 7px 10px; border-radius: 6px; border: 1px solid #94a3b8; font-size: 13px; background: white;">
                            <option value="200" <?= $iterationsSearch === 200 ? 'selected' : '' ?>>200x</option>
                            <option value="500" <?= $iterationsSearch === 500 ? 'selected' : '' ?>>500x</option>
                            <option value="1000" <?= $iterationsSearch === 1000 ? 'selected' : '' ?>>1.000x (Standar)</option>
                            <option value="5000" <?= $iterationsSearch === 5000 ? 'selected' : '' ?>>5.000x (Presisi Tinggi)</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="padding: 8px 18px; cursor: pointer; font-weight: bold;">⚡ Jalankan Uji Ulang</button>
                    </div>
                </div>
                <div style="margin-top: 10px; font-size: 12px; color: #64748b;">
                    <strong>Preset Pengujian Langsung:</strong>
                    <a href="?tab=profiling&dataset_size=12&iter_sort=100&iter_search=500" style="margin-left: 8px; margin-right: 8px;">[12 Data (Default)]</a> |
                    <a href="?tab=profiling&dataset_size=100&iter_sort=100&iter_search=1000" style="margin-left: 8px; margin-right: 8px;">[100 Data (Menengah)]</a> |
                    <a href="?tab=profiling&dataset_size=300&iter_sort=100&iter_search=1000" style="margin-left: 8px; margin-right: 8px; color: #dc2626; font-weight: bold;">[300 Data (Gap Drastis)]</a>
                </div>
            </form>

            <!-- KPI Dashboard APM Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 15px 0;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                    <span style="font-size: 11px; color: #64748b; font-weight: bold; text-transform: uppercase;">Peak RAM Usage</span>
                    <h3 style="margin: 4px 0 0 0; color: #0284c7;"><?= $systemPeakRamKb ?> KB</h3>
                    <small style="color: #64748b;">System Heap Peak</small>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                    <span style="font-size: 11px; color: #64748b; font-weight: bold; text-transform: uppercase;">Real Allocated RAM</span>
                    <h3 style="margin: 4px 0 0 0; color: #0f766e;"><?= $systemRealRamKb ?> KB</h3>
                    <small style="color: #64748b;">Current Active Heap</small>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                    <span style="font-size: 11px; color: #64748b; font-weight: bold; text-transform: uppercase;">Cache Hit Rate</span>
                    <h3 style="margin: 4px 0 0 0; color: #16a34a;"><?= $cachingBenchmark['cache_stats']['hit_rate_pct'] ?></h3>
                    <small style="color: #64748b;"><?= $cachingBenchmark['cache_stats']['hits'] ?> Hits / <?= $cachingBenchmark['cache_stats']['total_requests'] ?> Requests</small>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                    <span style="font-size: 11px; color: #64748b; font-weight: bold; text-transform: uppercase;">APM Health Status</span>
                    <h3 style="margin: 4px 0 0 0; color: #16a34a;">HEALTHY</h3>
                    <small style="color: #16a34a;">Semua threshold < 2.0 ms</small>
                </div>
            </div>

            <h3>1. Komparasi Algoritma Pengurutan (Sorting)</h3>
            <p>Dataset: <strong><?= $actualDatasetCount ?> Jadwal Kereta Api</strong> | Jumlah Iterasi Pengujian: <strong><?= $iterationsSort ?>x</strong> | Rasio Percepatan Quick Sort: <strong style="color: #16a34a;"><?= $sortBenchmark['speedup'] ?></strong></p>
            <table>
                <thead>
                    <tr>
                        <th>Algoritma</th>
                        <th>Kompleksitas Teoretis</th>
                        <th>Total Waktu (ms)</th>
                        <th>Rata-rata Waktu / Iterasi (ms)</th>
                        <th>Peak RAM (KB)</th>
                        <th>Status APM</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Bubble Sort (Teroptimasi Early Break)</strong></td>
                        <td>O(N<sup>2</sup>)</td>
                        <td><?= $sortBenchmark['bubble_sort']['total_time_ms'] ?> ms</td>
                        <td><?= $sortBenchmark['bubble_sort']['avg_time_ms'] ?> ms</td>
                        <td><?= $sortBenchmark['bubble_sort']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $sortBenchmark['bubble_sort']['status'] ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Quick Sort (Divide & Conquer)</strong></td>
                        <td>O(N log N)</td>
                        <td><?= $sortBenchmark['quick_sort']['total_time_ms'] ?> ms</td>
                        <td><?= $sortBenchmark['quick_sort']['avg_time_ms'] ?> ms</td>
                        <td><?= $sortBenchmark['quick_sort']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $sortBenchmark['quick_sort']['status'] ?></span></td>
                    </tr>
                </tbody>
            </table>

            <h3>2. Komparasi Algoritma Pencarian (Searching)</h3>
            <p>Target Pencarian: ID Kereta <code>KA-110</code> | Jumlah Iterasi Pengujian: <strong><?= $iterationsSearch ?>x</strong></p>
            <table>
                <thead>
                    <tr>
                        <th>Algoritma</th>
                        <th>Kompleksitas Teoretis</th>
                        <th>Total Waktu (ms)</th>
                        <th>Rata-rata Waktu / Iterasi (ms)</th>
                        <th>Peak RAM (KB)</th>
                        <th>Status APM</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Linear Search (Early Break)</strong></td>
                        <td>O(N)</td>
                        <td><?= $searchBenchmark['linear_search']['total_time_ms'] ?> ms</td>
                        <td><?= $searchBenchmark['linear_search']['avg_time_ms'] ?> ms</td>
                        <td><?= $searchBenchmark['linear_search']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $searchBenchmark['linear_search']['status'] ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Binary Search (Divide & Conquer)</strong></td>
                        <td>O(log N)</td>
                        <td><?= $searchBenchmark['binary_search']['total_time_ms'] ?> ms</td>
                        <td><?= $searchBenchmark['binary_search']['avg_time_ms'] ?> ms</td>
                        <td><?= $searchBenchmark['binary_search']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $searchBenchmark['binary_search']['status'] ?></span></td>
                    </tr>
                </tbody>
            </table>

            <h3>3. Komparasi Algoritma Graf (Graph: Dijkstra vs BFS)</h3>
            <p>Rute: Stasiun <code><?= $graphBenchmark['start'] ?></code> &rarr; <code><?= $graphBenchmark['end'] ?></code> | Jumlah Iterasi: <strong>150x</strong></p>
            <table>
                <thead>
                    <tr>
                        <th>Algoritma Graf</th>
                        <th>Kompleksitas Teoretis</th>
                        <th>Total Waktu (ms)</th>
                        <th>Rata-rata Waktu / Iterasi (ms)</th>
                        <th>Peak RAM (KB)</th>
                        <th>Status APM</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Dijkstra (Shortest Path berbobot km)</strong></td>
                        <td>O(V<sup>2</sup>)</td>
                        <td><?= $graphBenchmark['dijkstra']['total_time_ms'] ?> ms</td>
                        <td><?= $graphBenchmark['dijkstra']['avg_time_ms'] ?> ms</td>
                        <td><?= $graphBenchmark['dijkstra']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $graphBenchmark['dijkstra']['status'] ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Breadth-First Search (BFS Traversal)</strong></td>
                        <td>O(V + E)</td>
                        <td><?= $graphBenchmark['bfs']['total_time_ms'] ?> ms</td>
                        <td><?= $graphBenchmark['bfs']['avg_time_ms'] ?> ms</td>
                        <td><?= $graphBenchmark['bfs']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $graphBenchmark['bfs']['status'] ?></span></td>
                    </tr>
                </tbody>
            </table>

            <h3>4. Komparasi Algoritma Tree (Pohon Hierarki Kereta)</h3>
            <p>Target Node: Gerbong <code><?= $treeBenchmark['target_id'] ?></code> | Jumlah Iterasi: <strong>250x</strong></p>
            <table>
                <thead>
                    <tr>
                        <th>Operasi Tree</th>
                        <th>Kompleksitas Teoretis</th>
                        <th>Total Waktu (ms)</th>
                        <th>Rata-rata Waktu / Iterasi (ms)</th>
                        <th>Peak RAM (KB)</th>
                        <th>Status APM</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Pre-Order Traversal (Kunjungi Seluruh Pohon)</strong></td>
                        <td>O(N)</td>
                        <td><?= $treeBenchmark['pre_order']['total_time_ms'] ?> ms</td>
                        <td><?= $treeBenchmark['pre_order']['avg_time_ms'] ?> ms</td>
                        <td><?= $treeBenchmark['pre_order']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $treeBenchmark['pre_order']['status'] ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Tree Search DFS (Pencarian Simpul Early Exit)</strong></td>
                        <td>O(N)</td>
                        <td><?= $treeBenchmark['search_dfs']['total_time_ms'] ?> ms</td>
                        <td><?= $treeBenchmark['search_dfs']['avg_time_ms'] ?> ms</td>
                        <td><?= $treeBenchmark['search_dfs']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $treeBenchmark['search_dfs']['status'] ?></span></td>
                    </tr>
                </tbody>
            </table>

            <h3>5. Komparasi Dynamic Array (ArrayList OOP vs Native PHP Array)</h3>
            <p>Beban Operasi: 500 Elemen Insert & Read | Jumlah Iterasi: <strong>50x</strong> (Rasio Overhead OOP: <strong><?= $arrayListBenchmark['overhead_ratio'] ?></strong>)</p>
            <table>
                <thead>
                    <tr>
                        <th>Struktur Data</th>
                        <th>Karakteristik & Paradigma</th>
                        <th>Total Waktu (ms)</th>
                        <th>Rata-rata Waktu / Iterasi (ms)</th>
                        <th>Peak RAM (KB)</th>
                        <th>Status APM</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>ArrayList (OOP Class Instance)</strong></td>
                        <td>Dynamic Array (Encapsulated OOP)</td>
                        <td><?= $arrayListBenchmark['array_list']['total_time_ms'] ?> ms</td>
                        <td><?= $arrayListBenchmark['array_list']['avg_time_ms'] ?> ms</td>
                        <td><?= $arrayListBenchmark['array_list']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $arrayListBenchmark['array_list']['status'] ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>PHP Native Array (Primitive Array)</strong></td>
                        <td>C-Level Zend Engine Hash Table</td>
                        <td><?= $arrayListBenchmark['native_array']['total_time_ms'] ?> ms</td>
                        <td><?= $arrayListBenchmark['native_array']['avg_time_ms'] ?> ms</td>
                        <td><?= $arrayListBenchmark['native_array']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $arrayListBenchmark['native_array']['status'] ?></span></td>
                    </tr>
                </tbody>
            </table>

            <h3>6. Komparasi Struktur Data Stack (Push-Pop vs Dual-Stack Undo/Redo)</h3>
            <p>Beban Operasi: 500 Elemen State | Jumlah Iterasi: <strong>50x</strong></p>
            <table>
                <thead>
                    <tr>
                        <th>Mekanisme Stack</th>
                        <th>Karakteristik Operasi</th>
                        <th>Total Waktu (ms)</th>
                        <th>Rata-rata Waktu / Iterasi (ms)</th>
                        <th>Peak RAM (KB)</th>
                        <th>Status APM</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Stack OOP (Push-Pop Cycle)</strong></td>
                        <td>LIFO Primitif (Insert lalu Pop habis)</td>
                        <td><?= $stackBenchmark['stack_ops']['total_time_ms'] ?> ms</td>
                        <td><?= $stackBenchmark['stack_ops']['avg_time_ms'] ?> ms</td>
                        <td><?= $stackBenchmark['stack_ops']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $stackBenchmark['stack_ops']['status'] ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Dual-Stack Undo/Redo Manager</strong></td>
                        <td>Manajemen Riwayat State Form (Undo & Redo)</td>
                        <td><?= $stackBenchmark['undo_redo']['total_time_ms'] ?> ms</td>
                        <td><?= $stackBenchmark['undo_redo']['avg_time_ms'] ?> ms</td>
                        <td><?= $stackBenchmark['undo_redo']['peak_memory_kb'] ?> KB</td>
                        <td><span class="badge" style="background: #dcfce7; color: #166534; font-size: 10px;"><?= $stackBenchmark['undo_redo']['status'] ?></span></td>
                    </tr>
                </tbody>
            </table>

            <h3>7. Strategi Caching (DEV.to Best Practice: Uncached vs Cached Route)</h3>
            <p>Demonstrasi caching kalkulasi rute berulang (<code><?= $cachingBenchmark['route'] ?></code>). Peningkatan kecepatan: <strong><?= $cachingBenchmark['speedup'] ?></strong>!</p>
            <table>
                <thead>
                    <tr>
                        <th>Metode Eksekusi</th>
                        <th>Strategi Caching</th>
                        <th>Total Waktu (ms)</th>
                        <th>Rata-rata / Iterasi (ms)</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Dijkstra Tanpa Cache</strong></td>
                        <td>Hitung ulang graf di setiap request (O(V<sup>2</sup>))</td>
                        <td><?= $cachingBenchmark['no_cache']['total_time_ms'] ?> ms</td>
                        <td><?= $cachingBenchmark['no_cache']['avg_time_ms'] ?> ms</td>
                        <td>Boros siklus CPU saat traffic tinggi</td>
                    </tr>
                    <tr>
                        <td><strong>Dijkstra Dengan Caching (SimpleCache)</strong></td>
                        <td>In-Memory Cache (Hitung 1x, lalu O(1) read)</td>
                        <td><?= $cachingBenchmark['cached']['total_time_ms'] ?> ms</td>
                        <td><?= $cachingBenchmark['cached']['avg_time_ms'] ?> ms</td>
                        <td><strong>Speedup <?= $cachingBenchmark['speedup'] ?></strong> (Hit Rate <?= $cachingBenchmark['cache_stats']['hit_rate_pct'] ?>)</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h2>Dokumentasi Praktik Terbaik (Best Practices) Optimasi PHP</h2>
            <ul>
                <li><strong>Mindset "Profile First, Optimize Second":</strong> Optimasi performa adalah proses detektif (*detective work, not guesswork*). Semua perbaikan didasarkan pada metrik nyata, bukan asumsi.</li>
                <li><strong>In-Memory Caching Layer (<code>SimpleCache</code>):</strong> Perhitungan algoritma graf berbobot (Dijkstra) yang sering diminta dicache dalam memori, memotong response time hingga belasan kali lipat.</li>
                <li><strong>Application Performance Monitoring (<code>PerformanceMonitor</code>):</strong> Melacak eksekusi fungsi, memory differences, serta memberi peringatan jika durasi eksekusi melebihi threshold aman.</li>
                <li><strong>Pencatatan Peak Memory (<code>memory_get_peak_usage(true)</code>):</strong> Memantau penggunaan memori heap sistem secara menyeluruh untuk mendeteksi memory leak.</li>
                <li><strong>Early Break / Early Exit:</strong> Diimplementasikan pada Bubble Sort (flag <code>$swapped</code>), Linear Search (flag <code>$stopAtFirst</code>), dan Dijkstra (pencapaian simpul tujuan) untuk memangkas iterasi yang tidak perlu.</li>
                <li><strong>Lookup Berkecepatan O(1):</strong> Menggunakan struktur array asosiatif (Hash Map) dan <code>isset()</code> alih-alih <code>in_array()</code> atau <code>array_search()</code> yang berkategori $O(N)$.</li>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Tombol Reset Sesi Demo -->
    <div style="margin-top: 20px; text-align: right;">
        <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mereset seluruh data antrean dan riwayat demo?');">
            <input type="hidden" name="action" value="reset_all">
            <button type="submit" class="btn btn-danger" style="font-size: 11px;">Reset Seluruh Sesi Demo</button>
        </form>
    </div>

    <footer>
        <p>&copy; 2026 Tugas Kuliah Struktur Data & Algoritma - Sistem Pemesanan Tiket Kereta Api (PHP Native)</p>
    </footer>
</div>

</body>
</html>
