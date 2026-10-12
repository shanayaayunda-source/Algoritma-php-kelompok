<?php
require_once __DIR__ . '/../Algorithms/Sorting.php';
require_once __DIR__ . '/../Algorithms/Searching.php';

/**
 * Class Profiler
 * Layanan untuk mengukur kinerja algoritma (eksekusi waktu microtime dan penggunaan memori RAM).
 * Membantu menganalisis efisiensi komputasi dan dampak optimasi lokal.
 */
class Profiler {
    /**
     * Mengukur waktu eksekusi dan memori sebuah callback fungsi.
     * 
     * @param string $label Nama pengujian
     * @param callable $fn Fungsi yang akan dieksekusi
     * @param int $iterations Jumlah pengulangan (untuk stabilitas pengukuran pada dataset kecil)
     * @return array [ 'label', 'time_ms', 'memory_kb', 'result' ]
     */
    public static function measure(string $label, callable $fn, int $iterations = 1): array {
        // Catat penggunaan memori sebelum eksekusi
        $memBefore = memory_get_usage();

        // Catat timestamp presisi tinggi (mikrodetik)
        $startTime = microtime(true);

        $result = null;
        for ($i = 0; $i < $iterations; $i++) {
            $result = $fn();
        }

        $endTime = microtime(true);
        $memAfter = memory_get_usage();

        // Hitung selisih waktu dalam milidetik (ms)
        $durationMs = ($endTime - $startTime) * 1000;
        $memoryKb = max(0, ($memAfter - $memBefore) / 1024);

        return [
            'label' => $label,
            'iterations' => $iterations,
            'total_time_ms' => round($durationMs, 4),
            'avg_time_ms' => round($durationMs / $iterations, 6),
            'memory_kb' => round($memoryKb, 2),
            'result' => $result
        ];
    }

    /**
     * Membandingkan performa Bubble Sort vs Quick Sort pada dataset kereta.
     * @param array $dataset
     * @param int $iterations
     * @return array
     */
    public static function benchmarkSorting(array $dataset, int $iterations = 200): array {
        $bubbleProfile = self::measure("Bubble Sort (O(N^2))", function() use ($dataset) {
            return Sorting::bubbleSort($dataset, 'harga', 'asc');
        }, $iterations);

        $quickProfile = self::measure("Quick Sort (O(N log N))", function() use ($dataset) {
            return Sorting::quickSort($dataset, 'harga', 'asc');
        }, $iterations);

        return [
            'bubble_sort' => $bubbleProfile,
            'quick_sort' => $quickProfile,
            'speedup' => ($bubbleProfile['total_time_ms'] > 0 && $quickProfile['total_time_ms'] > 0)
                ? round($bubbleProfile['total_time_ms'] / $quickProfile['total_time_ms'], 2) . 'x'
                : '1.0x'
        ];
    }

    /**
     * Membandingkan performa Linear Search vs Binary Search pada dataset kereta.
     * @param array $dataset
     * @param int $iterations
     * @return array
     */
    public static function benchmarkSearching(array $dataset, int $iterations = 500): array {
        // Siapkan array terurut untuk Binary Search
        $sorted = Sorting::quickSort($dataset, 'id', 'asc');
        $targetId = 'KA-110';

        $linearProfile = self::measure("Linear Search (O(N))", function() use ($dataset, $targetId) {
            return Searching::linearSearch($dataset, 'id', $targetId, false, true);
        }, $iterations);

        $binaryProfile = self::measure("Binary Search (O(log N))", function() use ($sorted, $targetId) {
            return Searching::binarySearch($sorted, 'id', $targetId);
        }, $iterations);

        return [
            'linear_search' => $linearProfile,
            'binary_search' => $binaryProfile,
            'target' => $targetId
        ];
    }
}
