<?php

namespace Database\Seeders;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Mpkk;
use App\Models\Negeri;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds full MPKK list for Pulau Pinang from the official
 * "Senarai Nama MPKK dan Kuota Parti" PDF (5 daerah, 40 KADUN).
 *
 * Idempotent: matches existing MPKK by case-insensitive (kadun_id, nama)
 * so re-running updates kuota_parti instead of creating duplicates.
 */
class PenangFullMpkkSeeder extends Seeder
{
    /**
     * KADUN names from the PDF that differ from existing DB spelling.
     * key = PDF spelling, value = DB canonical spelling.
     */
    private array $kadunAliases = [
        'Air Puteh' => 'Air Putih',
        'Machang Bubuk' => 'Machang Bubok',
    ];

    /**
     * MPKK name aliases — existing DB rows that use a different spelling
     * than the PDF. The PDF spelling is treated as canonical: any matching
     * existing row gets renamed instead of creating a duplicate.
     *
     * Format: [kadunName => [pdfName => existingName]]
     */
    private array $mpkkAliases = [
        'Bertam' => [
            'Kampung Datuk' => 'Kampung Datok',
        ],
        'Penaga' => [
            'Permatang 3 Ringgit' => 'Permatang Tiga Ringgit',
        ],
    ];

    public function run(): void
    {
        $penang = Negeri::where('nama', 'Pulau Pinang')->first();
        if (!$penang) {
            $this->command->error('Negeri Pulau Pinang not found! Run NegeriSeeder first.');
            return;
        }

        $bandarIds = Bandar::where('negeri_id', $penang->id)->pluck('id')->all();
        if (empty($bandarIds)) {
            $this->command->error('No Bandar (Parlimen) found for Pulau Pinang. Run PenangParlimenSeeder first.');
            return;
        }

        $data = $this->mpkkData();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($data as $kadunName => $mpkkList) {
            $lookupName = $this->kadunAliases[$kadunName] ?? $kadunName;

            $kadun = Kadun::whereIn('bandar_id', $bandarIds)
                ->whereRaw('LOWER(nama) = ?', [strtolower($lookupName)])
                ->first();

            if (!$kadun) {
                $this->command->warn("KADUN not found: {$kadunName} (looked up as {$lookupName})");
                $skipped += count($mpkkList);
                continue;
            }

            $aliasMap = $this->mpkkAliases[$kadunName] ?? [];

            foreach ($mpkkList as [$mpkkName, $kuotaParti]) {
                $existing = Mpkk::where('kadun_id', $kadun->id)
                    ->whereRaw('LOWER(nama) = ?', [strtolower($mpkkName)])
                    ->first();

                if (!$existing && isset($aliasMap[$mpkkName])) {
                    $existing = Mpkk::where('kadun_id', $kadun->id)
                        ->whereRaw('LOWER(nama) = ?', [strtolower($aliasMap[$mpkkName])])
                        ->first();
                }

                if ($existing) {
                    $existing->update([
                        'nama' => $mpkkName,
                        'kuota_parti' => $kuotaParti,
                    ]);
                    $updated++;
                } else {
                    Mpkk::create([
                        'nama' => $mpkkName,
                        'kadun_id' => $kadun->id,
                        'kuota_parti' => $kuotaParti,
                    ]);
                    $created++;
                }
            }
        }

        $this->command->info("Penang Full MPKK seeded: {$created} created, {$updated} updated, {$skipped} skipped");
    }

