<?php
/**
 * Class Searching
 * Berisi implementasi algoritma pencarian data jadwal kereta api dan tiket pemesanan.
 * Dilengkapi dengan Linear Search dan Binary Search beserta dokumentasi optimasinya.
 */
class Searching {
    /**
     * Algoritma Linear Search
     * Kompleksitas waktu: O(N)
     * Menelusuri elemen satu per satu dari awal sampai akhir.
     * 
     * OPTIMASI LOKAL:
     * Menggunakan flag `$stopAtFirst`. Jika yang dicari adalah ID unik (misal: ID Pemesanan / Kode Tiket),
     * iterasi langsung berhenti (Early Return) begitu elemen ditemukan tanpa perlu memeriksa sisa data.
     * 
     * @param array $items Array objek atau asosiatif
     * @param string $key Properti pencarian (misal: 'id', 'nama', 'stasiunAsal')
     * @param mixed $value Nilai yang dicari
     * @param bool $partialMatch Jika true, menggunakan pencarian substring case-insensitive
     * @param bool $stopAtFirst Jika true, hentikan pencarian saat menemukan kecocokan pertama
     * @return array Daftar elemen yang cocok
     */
    public static function linearSearch(
        array $items,
        string $key,
        $value,
        bool $partialMatch = false,
        bool $stopAtFirst = false
    ): array {
        $results = [];
        $targetValue = is_string($value) ? strtolower(trim($value)) : $value;

        foreach ($items as $item) {
            $itemValue = self::getValue($item, $key);
            $compareValue = is_string($itemValue) ? strtolower(trim($itemValue)) : $itemValue;

            $matched = false;
            if ($partialMatch && is_string($compareValue) && is_string($targetValue)) {
                $matched = (strpos($compareValue, $targetValue) !== false);
            } else {
                $matched = ($compareValue == $targetValue);
            }

            if ($matched) {
                $results[] = $item;
                // [OPTIMASI LOKAL] Early break jika hanya mencari 1 elemen unik
                if ($stopAtFirst) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Algoritma Binary Search
     * Kompleksitas waktu: O(log N)
     * Hanya berlaku untuk data yang telah terurut (Sorted Array).
     * Membagi area pencarian menjadi dua bagian secara iteratif.
     * 
     * @param array $sortedItems Array yang sudah terurut berdasarkan $key secara menaik (ASC)
     * @param string $key Properti acuan (misal: 'id' atau 'harga')
     * @param mixed $target Nilai target yang dicari
     * @return mixed|null Mengembalikan elemen jika ditemukan, atau null jika tidak ada
     */
    public static function binarySearch(array $sortedItems, string $key, $target) {
        $low = 0;
        $high = count($sortedItems) - 1;

        $targetVal = is_string($target) ? strtolower(trim($target)) : $target;

        while ($low <= $high) {
            $mid = (int)(($low + $high) / 2);
            $midItem = $sortedItems[$mid];
            $midVal = self::getValue($midItem, $key);
            $currVal = is_string($midVal) ? strtolower(trim($midVal)) : $midVal;

            if ($currVal == $targetVal) {
                return $midItem; // Ditemukan
            }

            if ($currVal < $targetVal) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return null; // Tidak ditemukan
    }

    /**
     * Helper untuk mengambil nilai properti dari objek maupun array.
     */
    private static function getValue($item, string $key) {
        if (is_object($item)) {
            return $item->$key ?? null;
        }
        if (is_array($item)) {
            return $item[$key] ?? null;
        }
        return null;
    }
}
