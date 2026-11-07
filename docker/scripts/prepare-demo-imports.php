<?php

// Local file preparation only: never boots Laravel or touches a database.
use App\Support\DemoAccountPlan;

require __DIR__.'/../../app/Support/DemoAccountPlan.php';

$options = getopt('', ['source:', 'output:', 'language-source:']);
try {
    $source = realpath($options['source'] ?? '');
    $output = $options['output'] ?? '';
    if (!$source || !is_dir($source) || $output === '' || realpath($output) === $source) {
        throw new RuntimeException('Pakai --source=folder_data_dadu dan --output=folder_fixture_privat_yang_berbeda.');
    }
    $files = [
        'SEEDER_IGRACIAS_11_MAHASISWA_BLACKBOX.json', 'MATRICS_BLACKBOX_11_MAHASISWA.json',
        'rekap_igracias_2026-11-06.json', 'rekap_igracias_2026-11-06 KRS.json',
        'rekap_igracias_2026-11-06 UPDT TAK & SKPI.json', 'rekap_igracias_2026-11-06 FINANCE UPDATE.json',
    ];
    $data = $hashes = [];
    foreach ($files as $file) {
        $path = $source.'/'.$file;
        if (!is_file($path)) throw new RuntimeException('Sumber belum lengkap: '.$file);
        $data[$file] = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $hashes[$file] = hash_file('sha256', $path);
    }
    $sourcePaths = array_combine($files, array_map(static fn ($file) => $source.'/'.$file, $files));
    $languageSourceRows = $languageSourceMatchingBlackbox = 0;
    if (isset($options['language-source'])) {
        $languagePath = realpath($options['language-source']);
        if (!$languagePath || !is_file($languagePath)) throw new RuntimeException('Sumber bahasa tambahan tidak tersedia.');
        $languageFile = basename($languagePath);
        if (isset($sourcePaths[$languageFile])) throw new RuntimeException('Nama sumber bahasa tambahan bertabrakan dengan enam sumber dasar.');
        $languageData = json_decode(file_get_contents($languagePath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($languageData['data'] ?? null) || !array_is_list($languageData['data'])) throw new RuntimeException('Sumber bahasa harus memiliki daftar data.');
        $sourcePaths[$languageFile] = $languagePath;
        $hashes[$languageFile] = hash_file('sha256', $languagePath);
        $languageSourceRows = count($languageData['data']);
        $blackboxNims = array_column($data[$files[0]]['data'] ?? [], 'nim');
        $languageSourceMatchingBlackbox = count(array_intersect($blackboxNims, array_column($languageData['data'], 'nim')));
        // Provenance only. Never transfer real-student histories to synthetic demo identities.
    }
    $profiles = $data[$files[0]]['data'] ?? [];
    $matrix = $data[$files[1]]['test_cases'] ?? [];
    if (count($profiles) !== 11 || count($matrix) !== 11) throw new RuntimeException('Dataset/matrix harus berisi 11 mahasiswa.');
    $cases = [];
    foreach ($matrix as $case) $cases[$case['nim']] = $case;
    $krs = [];
    foreach ($data[$files[3]]['data'] as $row) {
        if (isset($krs[$row['nim']])) throw new RuntimeException('NIM KRS sumber berulang.');
        $krs[$row['nim']] = $row;
    }
    $roster = [];
    foreach ($profiles as $row) {
        $case = $cases[$row['nim']] ?? null;
        if (!$case || mb_strtolower(trim($case['nama'])) !== mb_strtolower(trim($row['nama']))) {
            throw new RuntimeException('Identitas dataset dan matrix tidak sesuai.');
        }
        $roster[] = ['nim' => $row['nim'], 'nama' => $row['nama'],
            'program_studi' => $case['program_studi'], 'semester' => $row['semester']];
    }
    $blackbox = DemoAccountPlan::blackbox($roster);
    $fixed = DemoAccountPlan::local()[1];
    $accounts = array_merge([$fixed], $blackbox);
    $students = [];
    foreach ($accounts as $account) {
        $s = $account['student'];
        $students[] = ['nim' => $s['student_number'], 'nama_siswa' => $s['name'], 'prodi' => $s['study_program'],
            'academic_year' => $s['academic_year'], 'active_semester' => $s['active_semester'],
            'email' => $s['email'], 'kelas' => 'DEMO75'];
    }
    $course = static fn (string $program): array => ['kode_mk' => 'DEMO75-TA2', 'nama_mk' => 'TUGAS AKHIR 2',
        'kelas' => 'DEMO75', 'kode_dosen' => '', 'tahun_ajaran' => '2627', 'semester' => '1', 'program_studi' => $program];
    $fixedCourse = $course('Informatika');
    $fixedCourse['kode_mk'] = 'DEMO75-BASE-TA';
    $fixedCourse['nama_mk'] = 'TUGAS AKHIR';
    $basicKrs = [['no' => 1, 'nim' => $fixed['username'], 'nama' => $fixed['student']['name'],
        'jumlah_mk_krs' => 1, 'krs_mata_kuliah' => [$fixedCourse]]];
    $scenarioKrs = $basicKrs;
    $corrections = [];
    $added = 0;
    foreach ($blackbox as $index => $account) {
        $s = $account['student'];
        $row = $krs[$s['student_number']] ?? null;
        if (!$row) throw new RuntimeException('Salah satu NIM blackbox tidak mempunyai KRS sumber.');
        if (mb_strtolower(trim($row['nama'])) !== mb_strtolower(trim($s['name']))) {
            $corrections[] = ['nim' => $s['student_number'], 'source_name' => $row['nama'], 'fixture_name' => $s['name']];
        }
        $row['no'] = $index + 2;
        $row['nama'] = $s['name'];
        if ($row['jumlah_mk_krs'] !== count($row['krs_mata_kuliah'])) throw new RuntimeException('Jumlah KRS sumber tidak cocok.');
        $basicKrs[] = $row;
        foreach ($row['krs_mata_kuliah'] as $item) {
            if ($item['tahun_ajaran'] !== '2627' || $item['semester'] !== '1') {
                throw new RuntimeException('Periode KRS sumber berubah; tinjau fixture skenario sebelum regenerasi.');
            }
        }
        $names = array_map(static fn ($item) => strtoupper(trim(preg_replace('/\s+/', ' ', $item['nama_mk']))), $row['krs_mata_kuliah']);
        $accepted = ['TUGAS AKHIR', 'TUGAS AKHIR 2', 'TUGAS AKHIR II', 'SIDANG TA 2', 'TA 2', 'TA-2', 'FINAL THESIS 2'];
        if (array_intersect($names, $accepted) === []) {
            $row['krs_mata_kuliah'][] = $course($s['study_program']);
            $row['jumlah_mk_krs']++;
            $added++;
        }
        $scenarioKrs[] = $row;
    }
    $oldDemo = json_decode(file_get_contents(__DIR__.'/../../docs/fixtures/sidang-ta/67-03-profil-akademik-akun-demo.json'), true, 512, JSON_THROW_ON_ERROR);
    $fixedProfile = null;
    foreach ($oldDemo['data'] as $row) if ($row['nim'] === $fixed['username']) $fixedProfile = $row;
    if (!$fixedProfile) throw new RuntimeException('Profil sintetis Krisna tetap belum tersedia.');
    $allProfiles = array_merge([$fixedProfile], $profiles);
    // Fixture-only date synthesis. This is not a rule for official imports or production histories.
    $parseFixtureDate = static function (mixed $value): ?DateTimeImmutable {
        if (!is_string($value) || trim($value) === '') return null;
        $value = mb_strtolower(trim($value));
        $months = ['januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
            'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08', 'september' => '09',
            'oktober' => '10', 'november' => '11', 'desember' => '12'];
        if (preg_match('/^(\\d{1,2}) ('.implode('|', array_keys($months)).') (\\d{4})$/D', $value, $match)) {
            $value = $match[3].'-'.$months[$match[2]].'-'.str_pad($match[1], 2, '0', STR_PAD_LEFT);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    };
    $asOf = new DateTimeImmutable('2026-11-07', new DateTimeZone('UTC'));
    $syntheticDateCount = 0;
    foreach ($allProfiles as &$row) {
        $row['periode_keuangan'] = '2627/1'; // Explicit synthetic period, consistent with 75-04.
        foreach ($row['riwayat_nilai_bahasa'] ?? [] as $index => $history) {
            if (is_string($history['tanggal_tes'] ?? null) && trim($history['tanggal_tes']) !== '') continue;
            $expiry = $parseFixtureDate($history['kedaluwarsa'] ?? null);
            if (isset($history['kedaluwarsa']) && trim((string) $history['kedaluwarsa']) !== '' && !$expiry) {
                throw new RuntimeException('Format kedaluwarsa fixture tidak valid; tanggal sintetis tidak dibuat.');
            }
            if ($expiry) {
                $year = (int) $expiry->format('Y') - 2;
                $month = (int) $expiry->format('m');
                $day = min((int) $expiry->format('d'), (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t'));
                $date = $expiry->setDate($year, $month, $day);
            } else {
                $date = new DateTimeImmutable('2026-10-01', new DateTimeZone('UTC'));
            }
            if ($date > $asOf) $date = $asOf;
            $row['riwayat_nilai_bahasa'][$index]['tanggal_tes'] = $date->format('Y-m-d');
            $row['riwayat_nilai_bahasa'][$index]['tanggal_tes_sintetis'] = true;
            $syntheticDateCount++;
        }
    }
    unset($row);
    $language = [];
    $languageKeys = array_flip(['nim', 'nama', 'nilai_bahasa', 'jenis_tes_bahasa', 'tanggal_tes_bahasa', 'jumlah_tes_bahasa', 'riwayat_nilai_bahasa']);
    foreach ($allProfiles as $row) $language[] = array_intersect_key($row, $languageKeys);
    $finance = [];
    foreach ($allProfiles as $index => $row) {
        $finance[] = ['no' => $index + 1, 'nim' => $row['nim'], 'nama' => $row['nama'],
            'semester' => (string) $row['semester'], 'periode_keuangan' => '2627/1',
            'status_keuangan' => $row['status_keuangan']];
    }
    foreach ([$output, $output.'/sumber', $output.'/impor'] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Tidak dapat membuat folder fixture.');
        chmod($directory, 0700);
    }
    $write = static function (string $file, array $payload) use ($output): void {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        if (file_put_contents($output.'/'.$file, $encoded) === false) throw new RuntimeException('Gagal menulis fixture.');
        chmod($output.'/'.$file, 0600);
    };
    foreach ($sourcePaths as $file => $sourcePath) {
        if (!copy($sourcePath, $output.'/sumber/'.$file)
            || hash_file('sha256', $output.'/sumber/'.$file) !== $hashes[$file]) throw new RuntimeException('Salinan sumber tidak identik.');
        chmod($output.'/sumber/'.$file, 0600);
    }
    $wrap = static fn (array $rows, array $menus, string $description): array => [
        'generated_at' => gmdate('c'), 'source' => $description, 'total' => count($rows),
        'selected_menus' => $menus, 'data' => $rows,
    ];
    $write('blackbox-accounts.json', ['source' => 'Identitas dari dataset blackbox + prodi matrix; bukan data kelayakan', 'total' => 11, 'data' => $roster]);
    $write('impor/75-01-mahasiswa-demo.json', $wrap($students, [], 'DEMO75: master Student, tidak membuat akun User'));
    $write('impor/75-02-krs-demo-sumber.json', $wrap($basicKrs, ['krs_mk'], 'DEMO75: 11 KRS sumber + Krisna tetap sintetis; nama diselaraskan dengan dataset blackbox'));
    $write('impor/75-02b-krs-demo-skenario-ta.json', $wrap($scenarioKrs, ['krs_mk'], 'DEMO75 SINTETIS: KRS sumber + mata kuliah TA uji agar skenario gate lain dapat diuji'));
    $write('impor/75-03-profil-akademik-demo.json', $wrap($allProfiles, $data[$files[0]]['selected_menus'], 'DEMO75 SINTETIS: profil Krisna tetap + 11 skenario akademik blackbox'));
    $write('impor/75-04-bpp-demo.json', $wrap($finance, ['keuangan'], 'DEMO75 SINTETIS: status dari skenario, periode 2627/1 ditetapkan khusus uji; bukan klaim pembayaran nyata'));
    $write('impor/75-05-bahasa-demo.json', $wrap($language, ['bahasa'], 'DEMO79 SINTETIS: skor/jenis/jumlah/validitas skenario tetap; tanggal_tes bertanda tanggal_tes_sintetis berasal dari kedaluwarsa minus dua tahun kalender (batas 2026-11-07), atau 2026-10-01 bila kedaluwarsa kosong; bukan tanggal tes resmi, tidak disalin dari mahasiswa sumber'));
    $sum = static fn (array $rows): int => array_sum(array_column($rows, 'jumlah_mk_krs'));
    $manifest = ['source_count' => count($sourcePaths), 'source_sha256' => $hashes,
        'language_source_rows' => $languageSourceRows, 'language_source_matching_blackbox' => $languageSourceMatchingBlackbox,
        'synthetic_language_dates_added' => $syntheticDateCount,
        'synthetic_language_date_policy' => 'SINTETIS fixture saja: kedaluwarsa minus dua tahun kalender, hari dijepit ke akhir bulan; maksimum 2026-11-07; tanpa kedaluwarsa 2026-10-01. Tanggal eksplisit dipertahankan. Tidak memakai tanggal mahasiswa sumber.', 'student_rows' => count($students), 'blackbox_accounts' => count($blackbox),
        'base_krs_items' => $sum($basicKrs), 'scenario_krs_items' => $sum($scenarioKrs), 'synthetic_ta_added' => $added,
        'name_corrections_in_derived_krs_only' => $corrections,
        'notes' => ['Sumber asli tidak diubah.', 'KRS skenario harus diimpor sesudah KRS sumber karena impor mengganti daftar periode.',
            'Sumber bahasa tambahan hanya untuk provenance; skor dan tanggal tidak digabung ke identitas demo.', 'Periode BPP pada fixture demo ditetapkan untuk uji, bukan diturunkan sebagai fakta dari KRS.']];
    $write('manifest.json', $manifest);
    $readme = "# Fixture privat reset75\n\nLokal: hanya dua akun tetap memakai password `password`. Docker: dua akun tetap memakai DADU_TEST_PASSWORD server; sebelas akun berikut memakai password `password`.\n\n";
    $readme .= "| Nama dataset | Email login staging | NIM |\n| --- | --- | --- |\n";
    foreach ($blackbox as $account) $readme .= '| '.$account['student']['name'].' | '.$account['email'].' | '.$account['username']." |\n";
    $readme .= "\nUrutan impor:75-01 Mahasiswa →75-02b KRS →75-03 Profil Akademik →75-04 Profil Akademik →75-05 Profil Akademik (bahasa).75-02 adalah alternatif untuk menguji KRS asli yang tidak memuat TA; jangan impor sesudah75-02b jika ingin mempertahankan skenario TA.\n\n";
    $readme .= "Folder sumber memuat salinan ".count($sourcePaths)." berkas input persis. Lihat manifest untuk perbedaan fixture turunan. Roster blackbox-accounts.json diperlukan hanya oleh setup Docker dan tidak berisi password dua akun tetap.\n";
    $readme .= "\nTanggal bahasa SINTETIS untuk fixture uji saja: kedaluwarsa minus dua tahun kalender, maksimum 2026-11-07, atau 2026-10-01 bila kedaluwarsa kosong. Tanggal eksplisit dipertahankan. Penanda tanggal_tes_sintetis menunjukkan tanggal buatan, bukan tanggal resmi. Skor/jenis/jumlah/status/validitas skenario tetap. Sumber bahasa tambahan hanya disimpan untuk provenance, tidak digabung berdasarkan indeks atau NIM.\n";
    $readme .= "\nSumber bahasa tambahan: ".$languageSourceRows." baris; kecocokan NIM blackbox: ".$languageSourceMatchingBlackbox.". Tanggal sintetis ditambahkan: ".$syntheticDateCount.".\n\nSHA256 sumber (byte-identik):\n";
    foreach ($hashes as $file => $hash) $readme .= '- '.$file.': `'.$hash."`\n";
    file_put_contents($output.'/README.md', $readme);
    chmod($output.'/README.md', 0600);
    echo 'Prepared private imports: '.count($students).' students, '.count($blackbox).' blackbox identities, '.$sum($scenarioKrs)." scenario KRS items.\n";
    echo "Original files unchanged; no database accessed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
