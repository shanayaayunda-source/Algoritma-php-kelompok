<?php
require_once __DIR__ . '/../Models/Stasiun.php';
require_once __DIR__ . '/../Models/Kereta.php';

/**
 * Class Dataset
 * Menyediakan dataset awal (hardcoded) untuk stasiun, jadwal kereta, jarak jaringan rute, dan hierarki kelas.
 */
class Dataset {
    /**
     * Mendapatkan daftar stasiun kereta api.
     * @return Stasiun[]
     */
    public static function getDaftarStasiun(): array {
        return [
            new Stasiun('GMR', 'Stasiun Gambir', 'Jakarta'),
            new Stasiun('BD',  'Stasiun Bandung', 'Bandung'),
            new Stasiun('CN',  'Stasiun Cirebon', 'Cirebon'),
            new Stasiun('SMT', 'Stasiun Semarang Tawang', 'Semarang'),
            new Stasiun('YK',  'Stasiun Yogyakarta', 'Yogyakarta'),
            new Stasiun('SLO', 'Stasiun Solo Balapan', 'Surakarta'),
            new Stasiun('SBI', 'Stasiun Surabaya Pasar Turi', 'Surabaya'),
            new Stasiun('ML',  'Stasiun Malang', 'Malang'),
        ];
    }

    /**
     * Mendapatkan daftar jadwal kereta api (dataset 12 kereta api).
     * @return Kereta[]
     */
    public static function getDaftarKereta(): array {
        return [
            new Kereta('KA-101', 'Argo Bromo Anggrek', 'GMR', 'SBI', '08:20', '16:30', 650000, 45),
            new Kereta('KA-102', 'Argo Dwipangga',     'GMR', 'SLO', '09:50', '16:55', 520000, 30),
            new Kereta('KA-103', 'Taksaka',            'GMR', 'YK',  '09:20', '16:05', 480000, 25),
            new Kereta('KA-104', 'Argo Parahyangan',   'GMR', 'BD',  '06:30', '09:15', 150000, 60),
            new Kereta('KA-105', 'Ciremai Express',    'BD',  'SMT', '07:15', '14:20', 210000, 40),
            new Kereta('KA-106', 'Lodaya Pagi',        'BD',  'SLO', '06:55', '15:20', 320000, 35),
            new Kereta('KA-107', 'Argo Muria',         'GMR', 'SMT', '07:05', '12:45', 380000, 50),
            new Kereta('KA-108', 'Sancaka',            'YK',  'SBI', '06:45', '10:45', 230000, 40),
            new Kereta('KA-109', 'Malabar',            'BD',  'ML',  '17:20', '06:38', 420000, 20),
            new Kereta('KA-110', 'Jayabaya',           'SBI', 'ML',  '04:15', '06:28', 90000,  80),
            new Kereta('KA-111', 'Sembrani',           'GMR', 'SBI', '19:00', '04:15', 590000, 15),
            new Kereta('KA-112', 'Matarmaja',          'GMR', 'ML',  '10:45', '03:10', 250000, 10),
        ];
    }

    /**
     * Mendapatkan graf bobot rute antar stasiun (jarak dalam kilometer).
     * @return array [ [stasiunA, stasiunB, jarakKm], ... ]
     */
    public static function getJaringanRute(): array {
        return [
            ['GMR', 'BD',  150],
            ['GMR', 'CN',  219],
            ['BD',  'CN',  180],
            ['BD',  'YK',  395],
            ['CN',  'SMT', 211],
            ['CN',  'YK',  260],
            ['SMT', 'SLO', 110],
            ['SMT', 'SBI', 280],
            ['YK',  'SLO', 60],
            ['SLO', 'SBI', 260],
            ['SLO', 'ML',  310],
            ['SBI', 'ML',  93],
        ];
    }

    /**
     * Mendapatkan struktur data hierarki kelas kereta.
     */
    public static function getHierarkiKelas(): array {
        return [
            'id' => 'KA-GLOBAL',
            'name' => 'Armada Kereta Api Indonesia',
            'type' => 'ROOT',
            'children' => [
                [
                    'id' => 'CLS-EKS',
                    'name' => 'Kelas Eksekutif',
                    'type' => 'KELAS',
                    'multiplier' => 1.5,
                    'children' => [
                        ['id' => 'GB-EKS1', 'name' => 'Gerbong Eksekutif 1 (Kapasitas 50 Kursi, Reclining Seat)', 'type' => 'GERBONG', 'seats' => 50],
                        ['id' => 'GB-EKS2', 'name' => 'Gerbong Eksekutif 2 (Kapasitas 50 Kursi, Reclining Seat)', 'type' => 'GERBONG', 'seats' => 50],
                        ['id' => 'GB-LUX1', 'name' => 'Gerbong Luxury Sleeper (Kapasitas 18 Kursi, Private TV)', 'type' => 'GERBONG', 'seats' => 18],
                    ]
                ],
                [
                    'id' => 'CLS-BIS',
                    'name' => 'Kelas Bisnis',
                    'type' => 'KELAS',
                    'multiplier' => 1.2,
                    'children' => [
                        ['id' => 'GB-BIS1', 'name' => 'Gerbong Bisnis 1 (Kapasitas 64 Kursi)', 'type' => 'GERBONG', 'seats' => 64],
                        ['id' => 'GB-BIS2', 'name' => 'Gerbong Bisnis 2 (Kapasitas 64 Kursi)', 'type' => 'GERBONG', 'seats' => 64],
                    ]
                ],
                [
                    'id' => 'CLS-EKO',
                    'name' => 'Kelas Ekonomi',
                    'type' => 'KELAS',
                    'multiplier' => 1.0,
                    'children' => [
                        ['id' => 'GB-EKO1', 'name' => 'Gerbong Ekonomi Premium 1 (Kapasitas 80 Kursi)', 'type' => 'GERBONG', 'seats' => 80],
                        ['id' => 'GB-EKO2', 'name' => 'Gerbong Ekonomi Premium 2 (Kapasitas 80 Kursi)', 'type' => 'GERBONG', 'seats' => 80],
                        ['id' => 'GB-EKO3', 'name' => 'Gerbong Ekonomi Reguler (Kapasitas 106 Kursi)', 'type' => 'GERBONG', 'seats' => 106],
                    ]
                ]
            ]
        ];
    }
}
