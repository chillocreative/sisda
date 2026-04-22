<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $tpl) {
            NotificationTemplate::updateOrCreate(
                ['code' => $tpl['code']],
                $tpl,
            );
        }
    }

    private function templates(): array
    {
        $out = [];
        $i = 0;

        foreach ($this->whatsappTemplates() as $t) {
            $out[] = $t + [
                'category' => NotificationTemplate::CATEGORY_WHATSAPP,
                'is_active' => true,
                'is_default' => $i === 0,
                'sort_order' => $i,
            ];
            $i++;
        }
        $i = 0;

        foreach ($this->passwordResetTemplates() as $t) {
            $out[] = $t + [
                'category' => NotificationTemplate::CATEGORY_PASSWORD_RESET,
                'is_active' => true,
                'is_default' => $i === 0,
                'sort_order' => $i,
            ];
            $i++;
        }
        $i = 0;

        foreach ($this->systemTemplates() as $t) {
            $out[] = $t + [
                'category' => NotificationTemplate::CATEGORY_SYSTEM,
                'is_active' => true,
                'is_default' => $i === 0,
                'sort_order' => $i,
            ];
            $i++;
        }

        return $out;
    }

    private function whatsappTemplates(): array
    {
        return [
            [
                'code' => 'wa_general_announcement',
                'name' => 'Pengumuman Am',
                'description' => 'Pengumuman rasmi kepada pengundi.',
                'body' => "*SISDA - Pengumuman*\n\nAssalamualaikum dan salam sejahtera {{nama}},\n\n{{mesej}}\n\nTerima kasih atas perhatian anda.\n\n_Mesej automatik SISDA._",
                'variables' => ['nama', 'mesej'],
            ],
            [
                'code' => 'wa_event_invitation',
                'name' => 'Jemputan Majlis',
                'description' => 'Jemputan ke majlis rasmi.',
                'body' => "*JEMPUTAN RASMI*\n\nYBhg. {{nama}},\n\nAnda dijemput hadir ke majlis {{nama_majlis}} pada:\n\n📅 Tarikh: {{tarikh}}\n🕐 Masa: {{masa}}\n📍 Lokasi: {{lokasi}}\n\nKehadiran anda amat dihargai.\n\n_Urus Setia, SISDA._",
                'variables' => ['nama', 'nama_majlis', 'tarikh', 'masa', 'lokasi'],
            ],
            [
                'code' => 'wa_meeting_reminder',
                'name' => 'Peringatan Mesyuarat',
                'description' => 'Peringatan mesyuarat AJK atau komuniti.',
                'body' => "*PERINGATAN MESYUARAT*\n\nSalam {{nama}},\n\nIni adalah peringatan mesyuarat {{tajuk}}:\n\n📅 {{tarikh}}, {{masa}}\n📍 {{lokasi}}\n\nSila pastikan kehadiran anda.",
                'variables' => ['nama', 'tajuk', 'tarikh', 'masa', 'lokasi'],
            ],
            [
                'code' => 'wa_voting_reminder',
                'name' => 'Peringatan Mengundi',
                'description' => 'Peringatan hari mengundi.',
                'body' => "*PERINGATAN HARI MENGUNDI*\n\nSalam {{nama}},\n\nHari mengundi pada {{tarikh}}. Pusat mengundi anda:\n\n📍 {{lokasi}}\n🆔 Daerah Mengundi: {{daerah_mengundi}}\n\nJom keluar mengundi! Suara anda penting.",
                'variables' => ['nama', 'tarikh', 'lokasi', 'daerah_mengundi'],
            ],
            [
                'code' => 'wa_donation_thankyou',
                'name' => 'Terima Kasih Sumbangan',
                'description' => 'Penghargaan sumbangan.',
                'body' => "*TERIMA KASIH*\n\n{{nama}} yang dihormati,\n\nKami merakamkan setinggi-tinggi penghargaan atas sumbangan anda sebanyak *RM{{jumlah}}* pada {{tarikh}}.\n\nMoga amalan mulia anda diberkati.\n\n_SISDA_",
                'variables' => ['nama', 'jumlah', 'tarikh'],
            ],
            [
                'code' => 'wa_aid_approved',
                'name' => 'Bantuan Diluluskan',
                'description' => 'Notifikasi permohonan bantuan diluluskan.',
                'body' => "*BANTUAN DILULUSKAN*\n\nTahniah {{nama}},\n\nPermohonan bantuan anda ({{jenis_bantuan}}) telah *DILULUSKAN*.\n\nSila hadir ke {{lokasi}} pada {{tarikh}} untuk proses seterusnya.\n\nNo. Rujukan: {{rujukan}}",
                'variables' => ['nama', 'jenis_bantuan', 'lokasi', 'tarikh', 'rujukan'],
            ],
            [
                'code' => 'wa_aid_rejected',
                'name' => 'Bantuan Tidak Diluluskan',
                'description' => 'Notifikasi permohonan tidak diluluskan.',
                'body' => "*MAKLUMAN PERMOHONAN*\n\nSalam {{nama}},\n\nDukacita dimaklumkan, permohonan bantuan anda tidak dapat diluluskan pada ketika ini.\n\nSebab: {{sebab}}\n\nAnda boleh memohon semula pada masa akan datang.",
                'variables' => ['nama', 'sebab'],
            ],
            [
                'code' => 'wa_birthday_wish',
                'name' => 'Ucapan Hari Lahir',
                'description' => 'Ucapan hari lahir kepada pengundi.',
                'body' => "🎂 *SELAMAT HARI LAHIR* 🎂\n\nSalam sejahtera {{nama}},\n\nSemoga dipanjangkan umur, dimurahkan rezeki dan sentiasa dalam kesihatan yang baik.\n\nDaripada kami di SISDA.",
                'variables' => ['nama'],
            ],
            [
                'code' => 'wa_raya_greeting',
                'name' => 'Ucapan Hari Raya',
                'description' => 'Ucapan Aidilfitri.',
                'body' => "🌙 *SELAMAT HARI RAYA AIDILFITRI* 🌙\n\n{{nama}} sekeluarga,\n\nSelamat menyambut Aidilfitri. Maaf zahir dan batin atas segala kekurangan.\n\nSemoga Syawal ini membawa keberkatan.\n\n_SISDA_",
                'variables' => ['nama'],
            ],
            [
                'code' => 'wa_newyear_greeting',
                'name' => 'Ucapan Tahun Baru',
                'description' => 'Ucapan tahun baru.',
                'body' => "🎉 *SELAMAT TAHUN BARU {{tahun}}* 🎉\n\n{{nama}},\n\nSemoga tahun baharu ini membawa kejayaan, kesihatan dan kegembiraan kepada anda dan keluarga.\n\n_Salam hangat, SISDA._",
                'variables' => ['nama', 'tahun'],
            ],
            [
                'code' => 'wa_gotong_royong',
                'name' => 'Jemputan Gotong-Royong',
                'description' => 'Jemputan aktiviti gotong-royong.',
                'body' => "*GOTONG-ROYONG PERDANA*\n\nSalam {{nama}},\n\nAnda dijemput menyertai gotong-royong di {{lokasi}}:\n\n📅 {{tarikh}}\n🕐 {{masa}}\n\nSila bawa peralatan asas. Sarapan disediakan.\n\nJom bersama-sama!",
                'variables' => ['nama', 'lokasi', 'tarikh', 'masa'],
            ],
            [
                'code' => 'wa_mobile_clinic',
                'name' => 'Info Klinik Bergerak',
                'description' => 'Info klinik bergerak / pemeriksaan kesihatan.',
                'body' => "*KLINIK BERGERAK*\n\nSalam {{nama}},\n\nKlinik bergerak akan berada di {{lokasi}} pada {{tarikh}} ({{masa}}).\n\nPerkhidmatan PERCUMA:\n• Pemeriksaan tekanan darah\n• Ujian gula dalam darah\n• Nasihat kesihatan\n\nJangan lepaskan peluang!",
                'variables' => ['nama', 'lokasi', 'tarikh', 'masa'],
            ],
            [
                'code' => 'wa_kenduri_announcement',
                'name' => 'Pengumuman Kenduri',
                'description' => 'Pengumuman kenduri komuniti.',
                'body' => "*KENDURI RAKYAT*\n\nSalam {{nama}},\n\nPenduduk dijemput hadir ke kenduri rakyat:\n\n📅 {{tarikh}}\n🕐 {{masa}}\n📍 {{lokasi}}\n\nMakan percuma untuk semua. Bawa keluarga bersama!",
                'variables' => ['nama', 'tarikh', 'masa', 'lokasi'],
            ],
            [
                'code' => 'wa_flood_aid',
                'name' => 'Pengumuman Bantuan Banjir',
                'description' => 'Info bantuan mangsa banjir.',
                'body' => "*BANTUAN MANGSA BANJIR*\n\nSalam {{nama}},\n\nPusat pemindahan banjir dibuka di:\n📍 {{lokasi}}\n\nHubungi {{telefon}} untuk bantuan segera.\n\nJaga keselamatan anda sekeluarga.",
                'variables' => ['nama', 'lokasi', 'telefon'],
            ],
            [
                'code' => 'wa_program_registration',
                'name' => 'Pendaftaran Program',
                'description' => 'Notifikasi pendaftaran program.',
                'body' => "*PENDAFTARAN PROGRAM*\n\n{{nama}},\n\nPendaftaran program *{{nama_program}}* kini dibuka.\n\n📅 Tarikh Program: {{tarikh}}\n🔗 Daftar: {{pautan}}\n\nTempat terhad. Daftar segera!",
                'variables' => ['nama', 'nama_program', 'tarikh', 'pautan'],
            ],
            [
                'code' => 'wa_cash_aid_reminder',
                'name' => 'Peringatan Bantuan Tunai',
                'description' => 'Peringatan pengambilan bantuan tunai.',
                'body' => "*PERINGATAN BANTUAN TUNAI*\n\nSalam {{nama}},\n\nSila hadir untuk mengambil bantuan tunai *RM{{jumlah}}* di:\n\n📍 {{lokasi}}\n📅 {{tarikh}}\n🕐 {{masa}}\n\nBawa MyKad untuk pengesahan.",
                'variables' => ['nama', 'jumlah', 'lokasi', 'tarikh', 'masa'],
            ],
            [
                'code' => 'wa_application_update',
                'name' => 'Update Status Permohonan',
                'description' => 'Update status permohonan.',
                'body' => "*UPDATE PERMOHONAN*\n\n{{nama}},\n\nStatus permohonan *{{rujukan}}* kini: *{{status}}*.\n\nCatatan: {{catatan}}\n\nHubungi {{telefon}} untuk pertanyaan.",
                'variables' => ['nama', 'rujukan', 'status', 'catatan', 'telefon'],
            ],
            [
                'code' => 'wa_emergency_alert',
                'name' => 'Notifikasi Kecemasan',
                'description' => 'Amaran kecemasan komuniti.',
                'body' => "⚠️ *AMARAN KECEMASAN* ⚠️\n\n{{mesej}}\n\nSila patuhi arahan pihak berkuasa. Hubungi {{telefon}} untuk bantuan segera.\n\n_Diterbitkan oleh SISDA._",
                'variables' => ['mesej', 'telefon'],
            ],
            [
                'code' => 'wa_tax_reminder',
                'name' => 'Peringatan Bayaran Cukai',
                'description' => 'Peringatan pembayaran cukai.',
                'body' => "*PERINGATAN CUKAI*\n\nSalam {{nama}},\n\nTarikh akhir bayaran cukai {{jenis_cukai}} ialah *{{tarikh}}*.\n\nJumlah: RM{{jumlah}}\n\nBayar awal elak denda. Maklumat lanjut: {{pautan}}",
                'variables' => ['nama', 'jenis_cukai', 'tarikh', 'jumlah', 'pautan'],
            ],
            [
                'code' => 'wa_general_thanks',
                'name' => 'Ucapan Terima Kasih Am',
                'description' => 'Terima kasih atas sokongan.',
                'body' => "*TERIMA KASIH*\n\nSalam {{nama}},\n\nTerima kasih atas {{sebab}}. Sokongan anda amat bermakna kepada kami.\n\nSemoga kita terus bekerjasama demi kebaikan bersama.",
                'variables' => ['nama', 'sebab'],
            ],
        ];
    }

    private function passwordResetTemplates(): array
    {
        return [
            [
                'code' => 'pr_standard_ms',
                'name' => 'Set Semula - Standard (BM)',
                'description' => 'Templat standard dalam Bahasa Melayu.',
                'body' => "*SISDA - Set Semula Kata Laluan*\n\nSalam {{nama}},\n\nKata laluan baharu anda ialah:\n`{{password}}`\n\nSila log masuk dan tukar kata laluan anda segera di {{pautan}}.\n\n_Mesej ini dijana secara automatik._",
                'variables' => ['nama', 'password', 'pautan'],
            ],
            [
                'code' => 'pr_formal',
                'name' => 'Set Semula - Rasmi',
                'description' => 'Versi rasmi untuk pegawai.',
                'body' => "YBhg. {{nama}},\n\nKami telah menetapkan semula kata laluan akaun SISDA anda atas permintaan.\n\nKata laluan sementara: {{password}}\n\nSila log masuk dan tukar kata laluan dalam tempoh {{tempoh}} jam.\n\nSekian, terima kasih.\n_Pentadbir SISDA_",
                'variables' => ['nama', 'password', 'tempoh'],
            ],
            [
                'code' => 'pr_short',
                'name' => 'Set Semula - Ringkas',
                'description' => 'Versi pendek dan pantas.',
                'body' => "SISDA: Kata laluan baharu anda: {{password}}\n\nLog masuk dan tukar segera.",
                'variables' => ['password'],
            ],
            [
                'code' => 'pr_english',
                'name' => 'Reset - English',
                'description' => 'English version.',
                'body' => "*SISDA - Password Reset*\n\nHi {{nama}},\n\nYour new password is:\n`{{password}}`\n\nPlease log in and change it immediately at {{pautan}}.\n\n_This is an automated message._",
                'variables' => ['nama', 'password', 'pautan'],
            ],
            [
                'code' => 'pr_security_warning',
                'name' => 'Set Semula - Dengan Amaran',
                'description' => 'Termasuk amaran keselamatan.',
                'body' => "🔒 *SISDA - Kata Laluan Baharu*\n\nHai {{nama}},\n\nKata laluan anda: `{{password}}`\n\n⚠️ *AMARAN:*\n• Jangan kongsi dengan sesiapa\n• Tukar segera selepas log masuk\n• Laporkan aktiviti mencurigakan\n\nLog masuk: {{pautan}}",
                'variables' => ['nama', 'password', 'pautan'],
            ],
            [
                'code' => 'pr_expiry',
                'name' => 'Set Semula - Dengan Tempoh Luput',
                'description' => 'Templat dengan tempoh luput.',
                'body' => "*SISDA - Kata Laluan Sementara*\n\n{{nama}},\n\nKata laluan sementara: {{password}}\n\n⏱ Kata laluan ini sah sehingga {{tarikh_luput}}. Sila tukar sebelum tempoh tamat.\n\nLog masuk: {{pautan}}",
                'variables' => ['nama', 'password', 'tarikh_luput', 'pautan'],
            ],
            [
                'code' => 'pr_with_steps',
                'name' => 'Set Semula - Dengan Langkah',
                'description' => 'Termasuk langkah demi langkah.',
                'body' => "*SISDA - Set Semula Kata Laluan*\n\nSalam {{nama}},\n\nKata laluan baharu: `{{password}}`\n\nLangkah:\n1. Buka {{pautan}}\n2. Log masuk dengan kata laluan di atas\n3. Pergi ke Profil → Tukar Kata Laluan\n4. Tetapkan kata laluan baharu yang kuat\n\nTerima kasih.",
                'variables' => ['nama', 'password', 'pautan'],
            ],
            [
                'code' => 'pr_suspicious_login',
                'name' => 'Amaran Log Masuk Mencurigakan',
                'description' => 'Set semula selepas log masuk mencurigakan.',
                'body' => "⚠️ *AMARAN KESELAMATAN*\n\n{{nama}},\n\nKami mengesan aktiviti log masuk yang tidak biasa pada akaun anda ({{ip}} • {{masa}}).\n\nKata laluan anda telah ditetapkan semula:\n`{{password}}`\n\nSila tukar dengan segera dan aktifkan pengesahan dua langkah.",
                'variables' => ['nama', 'ip', 'masa', 'password'],
            ],
            [
                'code' => 'pr_admin_initiated',
                'name' => 'Diinisiasi oleh Admin',
                'description' => 'Apabila admin reset kata laluan pengguna.',
                'body' => "*SISDA - Kata Laluan Ditetapkan Semula*\n\n{{nama}},\n\nPentadbir ({{admin_nama}}) telah menetapkan semula kata laluan anda.\n\nKata laluan baharu: {{password}}\n\nSila tukar selepas log masuk pertama.",
                'variables' => ['nama', 'admin_nama', 'password'],
            ],
            [
                'code' => 'pr_first_time',
                'name' => 'Kata Laluan Kali Pertama',
                'description' => 'Untuk akaun baharu.',
                'body' => "*Selamat Datang ke SISDA*\n\nSalam {{nama}},\n\nAkaun anda telah dicipta.\n\n👤 Nama Pengguna: {{username}}\n🔑 Kata Laluan: {{password}}\n\nLog masuk di {{pautan}} dan tukar kata laluan segera.",
                'variables' => ['nama', 'username', 'password', 'pautan'],
            ],
            [
                'code' => 'pr_account_locked',
                'name' => 'Akaun Dibuka Semula',
                'description' => 'Akaun dibuka selepas disekat.',
                'body' => "🔓 *AKAUN DIBUKA SEMULA*\n\n{{nama}},\n\nAkaun anda telah dibuka semula dan kata laluan ditetapkan semula.\n\nKata laluan baharu: {{password}}\n\nSila tukar dengan segera.",
                'variables' => ['nama', 'password'],
            ],
            [
                'code' => 'pr_mandatory_change',
                'name' => 'Tukar Wajib',
                'description' => 'Penetapan semula wajib oleh polisi.',
                'body' => "*PENETAPAN SEMULA WAJIB*\n\n{{nama}},\n\nMengikut polisi keselamatan, kata laluan anda perlu ditetapkan semula.\n\nKata laluan baharu: {{password}}\n\nTukar semasa log masuk seterusnya.",
                'variables' => ['nama', 'password'],
            ],
            [
                'code' => 'pr_super_admin',
                'name' => 'Super Admin Reset',
                'description' => 'Untuk akaun super admin.',
                'body' => "🛡️ *SISDA - Super Admin Password Reset*\n\n{{nama}},\n\nKata laluan super admin telah ditetapkan semula.\n\nKata laluan: `{{password}}`\n\n⚠ Simpan dengan selamat. Akses anda penuh dan kritikal.",
                'variables' => ['nama', 'password'],
            ],
            [
                'code' => 'pr_temporary_24h',
                'name' => 'Kata Laluan Sementara 24 Jam',
                'description' => 'Kata laluan sah selama 24 jam.',
                'body' => "⏱ *KATA LALUAN SEMENTARA (24 JAM)*\n\n{{nama}},\n\nKata laluan sementara: {{password}}\n\nSah selama 24 jam dari {{masa}}. Sila log masuk dan tetapkan kata laluan kekal.",
                'variables' => ['nama', 'password', 'masa'],
            ],
            [
                'code' => 'pr_2fa_reset',
                'name' => '2FA + Kata Laluan Reset',
                'description' => 'Reset termasuk 2FA.',
                'body' => "🔐 *SISDA - Reset Lengkap*\n\n{{nama}},\n\nKata laluan & kod 2FA anda ditetapkan semula.\n\nKata laluan: {{password}}\nKod pemulihan: {{kod_recovery}}\n\nLog masuk dan konfigurasi 2FA baharu dengan segera.",
                'variables' => ['nama', 'password', 'kod_recovery'],
            ],
            [
                'code' => 'pr_mobile_app',
                'name' => 'Reset Aplikasi Mudah Alih',
                'description' => 'Untuk pengguna aplikasi mudah alih.',
                'body' => "📱 *SISDA Mobile - Kata Laluan*\n\n{{nama}},\n\nKata laluan untuk aplikasi mudah alih: {{password}}\n\nBuka aplikasi SISDA dan log masuk semula. Tukar kata laluan dalam tetapan akaun.",
                'variables' => ['nama', 'password'],
            ],
            [
                'code' => 'pr_welcome_with_password',
                'name' => 'Selamat Datang + Kata Laluan',
                'description' => 'Akaun baharu dengan kata laluan awal.',
                'body' => "🎉 *SELAMAT DATANG KE SISDA*\n\n{{nama}},\n\nAkaun anda berjaya dicipta sebagai *{{peranan}}*.\n\nKata laluan awal: {{password}}\n\nLog masuk: {{pautan}}\n\nKami nantikan sumbangan anda!",
                'variables' => ['nama', 'peranan', 'password', 'pautan'],
            ],
            [
                'code' => 'pr_compromised',
                'name' => 'Kata Laluan Terdedah',
                'description' => 'Selepas pelanggaran keselamatan.',
                'body' => "🚨 *AMARAN KESELAMATAN KRITIKAL*\n\n{{nama}},\n\nKami mengesan kata laluan anda mungkin terdedah. Demi keselamatan, kata laluan telah ditetapkan semula:\n\nBaharu: {{password}}\n\nTukar segera dan semak aktiviti akaun.",
                'variables' => ['nama', 'password'],
            ],
            [
                'code' => 'pr_annual_policy',
                'name' => 'Set Semula Tahunan',
                'description' => 'Polisi set semula tahunan.',
                'body' => "📅 *SET SEMULA TAHUNAN {{tahun}}*\n\n{{nama}},\n\nSebagai sebahagian daripada polisi keselamatan tahunan, kata laluan ditetapkan semula:\n\n{{password}}\n\nSila tukar dengan segera.",
                'variables' => ['tahun', 'nama', 'password'],
            ],
            [
                'code' => 'pr_holiday_period',
                'name' => 'Set Semula Sebelum Cuti',
                'description' => 'Set semula keselamatan sebelum cuti panjang.',
                'body' => "*SISDA - Persediaan Cuti Panjang*\n\n{{nama}},\n\nKata laluan anda ditetapkan semula sebagai langkah keselamatan sebelum cuti {{nama_cuti}}.\n\nBaharu: {{password}}\n\nSelamat bercuti!",
                'variables' => ['nama', 'nama_cuti', 'password'],
            ],
        ];
    }

    private function systemTemplates(): array
    {
        return [
            [
                'code' => 'sys_user_approved',
                'name' => 'Pendaftaran Diluluskan',
                'description' => 'Notifikasi selepas pendaftaran diluluskan.',
                'body' => "*SISDA - Pendaftaran Diluluskan*\n\nTahniah {{nama}},\n\nPendaftaran anda telah diluluskan oleh {{admin_nama}}.\n\nSila log masuk: {{pautan}}",
                'variables' => ['nama', 'admin_nama', 'pautan'],
            ],
            [
                'code' => 'sys_user_rejected',
                'name' => 'Pendaftaran Ditolak',
                'description' => 'Notifikasi pendaftaran tidak diluluskan.',
                'body' => "*SISDA - Pendaftaran*\n\nSalam {{nama}},\n\nDukacita dimaklumkan, pendaftaran anda tidak diluluskan.\n\nSebab: {{sebab}}\n\nHubungi pentadbir untuk maklumat lanjut.",
                'variables' => ['nama', 'sebab'],
            ],
            [
                'code' => 'sys_account_created',
                'name' => 'Akaun Dicipta',
                'description' => 'Akaun baharu dicipta oleh admin.',
                'body' => "*SISDA - Akaun Baharu*\n\n{{nama}},\n\nAkaun SISDA anda telah dicipta.\n\n👤 No. Telefon: {{telefon}}\n🔑 Kata Laluan: {{password}}\n🎭 Peranan: {{peranan}}\n\nLog masuk: {{pautan}}",
                'variables' => ['nama', 'telefon', 'password', 'peranan', 'pautan'],
            ],
            [
                'code' => 'sys_account_deactivated',
                'name' => 'Akaun Dinyahaktifkan',
                'description' => 'Notifikasi akaun dinyahaktifkan.',
                'body' => "*SISDA - Akaun Dinyahaktifkan*\n\n{{nama}},\n\nAkaun SISDA anda telah dinyahaktifkan pada {{tarikh}}.\n\nSebab: {{sebab}}\n\nUntuk pengaktifan semula, hubungi pentadbir.",
                'variables' => ['nama', 'tarikh', 'sebab'],
            ],
            [
                'code' => 'sys_role_changed',
                'name' => 'Peranan Ditukar',
                'description' => 'Notifikasi perubahan peranan.',
                'body' => "*SISDA - Peranan Dikemaskini*\n\n{{nama}},\n\nPeranan akaun anda ditukar daripada *{{peranan_lama}}* kepada *{{peranan_baharu}}*.\n\nPerubahan oleh: {{admin_nama}}",
                'variables' => ['nama', 'peranan_lama', 'peranan_baharu', 'admin_nama'],
            ],
            [
                'code' => 'sys_new_voter_recorded',
                'name' => 'Data Pengundi Direkod',
                'description' => 'Data pengundi baharu direkod.',
                'body' => "*SISDA - Data Pengundi*\n\n{{nama_admin}},\n\nData pengundi baharu direkod:\n• Nama: {{nama_pengundi}}\n• IC: {{no_ic}}\n• Oleh: {{pendaftar}}\n• Masa: {{masa}}",
                'variables' => ['nama_admin', 'nama_pengundi', 'no_ic', 'pendaftar', 'masa'],
            ],
            [
                'code' => 'sys_donation_logged',
                'name' => 'Sumbangan Dilog',
                'description' => 'Sumbangan direkod dalam sistem.',
                'body' => "*SISDA - Sumbangan Baharu*\n\n{{nama_admin}},\n\nSumbangan baharu direkod:\n• Penyumbang: {{penyumbang}}\n• Jumlah: RM{{jumlah}}\n• Jenis: {{jenis}}\n• Tarikh: {{tarikh}}",
                'variables' => ['nama_admin', 'penyumbang', 'jumlah', 'jenis', 'tarikh'],
            ],
            [
                'code' => 'sys_weak_password_warning',
                'name' => 'Amaran Kata Laluan Lemah',
                'description' => 'Amaran kata laluan tidak kuat.',
                'body' => "⚠️ *AMARAN KESELAMATAN*\n\n{{nama}},\n\nKata laluan anda dikesan lemah. Sila tukar kepada kata laluan yang:\n• Sekurang-kurangnya 8 aksara\n• Mengandungi huruf besar & kecil\n• Mengandungi nombor & simbol\n\nTukar di: {{pautan}}",
                'variables' => ['nama', 'pautan'],
            ],
            [
                'code' => 'sys_unusual_login',
                'name' => 'Log Masuk Tidak Biasa',
                'description' => 'Amaran log masuk dari lokasi tidak biasa.',
                'body' => "🔍 *AKTIVITI LOG MASUK*\n\n{{nama}},\n\nLog masuk baharu dikesan:\n📍 IP: {{ip}}\n🌐 Peranti: {{peranti}}\n🕐 Masa: {{masa}}\n\nJika bukan anda, tukar kata laluan segera.",
                'variables' => ['nama', 'ip', 'peranti', 'masa'],
            ],
            [
                'code' => 'sys_report_exported',
                'name' => 'Laporan Dieksport',
                'description' => 'Notifikasi laporan telah dieksport.',
                'body' => "*SISDA - Laporan Dieksport*\n\n{{nama}},\n\nLaporan *{{jenis_laporan}}* telah dieksport oleh {{pengeksport}} pada {{masa}}.\n\nJumlah rekod: {{jumlah_rekod}}",
                'variables' => ['nama', 'jenis_laporan', 'pengeksport', 'masa', 'jumlah_rekod'],
            ],
            [
                'code' => 'sys_import_success',
                'name' => 'Import Berjaya',
                'description' => 'Notifikasi import data berjaya.',
                'body' => "✅ *IMPORT BERJAYA*\n\n{{nama}},\n\nImport fail *{{nama_fail}}* telah selesai.\n\n• Jumlah rekod: {{jumlah}}\n• Berjaya: {{berjaya}}\n• Gagal: {{gagal}}",
                'variables' => ['nama', 'nama_fail', 'jumlah', 'berjaya', 'gagal'],
            ],
            [
                'code' => 'sys_import_failed',
                'name' => 'Import Gagal',
                'description' => 'Notifikasi import gagal.',
                'body' => "❌ *IMPORT GAGAL*\n\n{{nama}},\n\nImport fail *{{nama_fail}}* gagal.\n\nSebab: {{sebab}}\n\nSila semak format fail dan cuba semula.",
                'variables' => ['nama', 'nama_fail', 'sebab'],
            ],
            [
                'code' => 'sys_review_needed',
                'name' => 'Ulasan Diperlukan',
                'description' => 'Rekod memerlukan semakan.',
                'body' => "*SISDA - Ulasan Diperlukan*\n\n{{nama}},\n\nTerdapat {{jumlah}} rekod memerlukan semakan anda.\n\nJenis: {{jenis}}\n🔗 Semak: {{pautan}}",
                'variables' => ['nama', 'jumlah', 'jenis', 'pautan'],
            ],
            [
                'code' => 'sys_task_assigned',
                'name' => 'Tugas Diberikan',
                'description' => 'Tugas ditugaskan kepada pengguna.',
                'body' => "📋 *TUGAS BAHARU*\n\n{{nama}},\n\nAnda telah ditugaskan:\n• Tajuk: {{tajuk}}\n• Tarikh akhir: {{tarikh_akhir}}\n• Ditugaskan oleh: {{penugas}}\n\n🔗 {{pautan}}",
                'variables' => ['nama', 'tajuk', 'tarikh_akhir', 'penugas', 'pautan'],
            ],
            [
                'code' => 'sys_aid_disbursed',
                'name' => 'Bantuan Dikeluarkan',
                'description' => 'Bantuan telah dikeluarkan.',
                'body' => "💰 *BANTUAN DIKELUARKAN*\n\n{{nama}},\n\nBantuan *{{jenis_bantuan}}* berjumlah RM{{jumlah}} telah dikeluarkan kepada {{penerima}} pada {{tarikh}}.\n\nNo. Rujukan: {{rujukan}}",
                'variables' => ['nama', 'jenis_bantuan', 'jumlah', 'penerima', 'tarikh', 'rujukan'],
            ],
            [
                'code' => 'sys_membership_anniversary',
                'name' => 'Ulang Tahun Keahlian',
                'description' => 'Peringatan ulang tahun keahlian.',
                'body' => "🎊 *ULANG TAHUN KEAHLIAN*\n\nTahniah {{nama}},\n\nAnda telah menjadi ahli SISDA selama {{tahun}} tahun.\n\nTerima kasih atas sumbangan dan sokongan berterusan anda!",
                'variables' => ['nama', 'tahun'],
            ],
            [
                'code' => 'sys_new_member',
                'name' => 'Ahli Baharu Didaftarkan',
                'description' => 'Notifikasi ahli baharu.',
                'body' => "*SISDA - Ahli Baharu*\n\n{{nama_admin}},\n\nAhli baharu didaftarkan:\n• Nama: {{nama_ahli}}\n• No. IC: {{no_ic}}\n• Parti: {{parti}}\n• Didaftar oleh: {{pendaftar}}",
                'variables' => ['nama_admin', 'nama_ahli', 'no_ic', 'parti', 'pendaftar'],
            ],
            [
                'code' => 'sys_meeting_scheduled',
                'name' => 'Mesyuarat Dijadualkan',
                'description' => 'Mesyuarat baharu dijadualkan.',
                'body' => "📅 *MESYUARAT DIJADUALKAN*\n\n{{nama}},\n\nMesyuarat *{{tajuk}}* dijadualkan:\n🗓 {{tarikh}}\n🕐 {{masa}}\n📍 {{lokasi}}\n\nSila sahkan kehadiran.",
                'variables' => ['nama', 'tajuk', 'tarikh', 'masa', 'lokasi'],
            ],
            [
                'code' => 'sys_maintenance',
                'name' => 'Sistem Dalam Penyelenggaraan',
                'description' => 'Notifikasi penyelenggaraan sistem.',
                'body' => "🔧 *PENYELENGGARAAN SISTEM*\n\nSalam {{nama}},\n\nSISDA akan menjalani penyelenggaraan:\n📅 {{tarikh}}\n🕐 {{masa_mula}} - {{masa_tamat}}\n\nSistem mungkin tidak dapat diakses. Harap maklum.",
                'variables' => ['nama', 'tarikh', 'masa_mula', 'masa_tamat'],
            ],
            [
                'code' => 'sys_data_backup',
                'name' => 'Sandaran Data Selesai',
                'description' => 'Notifikasi sandaran data selesai.',
                'body' => "💾 *SANDARAN DATA*\n\n{{nama}},\n\nSandaran data SISDA telah selesai pada {{masa}}.\n\nSaiz: {{saiz}}\nLokasi: {{lokasi}}\n\nSistem sedia digunakan seperti biasa.",
                'variables' => ['nama', 'masa', 'saiz', 'lokasi'],
            ],
        ];
    }
}
