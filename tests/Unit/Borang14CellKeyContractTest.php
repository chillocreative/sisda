<?php

namespace Tests\Unit;

use App\Http\Controllers\Borang14Controller;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * KONTRAK MERENTAS SEMPADAN: kunci sel grid kemasukan wujud DUA KALI —
 *
 *   PHP : Borang14Controller::cellKey()                (membina peta `votes`)
 *   JS  : cellKey() dalam
 *         resources/js/Pages/Pilihanraya/components/Borang14Form.jsx  (membacanya)
 *
 * Pelayan membina peta undi dengan satu, pelayar membacanya dengan satu lagi.
 * Jika kedua-duanya berbeza walau SATU pemisah, SETIAP sel dalam grid kemasukan
 * dipapar KOSONG — borang kelihatan seperti belum diisi walaupun undi selamat
 * dalam pangkalan data.
 *
 * Tiada pelari ujian JS dalam repo ini, jadi tiada apa-apa yang menangkap
 * penyimpangan itu. Ujian ini menutup lubang tersebut dari sebelah PHP:
 *   1. Ia memaku baris sumber JSX itu SECARA HARFIAH.
 *   2. Ia MENERBITKAN semula bentuk kunci daripada template literal JS itu dan
 *      membandingkannya dengan keluaran cellKey() PHP sebenar merentas satu
 *      matriks kes — supaya baris yang dipaku itu benar-benar terbukti sepadan,
 *      bukan sekadar tidak berubah.
 */
class Borang14CellKeyContractTest extends TestCase
{
    private const JSX = 'resources/js/Pages/Pilihanraya/components/Borang14Form.jsx';

    /**
     * Baris sumber JS yang dipaku. Jika ujian gagal di sini, JS telah berubah:
     * kemas kini Borang14Controller::cellKey() supaya SEPADAN, kemudian kemas
     * kini pemalar ini.
     */
    private const BARIS_JS = "export const cellKey = (contest, pusat, saluran, slot) => `\${contest}|\${pusat ?? ''}|\${saluran}|\${slot}`;";

    private const NASIHAT = "\n\n"
        ."cellKey() PHP dan cellKey() JS telah MENYIMPANG. Selagi ia menyimpang, "
        ."setiap sel dalam grid kemasukan Borang 14 dipapar KOSONG kerana pelayar "
        ."mencari kunci yang tidak pernah dihantar pelayan. Selaraskan semula "
        .self::JSX." dengan Borang14Controller::cellKey(), kemudian kemas kini "
        ."pemalar BARIS_JS dalam ujian ini.";

    private function jsx(): string
    {
        $path = dirname(__DIR__, 2).'/'.self::JSX;
        $this->assertFileExists($path, 'Fail skrin kemasukan hilang — '.self::JSX);

        return (string) file_get_contents($path);
    }

    private function cellKeyPhp(): ReflectionMethod
    {
        $m = (new ReflectionClass(Borang14Controller::class))->getMethod('cellKey');
        $m->setAccessible(true);

        return $m;
    }

    /**
     * Terbitkan bentuk kunci daripada template literal JS itu sendiri, tanpa
     * mengandaikan apa-apa: setiap segmen `${...}` mesti salah satu daripada
     * empat corak yang dikenali, jika tidak ujian gagal dan bukan meneka.
     */
    private function binaIkutJs(string $literal, string $contest, ?string $pusat, string $saluran, int $slot): string
    {
        $keluaran = '';
        $baki = $literal;

        while ($baki !== '') {
            $pos = strpos($baki, '${');
            if ($pos === false) {
                $keluaran .= $baki;
                break;
            }

            $keluaran .= substr($baki, 0, $pos);
            $tutup = strpos($baki, '}', $pos);
            $this->assertNotFalse($tutup, 'Template literal JS rosak (tiada "}").'.self::NASIHAT);

            $ungkapan = trim(substr($baki, $pos + 2, $tutup - $pos - 2));
            $keluaran .= match ($ungkapan) {
                'contest' => $contest,
                "pusat ?? ''" => $pusat ?? '',
                'saluran' => $saluran,
                'slot' => (string) $slot,
                default => $this->fail("Ungkapan JS tidak dikenali dalam cellKey: \${{$ungkapan}}".self::NASIHAT),
            };

            $baki = substr($baki, $tutup + 1);
        }

        return $keluaran;
    }

