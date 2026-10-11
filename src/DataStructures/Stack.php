<?php
/**
 * Class Stack
 * Implementasi struktur data Stack (LIFO: Last In, First Out).
 * Digunakan untuk riwayat aksi, penelusuran balik navigasi, dan fitur Undo/Redo formulir penumpang.
 */
class Stack {
    private array $elements = [];

    /**
     * Inisialisasi Stack.
     * @param array $initial
     */
    public function __construct(array $initial = []) {
        $this->elements = array_values($initial);
    }

    /**
     * Memasukkan elemen ke puncak tumpukan (Stack Top).
     * @param mixed $item
     * @return void
     */
    public function push($item): void {
        $this->elements[] = $item;
    }

    /**
     * Mengeluarkan dan mengembalikan elemen paling atas dari tumpukan (Pop).
     * @return mixed|null Mengembalikan null jika stack kosong
     */
    public function pop() {
        if ($this->isEmpty()) {
            return null;
        }
        return array_pop($this->elements);
    }

    /**
     * Melihat elemen paling atas tanpa mengeluarkannya (Peek).
     * @return mixed|null
     */
    public function peek() {
        if ($this->isEmpty()) {
            return null;
        }
        return end($this->elements);
    }

    /**
     * Memeriksa apakah tumpukan kosong.
     * @return bool
     */
    public function isEmpty(): bool {
        return empty($this->elements);
    }

    /**
     * Menghitung jumlah elemen dalam stack.
     * @return int
     */
    public function size(): int {
        return count($this->elements);
    }

    /**
     * Mengosongkan tumpukan.
     * @return void
     */
    public function clear(): void {
        $this->elements = [];
    }

    /**
     * Mengambil array elemen (dari bawah ke atas).
     * @return array
     */
    public function toArray(): array {
        return $this->elements;
    }
}

/**
 * Class UndoRedoManager
 * Menggunakan dua Stack (Undo Stack dan Redo Stack) untuk memfasilitasi operasi pembatalan input.
 */
class UndoRedoManager {
    private Stack $undoStack;
    private Stack $redoStack;

    public function __construct() {
        $this->undoStack = new Stack();
        $this->redoStack = new Stack();
    }

    /**
     * Merekam snapshot state baru ke Undo Stack dan mengosongkan Redo Stack.
     * @param mixed $state
     */
    public function recordAction($state): void {
        $this->undoStack->push($state);
        $this->redoStack->clear();
    }

    /**
     * Membatalkan aksi terakhir (Undo) dan memindahkannya ke Redo Stack.
     * @param mixed $currentState State saat ini yang perlu disimpan ke redo
     * @return mixed|null State sebelumnya
     */
    public function undo($currentState = null) {
        if ($this->undoStack->isEmpty()) {
            return null;
        }
        if ($currentState !== null) {
            $this->redoStack->push($currentState);
        }
        return $this->undoStack->pop();
    }

    /**
     * Mengembalikan aksi yang dibatalkan (Redo).
     * @param mixed $currentState State saat ini yang perlu disimpan ke undo
     * @return mixed|null State redo berikutnya
     */
    public function redo($currentState = null) {
        if ($this->redoStack->isEmpty()) {
            return null;
        }
        if ($currentState !== null) {
            $this->undoStack->push($currentState);
        }
        return $this->redoStack->pop();
    }

    public function canUndo(): bool {
        return !$this->undoStack->isEmpty();
    }

    public function canRedo(): bool {
        return !$this->redoStack->isEmpty();
    }

    public function getUndoStack(): Stack {
        return $this->undoStack;
    }

    public function getRedoStack(): Stack {
        return $this->redoStack;
    }
}
