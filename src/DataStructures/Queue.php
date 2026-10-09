<?php
/**
 * Class Queue
 * Implementasi struktur data Antrean (Queue: FIFO - First In, First Out).
 * Digunakan untuk sistem antrean pemrosesan tiket kereta api dan waiting list ketika kuota penuh.
 */
class Queue {
    private array $items = [];

    /**
     * Inisialisasi Queue.
     * @param array $initial
     */
    public function __construct(array $initial = []) {
        $this->items = array_values($initial);
    }

    /**
     * Menambahkan elemen baru ke barisan paling belakang antrean (Enqueue).
     * @param mixed $item
     * @return void
     */
    public function enqueue($item): void {
        $this->items[] = $item;
    }

    /**
     * Mengambil dan menghapus elemen paling depan dari antrean (Dequeue).
     * Sesuai konsep FIFO (First-In First-Out).
     * @return mixed|null Mengembalikan null jika antrean kosong
     */
    public function dequeue() {
        if ($this->isEmpty()) {
            return null;
        }
        return array_shift($this->items);
    }

    /**
     * Melihat elemen terdepan yang akan diproses tanpa menghapusnya (Peek / Front).
     * @return mixed|null
     */
    public function peek() {
        if ($this->isEmpty()) {
            return null;
        }
        return $this->items[0];
    }

    /**
     * Memeriksa apakah antrean saat ini kosong.
     * @return bool
     */
    public function isEmpty(): bool {
        return empty($this->items);
    }

    /**
     * Menghitung total jumlah elemen dalam antrean.
     * @return int
     */
    public function size(): int {
        return count($this->items);
    }

    /**
     * Mengosongkan seluruh antrean.
     * @return void
     */
    public function clear(): void {
        $this->items = [];
    }

    /**
     * Mengembalikan seluruh item dalam antrean dalam bentuk array berurutan (dari depan ke belakang).
     * @return array
     */
    public function toArray(): array {
        return $this->items;
    }
}
