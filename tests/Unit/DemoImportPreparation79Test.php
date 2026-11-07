<?php

use PHPUnit\Framework\TestCase;

/** Exercises only the standalone PHP CLI with synthetic inputs; no application or DB boot. */
final class DemoImportPreparation79Test extends TestCase
{
    private string $root;
    private array $profiles;
    private array $files = [
        'SEEDER_IGRACIAS_11_MAHASISWA_BLACKBOX.json', 'MATRICS_BLACKBOX_11_MAHASISWA.json',
        'rekap_igracias_2026-11-06.json', 'rekap_igracias_2026-11-06 KRS.json',
        'rekap_igracias_2026-11-06 UPDT TAK & SKPI.json', 'rekap_igracias_2026-11-06 FINANCE UPDATE.json',
    ];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/dadu79-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        $this->profiles = $matrix = $krs = [];
        for ($i = 1; $i <= 11; $i++) {
            $nim = sprintf('1202279%03d', $i);
            $name = 'SYNTHETIC TEST '.$i;
            $history = ['jenis_tes' => 'Online EPrT', 'nilai' => (string) (430 + $i),
                'kedaluwarsa' => '19 Mei 2028', 'status_berlaku' => 'Berlaku', 'kategori_tes_valid' => true];
            if ($i === 2) $history['kedaluwarsa'] = '2028-02-29';
            if ($i === 3) unset($history['kedaluwarsa']);
            if ($i === 4) $history['tanggal_tes'] = '7 November 2024';
            if ($i === 5) $history['kedaluwarsa'] = '2030-01-01';
            $this->profiles[] = ['nim' => $nim, 'nama' => $name, 'semester' => '8', 'ipk' => '3.10',
                'status_keuangan' => 'LUNAS', 'nilai_bahasa' => $history['nilai'], 'jenis_tes_bahasa' => 'Online EPrT',
                'jumlah_tes_bahasa' => $i, 'riwayat_nilai_bahasa' => [$history]];
            $matrix[] = ['nim' => $nim, 'nama' => $name, 'program_studi' => 'Informatika'];
            $krs[] = ['nim' => $nim, 'nama' => $name, 'jumlah_mk_krs' => 1, 'krs_mata_kuliah' => [
                ['tahun_ajaran' => '2627', 'semester' => '1', 'nama_mk' => 'TUGAS AKHIR 2']]];
        }
        $payloads = [['selected_menus' => ['akademik', 'bahasa', 'keuangan'], 'data' => $this->profiles],
            ['test_cases' => $matrix], ['data' => []], ['data' => $krs], ['data' => []], ['data' => []]];
        foreach ($this->files as $i => $file) file_put_contents($this->root.'/'.$file, json_encode($payloads[$i]));
        // Deliberately matching identity must still never replace a demo scenario by real-export scores or dates.
        file_put_contents($this->root.'/language.json', json_encode(['data' => [[
            'nim' => $this->profiles[0]['nim'], 'nilai_bahasa' => '999',
            'riwayat_nilai_bahasa' => [['tanggal_tes' => '1 Januari 2020', 'nilai' => '999']]]]]));
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    private function generate(bool $language): string
    {
        $out = $this->root.($language ? '/seven' : '/six');
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../../docker/scripts/prepare-demo-imports.php')
            .' --source='.escapeshellarg($this->root).' --output='.escapeshellarg($out)
            .($language ? ' --language-source='.escapeshellarg($this->root.'/language.json') : '');
        exec($command.' 2>&1', $lines, $exit);
        self::assertSame(0, $exit, implode("\n", $lines));
        return $out;
    }

