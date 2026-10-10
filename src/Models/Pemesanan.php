<?php
/**
 * Class Pemesanan
 * Merepresentasikan entitas data pemesanan / tiket kereta.
 */
class Pemesanan {
    public string $id;
    public string $namaPenumpang;
    public string $nik;
    public string $keretaId;
    public string $namaKereta;
    public string $kelas;
    public string $gerbong;
    public int $totalHarga;
    public string $status; // 'MENUNGGU_PEMROSESAN', 'BERHASIL', 'DIBATALKAN'
    public string $waktuPesan;

    /**
     * @param string $id Kode pemesanan unik (misal: TIKET-1001)
     * @param string $namaPenumpang Nama penumpang
     * @param string $nik Nomor NIK / KTP
     * @param string $keretaId ID kereta yang dipesan
     * @param string $namaKereta Nama kereta
     * @param string $kelas Kelas (Eksekutif/Bisnis/Ekonomi)
     * @param string $gerbong Nomor gerbong dan nomor kursi
     * @param int $totalHarga Total tarif tiket
     * @param string $status Status pesanan
     * @param string|null $waktuPesan Waktu saat transaksi dilakukan
     */
    public function __construct(
        string $id,
        string $namaPenumpang,
        string $nik,
        string $keretaId,
        string $namaKereta,
        string $kelas,
        string $gerbong,
        int $totalHarga,
        string $status = 'MENUNGGU_PEMROSESAN',
        ?string $waktuPesan = null
    ) {
        $this->id = $id;
        $this->namaPenumpang = $namaPenumpang;
        $this->nik = $nik;
        $this->keretaId = $keretaId;
        $this->namaKereta = $namaKereta;
        $this->kelas = $kelas;
        $this->gerbong = $gerbong;
        $this->totalHarga = $totalHarga;
        $this->status = $status;
        $this->waktuPesan = $waktuPesan ?? date('Y-m-d H:i:s');
    }

    /**
     * Konversi data pemesanan ke bentuk array asosiatif.
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'namaPenumpang' => $this->namaPenumpang,
            'nik' => $this->nik,
            'keretaId' => $this->keretaId,
            'namaKereta' => $this->namaKereta,
            'kelas' => $this->kelas,
            'gerbong' => $this->gerbong,
            'totalHarga' => $this->totalHarga,
            'status' => $this->status,
            'waktuPesan' => $this->waktuPesan
        ];
    }
}
