<?php
require_once __DIR__ . '/../Algorithms/Sorting.php';
require_once __DIR__ . '/../Algorithms/Searching.php';
require_once __DIR__ . '/../DataStructures/Graph.php';
require_once __DIR__ . '/../DataStructures/Tree.php';
require_once __DIR__ . '/../DataStructures/ArrayList.php';
require_once __DIR__ . '/../DataStructures/Stack.php';
require_once __DIR__ . '/Dataset.php';

/**
 * Class PerformanceMonitor
 * Mengimplementasikan APM (Application Performance Monitoring) sederhana sesuai standar industri:
 * - Precision timers (microtime)
 * - Heap & Real allocated memory tracking
 * - Threshold-based alert detection (Slow query / high memory warnings)
 */
class PerformanceMonitor {
    private static array $timers = [];
    private static array $logs = [];
    private static float $slowThresholdMs = 2.0;       // Ambang batas latency lambat (2ms)
    private static float $highMemoryThresholdKb = 50.0; // Ambang batas lonjakan memori (50KB)

    /**
     * Memulai timer segmen performa dengan nama tertentu.
     * @param string $name
     */
    public static function start(string $name): void {
        self::$timers[$name] = [
            'time' => microtime(true),
            'memory' => memory_get_usage(),
            'real_memory' => memory_get_usage(true)
        ];
    }

