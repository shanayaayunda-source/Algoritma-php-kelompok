<?php
/**
 * Class Graph
 * Implementasi struktur data Graf berbobot (Weighted Graph) menggunakan Adjacency List.
 * Digunakan untuk merepresentasikan peta rel dan jaringan rute antar stasiun kereta.
 */
class Graph {
    /**
     * Menyimpan daftar tetangga dan bobot (jarak km).
     * Format: [ 'GMR' => [ ['node' => 'BD', 'weight' => 150], ... ] ]
     */
    private array $adjacencyList = [];

    /**
     * Menambahkan simpul/node baru (stasiun) ke dalam graf.
     * @param string $vertex
     * @return void
     */
    public function addVertex(string $vertex): void {
        $vertex = strtoupper($vertex);
        if (!isset($this->adjacencyList[$vertex])) {
            $this->adjacencyList[$vertex] = [];
        }
    }

    /**
     * Menambahkan sisi berbobot (jalur rel & jarak km) antar dua stasiun.
     * @param string $u Stasiun awal
     * @param string $v Stasiun tujuan
     * @param int|float $weight Jarak (kilometer)
     * @param bool $bidirectional Apakah jalur berlaku dua arah (default: true)
     * @return void
     */
    public function addEdge(string $u, string $v, $weight, bool $bidirectional = true): void {
        $u = strtoupper($u);
        $v = strtoupper($v);

        $this->addVertex($u);
        $this->addVertex($v);

        $this->adjacencyList[$u][] = ['node' => $v, 'weight' => $weight];

        if ($bidirectional) {
            $this->adjacencyList[$v][] = ['node' => $u, 'weight' => $weight];
        }
    }

    /**
     * Mendapatkan seluruh daftar stasiun (vertices) dalam graf.
     * @return array
     */
    public function getVertices(): array {
        return array_keys($this->adjacencyList);
    }

    /**
     * Mendapatkan daftar tetangga yang terhubung langsung dari suatu stasiun.
     * @param string $vertex
     * @return array
     */
    public function getNeighbors(string $vertex): array {
        $vertex = strtoupper($vertex);
        return $this->adjacencyList[$vertex] ?? [];
    }

    /**
     * Traversal BFS (Breadth-First Search) untuk menjelajahi stasiun lapis demi lapis.
     * @param string $startVertex Stasiun titik awal
     * @return array Urutan stasiun yang dikunjungi
     */
    public function bfs(string $startVertex): array {
        $startVertex = strtoupper($startVertex);
        if (!isset($this->adjacencyList[$startVertex])) {
            return [];
        }

        $visited = [];
        $queue = [];
        $traversalOrder = [];

        $visited[$startVertex] = true;
        $queue[] = $startVertex;

        while (!empty($queue)) {
            $current = array_shift($queue);
            $traversalOrder[] = $current;

            foreach ($this->adjacencyList[$current] as $edge) {
                $neighbor = $edge['node'];
                if (!isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $queue[] = $neighbor;
                }
            }
        }

        return $traversalOrder;
    }

    /**
     * Algoritma Dijkstra untuk mencari rute terpendek (Shortest Path) antar dua stasiun kereta.
     * OPTIMASI LOKAL: Menggunakan early exit saat simpul tujuan telah tercapai dari priority set.
     * 
     * @param string $startVertex Stasiun keberangkatan
     * @param string $endVertex Stasiun kedatangan
     * @return array [ 'found' => bool, 'path' => array, 'distance' => int|float ]
     */
    public function dijkstra(string $startVertex, string $endVertex): array {
        $startVertex = strtoupper($startVertex);
        $endVertex = strtoupper($endVertex);

        if (!isset($this->adjacencyList[$startVertex]) || !isset($this->adjacencyList[$endVertex])) {
            return ['found' => false, 'path' => [], 'distance' => INF];
        }

        $distances = [];
        $previous = [];
        $unvisited = [];

        foreach ($this->adjacencyList as $vertex => $edges) {
            $distances[$vertex] = INF;
            $previous[$vertex] = null;
            $unvisited[$vertex] = true;
        }

        $distances[$startVertex] = 0;

        while (!empty($unvisited)) {
            // Temukan simpul di unvisited dengan jarak terkecil
            $minVertex = null;
            $minDistance = INF;

            foreach ($unvisited as $vertex => $val) {
                if ($distances[$vertex] < $minDistance) {
                    $minDistance = $distances[$vertex];
                    $minVertex = $vertex;
                }
            }

            // Jika tidak ada simpul yang dapat dijangkau lagi
            if ($minVertex === null || $minDistance === INF) {
                break;
            }

            // OPTIMASI LOKAL: Early exit jika kita sudah mencapai stasiun tujuan
            if ($minVertex === $endVertex) {
                break;
            }

            unset($unvisited[$minVertex]);

            foreach ($this->adjacencyList[$minVertex] as $edge) {
                $neighbor = $edge['node'];
                $weight = $edge['weight'];

                if (isset($unvisited[$neighbor])) {
                    $newDist = $distances[$minVertex] + $weight;
                    if ($newDist < $distances[$neighbor]) {
                        $distances[$neighbor] = $newDist;
                        $previous[$neighbor] = $minVertex;
                    }
                }
            }
        }

        // Rekonstruksi rute dari stasiun akhir ke awal
        if ($distances[$endVertex] === INF) {
            return ['found' => false, 'path' => [], 'distance' => INF];
        }

        $path = [];
        $curr = $endVertex;
        while ($curr !== null) {
            array_unshift($path, $curr);
            $curr = $previous[$curr];
        }

        return [
            'found' => true,
            'path' => $path,
            'distance' => $distances[$endVertex]
        ];
    }
}
