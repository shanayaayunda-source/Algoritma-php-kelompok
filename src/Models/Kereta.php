<?php
/**
 * Class Kereta
 * Merepresentasikan entitas jadwal kereta api.
 */
class Kereta {
    public string $id;
    public string $nama;
    public string $stasiunAsal;
    public string $stasiunTujuan;
    public string $jamBerangkat;
    public string $jamTiba;
    public int $harga;
    public int $kuota;

    /**
     * @param string $id ID atau nomor kereta (misal: KA-101)
     * @param string $nama Nama kereta api (misal: Argo Bromo Anggrek)
     * @param string $stasiunAsal Kode stasiun asal
     * @param string $stasiunTujuan Kode stasiun tujuan
     * @param string $jamBerangkat Waktu keberangkatan (HH:mm)
     * @param string $jamTiba Waktu tiba (HH:mm)
     * @param int $harga Harga dasar tiket dalam rupiah
     * @param int $kuota Jumlah sisa tiket yang tersedia
     */
    public function __construct(
        string $id,
        string $nama,
        string $stasiunAsal,
        string $stasiunTujuan,
        string $jamBerangkat,
        string $jamTiba,
        int $harga,
        int $kuota = 50
    ) {
        $this->id = $id;
        $this->nama = $nama;
        $this->stasiunAsal = strtoupper($stasiunAsal);
        $this->stasiunTujuan = strtoupper($stasiunTujuan);
        $this->jamBerangkat = $jamBerangkat;
        $this->jamTiba = $jamTiba;
        $this->harga = $harga;
        $this->kuota = $kuota;
    }

    /**
     * Konversi data kereta ke bentuk array asosiatif.
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'stasiunAsal' => $this->stasiunAsal,
            'stasiunTujuan' => $this->stasiunTujuan,
            'jamBerangkat' => $this->jamBerangkat,
            'jamTiba' => $this->jamTiba,
            'harga' => $this->harga,
            'kuota' => $this->kuota
        ];
    }
}