    /**
     * Menghentikan timer dan mencatat metrik ke dalam event log.
     * @param string $name
     * @param array $context Metadata tambahan
     * @return array
     */
    public static function stop(string $name, array $context = []): array {
        if (!isset(self::$timers[$name])) {
            return ['error' => "Timer '{$name}' belum dimulai."];
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        $endRealMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $startData = self::$timers[$name];
        $durationMs = ($endTime - $startData['time']) * 1000;
        $memoryDiffKb = max(0, ($endMemory - $startData['memory']) / 1024);
        $realMemoryDiffKb = max(0, ($endRealMemory - $startData['real_memory']) / 1024);

        $isSlow = ($durationMs > self::$slowThresholdMs);
        $isHighMemory = ($memoryDiffKb > self::$highMemoryThresholdKb);

        $entry = [
            'name' => $name,
            'duration_ms' => round($durationMs, 4),
            'memory_diff_kb' => round($memoryDiffKb, 2),
            'real_memory_diff_kb' => round($realMemoryDiffKb, 2),
            'peak_memory_kb' => round($peakMemory / 1024, 2),
            'status' => ($isSlow || $isHighMemory) ? 'WARNING' : 'HEALTHY',
            'warnings' => [
                'slow_execution' => $isSlow,
                'high_memory' => $isHighMemory
            ],
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        self::$logs[] = $entry;
        unset(self::$timers[$name]);

        return $entry;
    }

    public static function getLogs(): array {
        return self::$logs;
    }

    public static function clearLogs(): void {
        self::$logs = [];
        self::$timers = [];
    }
}

/**
 * Class SimpleCache
 * Implementasi in-memory caching layer sesuai best practice artikel:
 * "Caching strategies: Cache expensive operations and query results"
 */
class SimpleCache {
    private static array $storage = [];
    private static array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0
    ];

    /**
     * Mengambil data dari cache jika ada, atau menjalankan callback dan menyimpannya.
     * @param string $key
     * @param callable $callback
     * @param int|null $ttlSeconds
     * @return mixed
     */
    public static function remember(string $key, callable $callback, ?int $ttlSeconds = null) {
        $now = time();
        if (isset(self::$storage[$key])) {
            $item = self::$storage[$key];
            if ($item['expire'] === null || $item['expire'] > $now) {
                self::$stats['hits']++;
                return $item['value'];
            }
            unset(self::$storage[$key]);
        }

        self::$stats['misses']++;
        $value = $callback();
        self::set($key, $value, $ttlSeconds);
        return $value;
    }

    public static function set(string $key, $value, ?int $ttlSeconds = null): void {
        self::$storage[$key] = [
            'value' => $value,
            'expire' => ($ttlSeconds !== null) ? (time() + $ttlSeconds) : null,
            'created_at' => microtime(true)
        ];
        self::$stats['writes']++;
    }

    public static function get(string $key) {
        if (!isset(self::$storage[$key])) {
            self::$stats['misses']++;
            return null;
        }
        $item = self::$storage[$key];
        if ($item['expire'] !== null && $item['expire'] <= time()) {
            unset(self::$storage[$key]);
            self::$stats['misses']++;
            return null;
        }
        self::$stats['hits']++;
        return $item['value'];
    }

    public static function has(string $key): bool {
        if (!isset(self::$storage[$key])) {
            return false;
        }
        if (self::$storage[$key]['expire'] !== null && self::$storage[$key]['expire'] <= time()) {
            unset(self::$storage[$key]);
            return false;
        }
        return true;
    }

    public static function getStats(): array {
        $total = self::$stats['hits'] + self::$stats['misses'];
        $hitRate = $total > 0 ? round((self::$stats['hits'] / $total) * 100, 1) : 0;
        return array_merge(self::$stats, [
            'total_requests' => $total,
            'hit_rate_pct' => $hitRate . '%'
        ]);
    }

    public static function flush(): void {
        self::$storage = [];
        self::$stats = ['hits' => 0, 'misses' => 0, 'writes' => 0];
    }
}

/**
 * Class Profiler
 * Layanan untuk mengukur kinerja algoritma dan struktur data (eksekusi waktu microtime dan penggunaan memori RAM).
 * Mengikuti best practice industri: Peak Memory, Real Memory, Speedup Factor, dan Threshold Health Status.
 */
class Profiler {
    /**
     * Mengukur waktu eksekusi presisi tinggi dan pemakaian memori dari callback.
     * 
     * @param string $label Nama pengujian
     * @param callable $fn Fungsi yang akan dieksekusi
     * @param int $iterations Jumlah pengulangan (untuk stabilitas pengukuran)
     * @return array [ 'label', 'iterations', 'total_time_ms', 'avg_time_ms', 'memory_kb', 'real_memory_kb', 'peak_memory_kb', 'status', 'result' ]
     */
    public static function measure(string $label, callable $fn, int $iterations = 1): array {
        // Catat penggunaan memori sebelum eksekusi (Heap & Real System Allocation)
        $memBefore = memory_get_usage();
        $realMemBefore = memory_get_usage(true);

        // Catat timestamp presisi tinggi (mikrodetik)
        $startTime = microtime(true);

        $result = null;
        for ($i = 0; $i < $iterations; $i++) {
            $result = $fn();
        }

        $endTime = microtime(true);
        $memAfter = memory_get_usage();
        $realMemAfter = memory_get_usage(true);
        $peakMem = memory_get_peak_usage(true);

        // Hitung selisih waktu dalam milidetik (ms)
        $durationMs = ($endTime - $startTime) * 1000;
        $memoryKb = max(0, ($memAfter - $memBefore) / 1024);
        $realMemoryKb = max(0, ($realMemAfter - $realMemBefore) / 1024);
        $peakMemoryKb = round($peakMem / 1024, 2);

        $avgTimeMs = $durationMs / $iterations;
        $status = ($avgTimeMs > 2.0) ? 'WARNING' : 'HEALTHY';

        return [
            'label' => $label,
            'iterations' => $iterations,
            'total_time_ms' => round($durationMs, 4),
            'avg_time_ms' => round($avgTimeMs, 6),
            'memory_kb' => round($memoryKb, 2),
            'real_memory_kb' => round($realMemoryKb, 2),
            'peak_memory_kb' => $peakMemoryKb,
            'status' => $status,
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

    /**
     * Membandingkan performa algoritma Graf: Dijkstra Shortest Path vs Breadth-First Search (BFS).
     * @param Graph|null $graph
     * @param string $start
     * @param string $end
     * @param int $iterations
     * @return array
     */
    public static function benchmarkGraph(?Graph $graph = null, string $start = 'GMR', string $end = 'SBI', int $iterations = 300): array {
        if ($graph === null) {
            $graph = new Graph();
            foreach (Dataset::getJaringanRute() as $r) {
                $graph->addEdge($r[0], $r[1], $r[2]);
            }
        }

        $dijkstraProfile = self::measure("Dijkstra Shortest Path (O(V^2))", function() use ($graph, $start, $end) {
            return $graph->dijkstra($start, $end);
        }, $iterations);

        $bfsProfile = self::measure("Breadth-First Search (O(V + E))", function() use ($graph, $start) {
            return $graph->bfs($start);
        }, $iterations);

        return [
            'start' => $start,
            'end' => $end,
            'dijkstra' => $dijkstraProfile,
            'bfs' => $bfsProfile,
            'speedup' => ($dijkstraProfile['total_time_ms'] > 0 && $bfsProfile['total_time_ms'] > 0)
                ? round($dijkstraProfile['total_time_ms'] / $bfsProfile['total_time_ms'], 2) . 'x'
                : '1.0x'
        ];
    }

    /**
     * Mengukur performa struktur data Tree: Pre-Order Traversal vs Recursive Search (findNodeById).
     * @param Tree|null $tree
     * @param string $targetId
     * @param int $iterations
     * @return array
     */
    public static function benchmarkTree(?Tree $tree = null, string $targetId = 'GB-LUX1', int $iterations = 500): array {
        if ($tree === null) {
            $tree = Tree::buildFromArray(Dataset::getHierarkiKelas());
        }

        $preOrderProfile = self::measure("Pre-Order Traversal (O(N))", function() use ($tree) {
            return $tree->preOrderTraversal();
        }, $iterations);

        $searchProfile = self::measure("Tree Search DFS (O(N) Early Exit)", function() use ($tree, $targetId) {
            return $tree->findNodeById($targetId);
        }, $iterations);

        return [
            'target_id' => $targetId,
            'pre_order' => $preOrderProfile,
            'search_dfs' => $searchProfile
        ];
    }

    /**
     * Membandingkan performa Dynamic Array OOP (ArrayList) vs PHP Native Array.
     * Menguji operasi insertion (add) dan index retrieval (get).
     * @param int $itemCount Jumlah elemen yang diproses per iterasi
     * @param int $iterations Jumlah iterasi benchmark
     * @return array
     */
    public static function benchmarkArrayList(int $itemCount = 500, int $iterations = 100): array {
        $arrayListProfile = self::measure("ArrayList (OOP Object)", function() use ($itemCount) {
            $list = new ArrayList();
            for ($i = 0; $i < $itemCount; $i++) {
                $list->add("Kereta-" . $i);
            }
            $dummy = $list->get((int)($itemCount / 2));
            return $list->size();
        }, $iterations);

        $nativeArrayProfile = self::measure("PHP Native Array (Primitive)", function() use ($itemCount) {
            $arr = [];
            for ($i = 0; $i < $itemCount; $i++) {
                $arr[] = "Kereta-" . $i;
            }
            $dummy = $arr[(int)($itemCount / 2)];
            return count($arr);
        }, $iterations);

        return [
            'item_count' => $itemCount,
            'array_list' => $arrayListProfile,
            'native_array' => $nativeArrayProfile,
            'overhead_ratio' => ($nativeArrayProfile['total_time_ms'] > 0)
                ? round($arrayListProfile['total_time_ms'] / $nativeArrayProfile['total_time_ms'], 2) . 'x'
                : '1.0x'
        ];
    }

    /**
     * Mengukur performa struktur data Stack: Operasi Push-Pop LIFO vs Dual-Stack Undo/Redo Workflow.
     * @param int $itemCount
     * @param int $iterations
     * @return array
     */
    public static function benchmarkStack(int $itemCount = 500, int $iterations = 100): array {
        $stackProfile = self::measure("Stack OOP (Push-Pop Cycle)", function() use ($itemCount) {
            $stack = new Stack();
            for ($i = 0; $i < $itemCount; $i++) {
                $stack->push("Snapshot-" . $i);
            }
            while (!$stack->isEmpty()) {
                $stack->pop();
            }
            return true;
        }, $iterations);

        $undoRedoProfile = self::measure("Dual-Stack Undo/Redo Manager", function() use ($itemCount) {
            $mgr = new UndoRedoManager();
            for ($i = 0; $i < $itemCount; $i++) {
                $mgr->recordAction(["step" => $i, "data" => "Penumpang-" . $i]);
            }
            for ($u = 0; $u < 10 && $mgr->canUndo(); $u++) {
                $mgr->undo();
            }
            for ($r = 0; $r < 5 && $mgr->canRedo(); $r++) {
                $mgr->redo();
            }
            return true;
        }, $iterations);

        return [
            'item_count' => $itemCount,
            'stack_ops' => $stackProfile,
            'undo_redo' => $undoRedoProfile
        ];
    }

    /**
     * BEST PRACTICE (DEV.to Article): Demonstrasi Caching Strategy.
     * Membandingkan kalkulasi rute berulang tanpa cache vs dengan In-Memory Caching (SimpleCache).
     * @param Graph|null $graph
     * @param string $start
     * @param string $end
     * @param int $iterations
     * @return array
     */
    public static function benchmarkCaching(?Graph $graph = null, string $start = 'GMR', string $end = 'SBI', int $iterations = 300): array {
        if ($graph === null) {
            $graph = new Graph();
            foreach (Dataset::getJaringanRute() as $r) {
                $graph->addEdge($r[0], $r[1], $r[2]);
            }
        }

        // 1. Tanpa Cache: Setiap request menghitung ulang Dijkstra O(V^2)
        $noCacheProfile = self::measure("Dijkstra Tanpa Cache (Hitung Berulang)", function() use ($graph, $start, $end) {
            return $graph->dijkstra($start, $end);
        }, $iterations);

        // 2. Dengan Cache: Menggunakan SimpleCache::remember
        SimpleCache::flush();
        $cacheKey = "route_{$start}_{$end}";
        $cachedProfile = self::measure("Dijkstra Dengan Caching (SimpleCache)", function() use ($graph, $start, $end, $cacheKey) {
            return SimpleCache::remember($cacheKey, function() use ($graph, $start, $end) {
                return $graph->dijkstra($start, $end);
            }, 3600);
        }, $iterations);

        $cacheStats = SimpleCache::getStats();

        return [
            'route' => "{$start} -> {$end}",
            'no_cache' => $noCacheProfile,
            'cached' => $cachedProfile,
            'speedup' => ($cachedProfile['total_time_ms'] > 0 && $noCacheProfile['total_time_ms'] > 0)
                ? round($noCacheProfile['total_time_ms'] / $cachedProfile['total_time_ms'], 2) . 'x'
                : '1.0x',
            'cache_stats' => $cacheStats
        ];
    }
}