    /**
     * @return array<string, array<int, array{0:string,1:string}>>
     */
    private function mpkkData(): array
    {
        return [
            // ========== DAERAH TIMUR LAUT ==========
            'Air Itam' => [
                ['Happy Valley', 'DAP'],
                ['Jalan Masjid Negeri', 'DAP'],
                ['Jalan Shaik Madar', 'PKR'],
                ['Jalan Zoo', 'DAP'],
                ['Kampung Melayu', 'DAP'],
                ['Kampung Pisang', 'DAP'],
                ['Thean Teik', 'DAP'],
            ],
            'Air Puteh' => [
                ['Bukit Bendera', 'DAP'],
                ['Kampung Bharu (DTL)', 'DAP'],
                ['Taman Lumba Kuda', 'DAP'],
                ['Taman Reservior', 'DAP'],
                ['Taman Sempadan', 'DAP'],
            ],
            'Batu Lancang' => [
                ['Jalan Perak', 'DAP'],
                ['Taman Ara', 'DAP'],
                ['Taman Ghee Hiang', 'DAP'],
                ['Taman Hijau', 'DAP'],
                ['Taman Hutching', 'PKR'],
                ['Taman Seri Damai', 'BN'],
            ],
            'Batu Uban' => [
                ['Batu Uban', 'AMANAH'],
                ['Lip Sin', 'PKR'],
                ['Sungai Dua (DTL)', 'PKR'],
                ['Sungai Nibong Kecil, MK 13', 'PKR'],
                ['Taman Brown', 'PKR'],
                ['Taman Pekaka', 'DAP'],
            ],
            'Datok Keramat' => [
                ['Datok Keramat (DTL)', 'DAP'],
                ['Jalan Kampung Dodol', 'PKR'],
                ['Jalan P.Ramlee', 'DAP'],
                ['Jalan York', 'DAP'],
                ['Kampung Makam', 'AMANAH'],
                ['Kebun Lama', 'AMANAH'],
                ['Taman Free School', 'DAP'],
                ['Caunter Hall', 'BN'],
            ],
            'Kebun Bunga' => [
                ['Fettes Park', 'PKR'],
                ['Ladang Hong Seng', 'PKR'],
                ['Ladang Lada', 'DAP'],
                ['Mount Erskine', 'PKR'],
                ['Padang Tembak', 'PKR'],
                ['Jln Batu Gantong', 'BN'],
            ],
            'Komtar' => [
                ['Kampung Jawa (DTL)', 'DAP'],
                ['Kampung Kolam', 'DAP'],
                ['Macalister', 'DAP'],
                ['Taman Trafik', 'DAP'],
                ['Jalan Irving', 'BN'],
            ],
            'Padang Kota' => [
                ['Jalan Transfer / Jalan Argyill', 'DAP'],
                ['Lebuh Chulia', 'DAP'],
                ['Lebuh Pasar', 'PKR'],
                ['Pykett Avenue / Jalan Northam', 'DAP'],
            ],
            'Paya Terubong' => [
                ['Desa Permata', 'DAP'],
                ['Lebuh Relau', 'DAP'],
                ['Medan Angsana', 'DAP'],
                ['Paya Terubong', 'DAP'],
                ['Paya Terubong Tengah', 'DAP'],
                ['Relau', 'DAP'],
                ['Semarak Api', 'DAP'],
                ['Seri Relau', 'DAP'],
                ['Sungai Dondang', 'DAP'],
                ['Tingkat Paya Terubong', 'DAP'],
            ],
            'Pengkalan Kota' => [
                ['C.Y.Choy', 'DAP'],
                ['Clan Jetties', 'DAP'],
                ['Lebuh Macallum', 'DAP'],
                ['Pengkalan Weld', 'DAP'],
                ['Jalan Prangin', 'BN'],
            ],
            'Pulau Tikus' => [
                ['Kampung Herriot', 'PKR'],
                ['Kampung Sireh / Kebun Nyior', 'DAP'],
                ['Midland Berjaya', 'DAP'],
                ['Pantai Molek', 'DAP'],
                ['Persiaran Gurney', 'DAP'],
                ['Kelawai', 'DAP'],
                ['Bangkok Lane', 'BN'],
            ],
            'Seri Delima' => [
                ['Taman Tun Sardon', 'DAP'],
                ['Green Lane Height', 'DAP'],
                ['Island Glades', 'DAP'],
                ['Island Park', 'DAP'],
                ['Jalan Aquarium', 'PKR'],
                ['Lengkok Bawah', 'DAP'],
                ['Sungai Gelugor', 'AMANAH'],
            ],
            'Sungai Pinang' => [
                ['Bandar Baru Jelutong', 'DAP'],
                ['Bukit Dumbar', 'DAP'],
                ['Kampung Jelutong', 'AMANAH'],
                ['Kampung Maqbul', 'PKR'],
                ['Kampung Rawa', 'DAP'],
                ['Medan Tengku / Kota Giam', 'PKR'],
                ['Taman West Jelutong', 'DAP'],
            ],
            'Tanjong Bunga' => [
                ['Batu Feringghi', 'DAP'],
                ['Jalan Gajah', 'DAP'],
                ['Sri Tanjong Pinang', 'DAP'],
                ['Sungai Emas', 'DAP'],
                ['Taman Seri Setia', 'DAP'],
                ['Tanjong Tokong', 'PKR'],
                ['Tanjung Bungah', 'DAP'],
                ['Desiran Tanjong', 'AMANAH'],
                ['Sungai Kelian', 'BN'],
            ],

            // ========== DAERAH BARAT DAYA ==========
            'Batu Maung' => [
                ['Batu Maung', 'AMANAH'],
                ['Bukit Gedong', 'PKR'],
                ['Permatang Damar Laut', 'PKR'],
                ['Sepuluh Kongsi', 'DAP'],
                ['Sungai Ara', 'PKR'],
                ['Kampung Naran', 'PKR'],
                ['Sungai Tiram', 'PKR'],
                ['Teluk Tempoyak', 'AMANAH'],
                ['Desa Jelita', 'PKR'],
            ],
            'Bayan Lepas' => [
                ['Bayan Lepas', 'AMANAH'],
                ['Bukit Gemuruh', 'PKR'],
                ['Gertak Sanggol', 'PKR'],
                ['Kampung Binjai (DBD)', 'PKR'],
                ['Kampung Bukit Bayan Lepas', 'AMANAH'],
                ['Kampung Seronok', 'PKR'],
                ['Mutiara Perdana', 'AMANAH'],
                ['Pekan Bayan Lepas', 'PKR'],
                ['Sungai Batu', 'PKR'],
                ['Taman Perda, Teluk Kumbar', 'AMANAH'],
                ['Taman Sungai Ara', 'DAP'],
                ['Teluk Kumbar', 'PKR'],
                ['Rajawali', 'AMANAH'],
                ['Batu Laut', 'AMANAH'],
                ['Bukit Belah', 'DAP'],
                ['Kampung Masjid', 'BN'],
            ],
            'Pantai Jerejak' => [
                ['Jalan Tengah Selatan', 'DAP'],
                ['Jalan Tengah Utara', 'PKR'],
                ['Kampung Jawa, Bayan Baru', 'PKR'],
                ['Mahsuri', 'PKR'],
                ['Mayang Pasir', 'PKR'],
                ['Sungai Nibong Besar', 'AMANAH'],
                ['Sungai Nibong Kecil', 'PKR'],
                ['Sungai Nibong Pantai', 'AMANAH'],
            ],
            'Pulau Betong' => [
                ['Kampung Genting', 'PKR'],
                ['Kampung Perlis', 'AMANAH'],
                ['Kampung Terang', 'PKR'],
                ['Kongsi', 'AMANAH'],
                ['Kuala Pulau Betong', 'PKR'],
                ['Pondok Upeh', 'PKR'],
                ['Pulau Betong', 'PKR'],
                ['Simpang Empat', 'PKR'],
                ['Sungai Burong', 'AMANAH'],
                ['Titi Serong', 'DAP'],
                ['Titi Teras', 'AMANAH'],
                ['Air Putih (DBD)', 'PKR'],
                ['Paya Kongsi', 'PKR'],
            ],
            'Telok Bahang' => [
                ['Jalan Bharu', 'PKR'],
                ['Bandar Baru Air Putih', 'DAP'],
                ['Kuala Jalan Bharu', 'PKR'],
                ['Kuala Sungai Pinang', 'PKR'],
                ['Pantai Acheh', 'PKR'],
                ['Permatang Pasir (DBD)', 'AMANAH'],
                ['Sungai Pinang', 'PKR'],
                ['Sungai Rusa', 'BN'],
                ['Taman Nelayan', 'PKR'],
                ['Telok Awak', 'PKR'],
                ['Telok Bahang', 'BN'],
                ['Bukit Kecil', 'BN'],
                ['Taman Manggis', 'BN'],
            ],

            // ========== DAERAH SEBERANG PERAI UTARA ==========
            'Bagan Dalam' => [
                ['Bagan Dalam', 'DAP'],
                ['Bagan Luar', 'PKR'],
                ['Kampung Benggali', 'DAP'],
                ['Kampung Pak Abu', 'DAP'],
                ['Taman Siram / Taman Bagan', 'DAP'],
                ['Chain Ferry', 'DAP'],
                ['Jalan Heng Choon Thiam', 'DAP'],
                ['Kampung Perlis', 'AMANAH'],
            ],
            'Bagan Jermal' => [
                ['Jalan Mengkuang', 'DAP'],
                ['Jalan Mohd Saad', 'PKR'],
                ['Kampung Simpah', 'DAP'],
                ['Mak Mandin', 'DAP'],
                ['Ampang Jajar', 'DAP'],
                ['Bagan Jermal', 'DAP'],
            ],
            'Bertam' => [
                ['Kampung Bertam', 'DAP'],
                ['Kampung Datuk', 'PKR'],
                ['Kepala Batas', 'DAP'],
                ['Padang Benggali', 'BN'],
                ['Permatang Serdang', 'DAP'],
                ['Permatang Sintok', 'PKR'],
                ['Jalan Kedah', 'BN'],
                ['Pongsu Seribu', 'AMANAH'],
                ['Permatang Rambai', 'BN'],
            ],
            'Penaga' => [
                ['Bakau Tua', 'AMANAH'],
                ['Guar Kepah', 'DAP'],
                ['Kota Aur', 'AMANAH'],
                ['Lahar Kepar', 'AMANAH'],
                ['Pasir Gebu', 'AMANAH'],
                ['Penaga', 'AMANAH'],
                ['Permatang Janggus', 'AMANAH'],
                ['Bakar Kapur', 'BN'],
                ['Permatang 3 Ringgit', 'BN'],
                ['Pulau Mertajam', 'BN'],
                ['Matang Keriang', 'BN'],
                ['Kuala Muda', 'PKR'],
            ],
            'Permatang Berangan' => [
                ['Lahar Yooi', 'AMANAH'],
                ['Padang Menora', 'BN'],
                ['Pokok Sena', 'PKR'],
                ['Pokok Tampang', 'AMANAH'],
                ['Simpang Tiga Tasek Gelugor', 'PKR'],
                ['Taman Koskam', 'AMANAH'],
                ['Taman Sena Indah', 'BN'],
                ['Tasek Gelugor', 'AMANAH'],
                ['Ara Kuda', 'BN'],
                ['Spg. Tiga Taitong', 'DAP'],
                ['Pokok Machang', 'PKR'],
            ],
            'Pinang Tunggal' => [
                ['Bumbung Lima', 'DAP'],
                ['Kampung Selamat Selatan', 'PKR'],
                ['Kampung Selamat Utara', 'DAP'],
                ['Kampung Tok Bedu', 'PKR'],
                ['Kubang Menerong', 'PKR'],
                ['Permatang Langsat', 'PKR'],
                ['Paya Keladi', 'PKR'],
                ['Pinang Tunggal', 'AMANAH'],
                ['Bertam Indah', 'PKR'],
                ['Kg Baru', 'PKR'],
                ['Permatang Tinggi B', 'PKR'],
                ['Bertam Perdana', 'AMANAH'],
                ['Kg Rangkaian Kg Selamat', 'PKR'],
            ],
            'Sungai Dua' => [
                ['Alor Merah', 'AMANAH'],
                ['Merbau Kudong', 'AMANAH'],
                ['Pajak Song', 'AMANAH'],
                ['Permatang Tok Jaya', 'BN'],
                ['Simpang Empat Permatang Buluh', 'AMANAH'],
                ['Sungai Dua (SPU)', 'AMANAH'],
                ['Taman Desa Murni', 'PKR'],
                ['Taman Merbau Indah', 'DAP'],
                ['Kampung Nyior Sebatang', 'BN'],
                ['Kampung Setol', 'AMANAH'],
            ],
            'Sungai Puyu' => [
                ['Jalan Thamby Kecik', 'DAP'],
                ['Kampung Baru (SPU)', 'DAP'],
                ['Kampung Manggis', 'DAP'],
                ['Sungai Puyu', 'DAP'],
                ['Taman Lucky', 'DAP'],
                ['Kampung Tok Sani', 'DAP'],
                ['Kg Benggali, Sg Puyu', 'DAP'],
            ],
            'Telok Ayer Tawar' => [
                ['Bagan Ajam', 'PKR'],
                ['Pekan Darat', 'PKR'],
                ['Permatang Binjai', 'PKR'],
                ['Permatang Kuching', 'PKR'],
                ['Taman Perkasa', 'DAP'],
                ['Taman Senangan', 'PKR'],
                ['Taman Wira', 'PKR'],
                ['Jalan Masjid', 'BN'],
            ],

            // ========== DAERAH SEBERANG PERAI TENGAH ==========
            'Berapit' => [
                ['Kampung Aston', 'DAP'],
                ['Taman Alma, Kampung Baru', 'PKR'],
                ['Taman Tenang', 'DAP'],
                ['Kampung Besar', 'DAP'],
                ['Mutiara Indah', 'DAP'],
                ['Kampung Rangkaian Kg. Baru', 'DAP'],
                ['Kampung Baru Berapit', 'DAP'],
            ],
            'Bukit Tengah' => [
                ['Bukit Minyak MK. 13', 'PKR'],
                ['Bukit Tengah', 'PKR'],
                ['Jalan Pengkalan', 'PKR'],
                ['Kampung Sekolah Juru', 'AMANAH'],
                ['Kebun Sireh', 'AMANAH'],
                ['Perkampungan Juru', 'DAP'],
                ['Sungai Semilang', 'PKR'],
                ['Taman Mangga Juru', 'DAP'],
                ['Perwira', 'PKR'],
                ['Bukit Kechil Juru', 'PKR'],
                ['Kampung Baru Juru', 'PKR'],
            ],
            'Penanti' => [
                ['Berapit Road', 'PKR'],
                ['Guar Perahu MK 21', 'PKR'],
                ['Kampung Mengkuang Semarak', 'PKR'],
                ['Kampung Tun Sardon', 'PKR'],
                ['Kuala Mengkuang', 'AMANAH'],
                ['Kubang Semang / Sungai Semambu MK.20', 'AMANAH'],
                ['Kubang Ulu', 'PKR'],
                ['Taman Guar Perahu Dan Mengkuang Mak Sulung MK20', 'AMANAH'],
                ['Mengkuang Titi', 'PKR'],
                ['Padang Ibu', 'PKR'],
                ['Tanah Liat MK9', 'PKR'],
                ['Taman Seri Akasia-Spg 3 Kubang Ulu', 'PKR'],
                ['Kampung Terus', 'PKR'],
                ['Kampung Tok Elong', 'PKR'],
                ['Kampung Baru Sungai Lembu', 'PKR'],
            ],
            'Machang Bubuk' => [
                ['Alma / Bukit Minyak', 'PKR'],
                ['Bukit Teh', 'AMANAH'],
                ['Cherok Tok Kun', 'PKR'],
                ['Jalan Gajah Mati / Machang Bubok', 'AMANAH'],
                ['Kampung Tasek Junjong', 'AMANAH'],
                ['Kuala Tasek / Kampung Manggis', 'PKR'],
                ['Taman Alma Jaya', 'AMANAH'],
                ['Taman Impian', 'DAP'],
                ['Taman Impian Jaya', 'PKR'],
                ['Taman Machang Bubok', 'DAP'],
                ['Taman Sejahtera', 'PKR'],
                ['Taman Selamat', 'DAP'],
                ['Taman Seri Janggus', 'AMANAH'],
                ['Taman Sri Kijang', 'DAP'],
                ['Taman Sukun', 'PKR'],
                ['Kampung Baru Machang Bubok', 'PKR'],
                ['Kampung Baru Permatang Tinggi', 'DAP'],
            ],
            'Padang Lalang' => [
                ['Kampung Baru Alma', 'AMANAH'],
                ['Sungai Rambai / Padang Lalang', 'DAP'],
                ['Taman Kota Permai', 'DAP'],
                ['Taman Sri Rambai Fasa 1', 'DAP'],
                ['Taman Sri Rambai Fasa 2-4', 'DAP'],
                ['Taman Keenways', 'BN'],
            ],
            'Perai' => [
                ['Taman Supreme', 'DAP'],
                ['Perai (Kampung Main Road)', 'DAP'],
                ['Taman Chai Leng', 'DAP'],
                ['Taman Inderawasih', 'DAP'],
                ['Kg Manis', 'DAP'],
            ],
            'Permatang Pasir' => [
                ['Bukit Indra Muda / Kepala Bukit', 'PKR'],
                ['Cross Street / Kampung Paya', 'AMANAH'],
                ['Kampung Pelet', 'PKR'],
                ['Kubang Semang MK5', 'PKR'],
                ['Pengkalan Tambang', 'PKR'],
                ['Permatang Pasir (SPT)', 'AMANAH'],
                ['Pmtg Pauh / Samagagah', 'AMANAH'],
                ['Tanah Liat Mukim 8', 'AMANAH'],
                ['Permatang Rawa MK8', 'AMANAH'],
                ['Kampung Petani', 'AMANAH'],
                ['Permatang Tengah', 'PKR'],
                ['Sungai Semambu MK.5', 'BN'],
            ],
            'Seberang Jaya' => [
                ['Jalan Baru', 'PKR'],
                ['Jalan Hussein Onn', 'PKR'],
                ['Jalan Sembilang', 'PKR'],
                ['Jalan Siakap', 'PKR'],
                ['Jalan Tenggiri', 'PKR'],
                ['Kampung Belah Dua', 'PKR'],
                ['Kampung Pertama', 'PKR'],
                ['Kampung Taman Baru', 'PKR'],
                ['Simpang Empat / Permatang Rawa / Permatang Batu', 'PKR'],
                ['Bandar Perda', 'BN'],
                ['Tok Kandu', 'AMANAH'],
            ],

            // ========== DAERAH SEBERANG PERAI SELATAN ==========
            'Bukit Tambun' => [
                ['Batu Kawan', 'DAP'],
                ['Bukit Tambun', 'PKR'],
                ['Kampung Baru (SPS)', 'PKR'],
                ['Ladang Valdor', 'DAP'],
                ['Pulau Aman', 'PKR'],
                ['Simpang Ampat', 'PKR'],
                ['Sungai Bakap Utara', 'DAP'],
                ['Taman Merak Simpang Ampat', 'PKR'],
                ['Taman Sri Aman', 'DAP'],
                ['Bandar Casia', 'PKR'],
                ['Badak Mati', 'BN'],
                ['Kampung Baru Valdor', 'DAP'],
                ['Kg Bagan Bukit Tambun', 'PKR'],
            ],
            'Jawi' => [
                ['Air Lintas', 'DAP'],
                ['Bukit Panchor', 'DAP'],
                ['Byram', 'DAP'],
                ['Kampung Che Aminah', 'PKR'],
                ['Kampung Kebun Baru', 'PKR'],
                ['Nibong Tebal', 'DAP'],
                ['Sanglang', 'DAP'],
                ['Sungai Jawi', 'DAP'],
                ['Telok Ipil', 'PKR'],
                ['Jawi Jaya', 'AMANAH'],
                ['Kg Changkat Baru', 'PKR'],
                ['Kampung Rangkaian Changkat', 'DAP'],
                ['Kampung Baru Jawi', 'DAP'],
            ],
            'Sungai Acheh' => [
                ['Dato Keramat (SPS)', 'PKR'],
                ['Permatang Keling', 'PKR'],
                ['Seri Ampangan (Transkrian)', 'PKR'],
                ['Simpang Tiga (SPS)', 'PKR'],
                ['Sungai Acheh', 'AMANAH'],
                ['Sungai Bakau', 'AMANAH'],
                ['Sungai Chenaam', 'AMANAH'],
                ['Sungai Setar', 'PKR'],
                ['Transkrian', 'PKR'],
                ['Tanjong Berembang', 'PKR'],
                ['Permatang Tok Mahat', 'BN'],
                ['Kampung Bagan Sungai Udang', 'DAP'],
            ],
            'Sungai Bakap' => [
                ['Jalan Stesen', 'PKR'],
                ['Kampung Besar (SPS)', 'PKR'],
                ['Kampung Changkat Dain', 'PKR'],
                ['Kepala Gajah', 'PKR'],
                ['Lima Kongsi', 'PKR'],
                ['Padang Lalang', 'PKR'],
                ['Sungai Bakap Selatan', 'PKR'],
                ['Sungai Baong', 'PKR'],
                ['Sungai Duri', 'PKR'],
                ['Sungai Kechil', 'PKR'],
                ['Tasek (SPS)', 'PKR'],
                ['Tasek Mutiara Selatan', 'AMANAH'],
                ['Tasek Mutiara Utara', 'AMANAH'],
                ['Tasek Mutiara Tengah', 'DAP'],
                ['Tasek Junjong', 'BN'],
                ['Pearl City', 'PKR'],
                ['Kampung Baru Sungai Kechil', 'PKR'],
                ['Kampung Baru Wellesly', 'PKR'],
            ],
        ];
    }
}
