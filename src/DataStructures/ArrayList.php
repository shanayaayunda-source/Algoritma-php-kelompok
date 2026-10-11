<?php
/**
 * Class ArrayList
 * Implementasi struktur data Dynamic Array (List dinamis berurutan) berbasis OOP di PHP.
 * Digunakan untuk mengelola daftar entitas secara fleksibel (katalog kereta, stasiun, riwayat).
 */
class ArrayList {
    private array $items = [];

    /**
     * Inisialisasi ArrayList (opsional dengan array awal).
     * @param array $initialItems
     */
    public function __construct(array $initialItems = []) {
        $this->items = array_values($initialItems);
    }

    /**
     * Menambahkan elemen baru ke akhir array list.
     * @param mixed $element
     * @return void
     */
    public function add($element): void {
        $this->items[] = $element;
    }

    /**
     * Mendapatkan elemen pada indeks tertentu.
     * @param int $index
     * @return mixed|null Mengembalikan null jika indeks di luar jangkauan
     */
    public function get(int $index) {
        if ($index < 0 || $index >= count($this->items)) {
            return null;
        }
        return $this->items[$index];
    }

    /**
     * Mengubah elemen pada indeks tertentu.
     * @param int $index
     * @param mixed $element
     * @return bool
     */
    public function set(int $index, $element): bool {
        if ($index < 0 || $index >= count($this->items)) {
            return false;
        }
        $this->items[$index] = $element;
        return true;
    }

    /**
     * Menghapus elemen pada indeks tertentu dan merapikan indeks (re-index).
     * @param int $index
     * @return mixed|null Elemen yang dihapus atau null jika indeks tidak valid
     */
    public function remove(int $index) {
        if ($index < 0 || $index >= count($this->items)) {
            return null;
        }
        $removed = $this->items[$index];
        array_splice($this->items, $index, 1);
        return $removed;
    }

    /**
     * Mendapatkan ukuran total elemen saat ini.
     * @return int
     */
    public function size(): int {
        return count($this->items);
    }

    /**
     * Memeriksa apakah list dalam keadaan kosong.
     * @return bool
     */
    public function isEmpty(): bool {
        return empty($this->items);
    }

    /**
     * Mengosongkan seluruh isi list.
     * @return void
     */
    public function clear(): void {
        $this->items = [];
    }

    /**
     * Mencari elemen pertama yang memenuhi kondisi callback tertentu.
     * @param callable $predicate Function($item): bool
     * @return mixed|null
     */
    public function find(callable $predicate) {
        foreach ($this->items as $item) {
            if ($predicate($item)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Menyaring elemen berdasarkan predikat dan mengembalikan ArrayList baru.
     * @param callable $predicate Function($item): bool
     * @return ArrayList
     */
    public function filter(callable $predicate): ArrayList {
        $result = new ArrayList();
        foreach ($this->items as $item) {
            if ($predicate($item)) {
                $result->add($item);
            }
        }
        return $result;
    }

    /**
     * Mengembalikan representasi array PHP asli.
     * @return array
     */
    public function toArray(): array {
        return $this->items;
    }
}