    public function test_baris_sumber_js_kekal_seperti_yang_dipaku(): void
    {
        $this->assertStringContainsString(
            self::BARIS_JS,
            $this->jsx(),
            'Baris cellKey dalam '.self::JSX.' tidak lagi sepadan dengan versi yang dipaku.'.self::NASIHAT,
        );
    }

    public function test_php_dan_js_menghasilkan_kunci_yang_serupa_bit(): void
    {
        preg_match('/export const cellKey = \([^)]*\) => `([^`]*)`;/', $this->jsx(), $m);
        $this->assertNotEmpty($m, 'Tidak jumpa template literal cellKey dalam '.self::JSX.self::NASIHAT);
        $literal = $m[1];

        $php = $this->cellKeyPhp();
        $controller = (new ReflectionClass(Borang14Controller::class))->newInstanceWithoutConstructor();

        // Matriks: kedua-dua pertandingan × pusat null/kosong/biasa/mengandungi
        // pemisah/unicode/ruang berikutan × saluran biasa/berlabel/mengandungi
        // pemisah/berruang × slot parti pertama, parti terakhir, ditolak (90),
        // tidak dimasukkan (91).
        $contests = ['dun', 'parlimen'];
        $pusatSet = [null, '', 'SK GEMAS', 'SK A|B', 'SJK(C) ÑOÑO 中文', 'SK GEMAS '];
        $saluranSet = ['1', '10', 'UNDI POS (B)', 'A|B', ' 2 ', ''];
        $slotSet = [1, 6, 90, 91];

        $bilangan = 0;
        foreach ($contests as $contest) {
            foreach ($pusatSet as $pusat) {
                foreach ($saluranSet as $saluran) {
                    foreach ($slotSet as $slot) {
                        $bilangan++;
                        $this->assertSame(
                            $this->binaIkutJs($literal, $contest, $pusat, $saluran, $slot),
                            $php->invoke($controller, $contest, $pusat, $saluran, $slot),
                            sprintf(
                                'Kunci berbeza bagi (contest=%s, pusat=%s, saluran=%s, slot=%d).%s',
                                $contest,
                                $pusat === null ? 'null' : "'{$pusat}'",
                                "'{$saluran}'",
                                $slot,
                                self::NASIHAT,
                            ),
                        );
                    }
                }
            }
        }

        $this->assertSame(288, $bilangan, 'Matriks kes tersilap kira — semak gelung.');
    }

    public function test_contest_ialah_komponen_pertama_dan_membezakan_kunci(): void
    {
        $php = $this->cellKeyPhp();
        $controller = (new ReflectionClass(Borang14Controller::class))->newInstanceWithoutConstructor();

        $dun = $php->invoke($controller, 'dun', 'SK GEMAS', '3', 1);
        $pru = $php->invoke($controller, 'parlimen', 'SK GEMAS', '3', 1);

        $this->assertSame('dun|SK GEMAS|3|1', $dun);
        $this->assertSame('parlimen|SK GEMAS|3|1', $pru);
        $this->assertNotSame(
            $dun,
            $pru,
            'Sel PRU dan PRN pada (pusat, saluran, slot) yang sama MESTI berlainan kunci — '
            .'jika tidak satu jalur menulis ganti satu lagi dalam keadaan frontend.',
        );
        $this->assertStringStartsWith('dun|', $dun, 'contest mesti komponen PERTAMA kunci.');
    }
}
