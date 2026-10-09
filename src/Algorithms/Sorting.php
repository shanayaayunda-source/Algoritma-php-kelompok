<?php
/**
 * Class Sorting
 * Berisi implementasi algoritma pengurutan data jadwal kereta api.
 * Dilengkapi dengan optimasi lokal dan dokumentasi perbandingan algoritma.
 */
class Sorting {
    /**
     * Algoritma Bubble Sort
     * Kompleksitas waktu: O(N^2) kasus terburuk, O(N) kasus terbaik setelah optimasi.
     * 
     * OPTIMASI LOKAL:
     * Menambahkan flag `$swapped`. Jika dalam satu putaran iterasi luar tidak ada
     * elemen yang bertukar posisi, artinya array sudah terurut sempurna, sehingga
     * perulangan langsung dihentikan lebih awal (Early Break) untuk menghemat operasi CPU.
     * 
     * @param array $items Array objek (misal: Kereta) atau array asosiatif
     * @param string $key Properti atau kunci yang dijadikan acuan pengurutan (misal: 'harga', 'jamBerangkat')
     * @param string $direction Arah pengurutan: 'asc' (menaik) atau 'desc' (menurun)
     * @return array
     */
    public static function bubbleSort(array $items, string $key, string $direction = 'asc'): array {
        $arr = array_values($items);
        $n = count($arr);
        $isAsc = strtolower($direction) === 'asc';

        for ($i = 0; $i < $n - 1; $i++) {
            // [OPTIMASI LOKAL] Flag untuk mendeteksi apakah ada pertukaran elemen pada iterasi ini
            $swapped = false;

            // Elemen terakhir sebanyak $i sudah pasti berada di tempat yang benar
            for ($j = 0; $j < $n - $i - 1; $j++) {
                $valA = self::getValue($arr[$j], $key);
                $valB = self::getValue($arr[$j + 1], $key);

                $shouldSwap = $isAsc ? ($valA > $valB) : ($valA < $valB);

                if ($shouldSwap) {
                    $temp = $arr[$j];
                    $arr[$j] = $arr[$j + 1];
                    $arr[$j + 1] = $temp;
                    $swapped = true;
                }
            }

            // [OPTIMASI LOKAL] Jika tidak ada pertukaran sama sekali, hentikan iterasi
            if (!$swapped) {
                break;
            }
        }

        return $arr;
    }

    /**
     * Algoritma Quick Sort (Divide and Conquer)
     * Kompleksitas waktu: Rata-rata O(N log N).
     * Sangat efisien untuk pengurutan data berskala lebih besar.
     * 
     * @param array $items
     * @param string $key
     * @param string $direction
     * @return array
     */
    public static function quickSort(array $items, string $key, string $direction = 'asc'): array {
        $arr = array_values($items);
        if (count($arr) <= 1) {
            return $arr;
        }

        $isAsc = strtolower($direction) === 'asc';
        $pivotIndex = (int)(count($arr) / 2);
        $pivot = $arr[$pivotIndex];
        $pivotVal = self::getValue($pivot, $key);

        $left = [];
        $equal = [];
        $right = [];

        foreach ($arr as $item) {
            $val = self::getValue($item, $key);
            if ($val == $pivotVal) {
                $equal[] = $item;
            } elseif ($isAsc ? ($val < $pivotVal) : ($val > $pivotVal)) {
                $left[] = $item;
            } else {
                $right[] = $item;
            }
        }

        return array_merge(
            self::quickSort($left, $key, $direction),
            $equal,
            self::quickSort($right, $key, $direction)
        );
    }

    /**
     * Helper untuk mengambil nilai properti baik dari objek maupun array asosiatif.
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