    private function read(string $file): array
    {
        self::assertFileExists($file);
        return json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_optional_seventh_source_is_private_hashed_and_never_merged_into_scenarios(): void
    {
        $out = $this->generate(true);
        $manifest = $this->read($out.'/manifest.json');
        self::assertSame(7, $manifest['source_count']);
        self::assertSame(hash_file('sha256', $this->root.'/language.json'), $manifest['source_sha256']['language.json']);
        self::assertSame(file_get_contents($this->root.'/language.json'), file_get_contents($out.'/sumber/language.json'));
        self::assertSame(0700, fileperms($out.'/sumber') & 0777);
        self::assertSame(0600, fileperms($out.'/sumber/language.json') & 0777);
        $payload = $this->read($out.'/impor/75-05-bahasa-demo.json');
        self::assertSame(['bahasa'], $payload['selected_menus']);
        self::assertSame(12, $payload['total']);
        self::assertStringContainsString('SINTETIS', $payload['source']);
        foreach (array_slice($payload['data'], 1) as $i => $row) {
            self::assertSame([], array_diff(array_keys($row), ['no', 'nim', 'nama', 'nilai_bahasa', 'jenis_tes_bahasa', 'tanggal_tes_bahasa', 'jumlah_tes_bahasa', 'riwayat_nilai_bahasa']));
            foreach (['nim', 'nama', 'nilai_bahasa', 'jenis_tes_bahasa', 'jumlah_tes_bahasa'] as $key) self::assertSame($this->profiles[$i][$key], $row[$key]);
            $original = $this->profiles[$i]['riwayat_nilai_bahasa'][0];
            self::assertSame($original, array_intersect_key($row['riwayat_nilai_bahasa'][0], $original));
        }
        $histories = array_column(array_slice($payload['data'], 1), 'riwayat_nilai_bahasa');
        self::assertSame('2026-05-19', $histories[0][0]['tanggal_tes']);
        self::assertTrue($histories[0][0]['tanggal_tes_sintetis']);
        self::assertSame('2026-02-28', $histories[1][0]['tanggal_tes']);
        self::assertSame('2026-10-01', $histories[2][0]['tanggal_tes']);
        self::assertSame('7 November 2024', $histories[3][0]['tanggal_tes']);
        self::assertSame('2026-11-07', $histories[4][0]['tanggal_tes']);
        self::assertStringContainsString('SINTETIS', file_get_contents($out.'/README.md'));
        self::assertStringContainsString('7', file_get_contents($out.'/README.md'));
        $academic = $this->read($out.'/impor/75-03-profil-akademik-demo.json');
        foreach ($academic['data'] as $row) self::assertSame('2627/1', $row['periode_keuangan']);
        foreach (array_slice($academic['data'], 1) as $i => $row) {
            foreach ($this->profiles[$i] as $key => $value) {
                if ($key !== 'riwayat_nilai_bahasa') self::assertSame($value, $row[$key]);
            }
        }
        foreach ($this->files as $file) {
            self::assertSame(hash_file('sha256', $this->root.'/'.$file), $manifest['source_sha256'][$file]);
            self::assertSame(file_get_contents($this->root.'/'.$file), file_get_contents($out.'/sumber/'.$file));
        }
        self::assertSame(12, $manifest['base_krs_items']);
        self::assertSame(12, $manifest['scenario_krs_items']);
        self::assertSame(0, $manifest['synthetic_ta_added']);
    }

    public function test_original_six_source_cli_and_public_synthetic_date_shape_remain_supported(): void
    {
        $out = $this->generate(false);
        self::assertSame(6, $this->read($out.'/manifest.json')['source_count']);
        self::assertCount(12, $this->read($out.'/impor/75-05-bahasa-demo.json')['data']);
        foreach (['67-03-profil-akademik-akun-demo.json', '67-04-profil-ecct-dummy002.json'] as $file) {
            $data = $this->read(__DIR__.'/../../docs/fixtures/sidang-ta/'.$file);
            foreach ($data['data'] as $row) {
                foreach ($row['riwayat_nilai_bahasa'] as $history) {
                    self::assertMatchesRegularExpression('/^2026-05-\\d{2}$/', $history['tanggal_tes']);
                    self::assertTrue($history['tanggal_tes_sintetis']);
                }
            }
        }
    }
}
