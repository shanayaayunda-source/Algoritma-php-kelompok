<?php
/**
 * Class Stasiun
 * Merepresentasikan entitas stasiun kereta api.
 */
class Stasiun {
    public string $kode;
    public string $nama;
    public string $kota;

    /**
     * @param string $kode Kode unik stasiun (misal: GMR, BD)
     * @param string $nama Nama lengkap stasiun
     * @param string $kota Kota letak stasiun
     */
    public function __construct(string $kode, string $nama, string $kota) {
        $this->kode = strtoupper($kode);
        $this->nama = $nama;
        $this->kota = $kota;
    }

    /**
     * Konversi data stasiun ke bentuk array asosiatif.
     */
    public function toArray(): array {
        return [
            'kode' => $this->kode,
            'nama' => $this->nama,
            'kota' => $this->kota
        ];
    }
}
