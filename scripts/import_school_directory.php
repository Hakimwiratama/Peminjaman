<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db']['host'], $config['db']['port'], $config['db']['name']),
    $config['db']['user'],
    $config['db']['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS school_directory (
        school_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        npsn VARCHAR(20) NULL,
        name VARCHAR(255) NOT NULL,
        province_code VARCHAR(13) NOT NULL,
        regency_code VARCHAR(13) NOT NULL,
        school_type VARCHAR(40) NOT NULL,
        INDEX idx_school_directory_region_name (province_code, regency_code, name),
        INDEX idx_school_directory_npsn (npsn)
    ) ENGINE=InnoDB'
);
$schoolColumns = $pdo->query('SHOW COLUMNS FROM school_directory')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('school_id', $schoolColumns, true)) {
    $pdo->exec('ALTER TABLE school_directory DROP PRIMARY KEY, MODIFY npsn VARCHAR(20) NULL, ADD school_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST, ADD INDEX idx_school_directory_npsn (npsn)');
}

function normalize_region_name(string $value, bool $province = false): string
{
    $value = mb_strtoupper(trim($value), 'UTF-8');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii !== false) {
        $value = $ascii;
    }
    $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

    if ($province) {
        if (str_contains($value, 'JAKARTA')) {
            return 'DKI JAKARTA';
        }
        if (str_contains($value, 'YOGYAKARTA')) {
            return 'DI YOGYAKARTA';
        }
        if (str_contains($value, 'BANGKA') && str_contains($value, 'BELITUNG')) {
            return 'KEPULAUAN BANGKA BELITUNG';
        }
        if (str_contains($value, 'RIAU') && (str_starts_with($value, 'KEP ') || str_starts_with($value, 'KEPULAUAN '))) {
            return 'KEPULAUAN RIAU';
        }
        if (str_contains($value, 'ACEH')) {
            return 'ACEH';
        }
        $value = preg_replace('/^DAERAH KHUSUS IBUKOTA /', 'DKI ', $value) ?? $value;
    } else {
        $value = preg_replace('/^(?:KABUPATEN|KAB|KOTA)(?:\s+(?:ADMINISTRASI|ADM))?\s+/', '', $value) ?? $value;
        $value = str_replace('HASUDUTAN', 'HASUNDUTAN', $value);
        $value = preg_replace('/^KEP\s+/', 'KEPULAUAN ', $value) ?? $value;
        $value = str_replace('LIMA PULUH KOTO', 'LIMA PULUH KOTA', $value);
        $value = str_replace('KEPULAUAN MOROTAI', 'PULAU MOROTAI', $value);
        $value = str_replace('PANGKAJENE KEPULAUAN', 'PANGKAJENE DAN KEPULAUAN', $value);
    }

    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    return str_replace(' ', '', $value);
}

function regency_kind(string $value): string
{
    $value = mb_strtoupper(trim($value), 'UTF-8');
    if (preg_match('/^KOTA(?:\.|\s|$)/', $value)) {
        return 'KOTA';
    }
    if (preg_match('/^KAB(?:UPATEN)?(?:\.|\s|$)/', $value)) {
        return 'KABUPATEN';
    }
    return 'OTHER';
}

$regionRows = $pdo->query('SELECT code,parent_code,name,level FROM region_codes')->fetchAll();
$provinceCodes = [];
$regencyCodes = [];
foreach ($regionRows as $region) {
    if ((int) $region['level'] === 1) {
        $provinceCodes[normalize_region_name($region['name'], true)] = $region['code'];
    } elseif ((int) $region['level'] === 2) {
        $key = $region['parent_code'] . '|' . regency_kind($region['name']) . '|' . normalize_region_name($region['name']);
        $regencyCodes[$key] = $region['code'];
    }
}

$downloadUrl = 'https://raw.githubusercontent.com/prodhokter/dataset-sekolah-indonesia/master/sekolah-indonesia.csv';
$temporaryFile = tempnam(sys_get_temp_dir(), 'sipintar-schools-');
if ($temporaryFile === false) {
    throw new RuntimeException('Tidak dapat membuat file sementara untuk dataset sekolah.');
}

$download = fopen($temporaryFile, 'wb');
$curl = curl_init($downloadUrl);
if ($download === false || $curl === false) {
    if (is_resource($download)) {
        fclose($download);
    }
    unlink($temporaryFile);
    throw new RuntimeException('Tidak dapat memulai unduhan dataset sekolah.');
}

curl_setopt_array($curl, [
    CURLOPT_FILE => $download,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_FAILONERROR => true,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 300,
    CURLOPT_USERAGENT => 'SIPINTAR school directory importer',
]);
$downloaded = curl_exec($curl);
$downloadError = curl_error($curl);
$httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);
fclose($download);
if ($downloaded === false || $httpStatus < 200 || $httpStatus >= 300) {
    unlink($temporaryFile);
    throw new RuntimeException("Unduhan dataset sekolah gagal (HTTP {$httpStatus}): {$downloadError}");
}

$input = fopen($temporaryFile, 'rb');
if ($input === false) {
    unlink($temporaryFile);
    throw new RuntimeException('File dataset sekolah tidak dapat dibaca.');
}

$pdo->exec('CREATE TEMPORARY TABLE school_directory_stage LIKE school_directory');
$inserted = 0;
$unmapped = 0;
$unmappedRegions = [];
$npsnWithoutValidNumber = 0;
$emptyNames = 0;
$malformedRows = 0;
$batch = [];
$batchSize = 500;
$header = fgetcsv($input, 0, ';', '"', '\\');
if (!is_array($header)) {
    fclose($input);
    unlink($temporaryFile);
    throw new RuntimeException('Header dataset sekolah tidak dapat dibaca.');
}
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? $header[0];
$columns = array_flip($header);
foreach (['npsn', 'name', 'province', 'city', 'type'] as $requiredColumn) {
    if (!isset($columns[$requiredColumn])) {
        fclose($input);
        unlink($temporaryFile);
        throw new RuntimeException("Kolom {$requiredColumn} tidak ditemukan pada dataset sekolah.");
    }
}

$insertBatch = static function (array $rows) use ($pdo): void {
    if (!$rows) {
        return;
    }
    $values = implode(',', array_fill(0, count($rows), '(?,?,?,?,?)'));
    $stmt = $pdo->prepare("INSERT INTO school_directory_stage (npsn,name,province_code,regency_code,school_type) VALUES {$values}");
    $params = [];
    foreach ($rows as $row) {
        array_push($params, ...$row);
    }
    $stmt->execute($params);
};

while (($record = fgetcsv($input, 0, ';', '"', '\\')) !== false) {
    if (count($record) < count($header)) {
        $malformedRows++;
        continue;
    }
    $provinceName = (string) $record[$columns['province']];
    $provinceCode = $provinceCodes[normalize_region_name($provinceName, true)] ?? null;
    $cityName = (string) $record[$columns['city']];
    $regencyKey = $provinceCode . '|' . regency_kind($cityName) . '|' . normalize_region_name($cityName);
    $regencyCode = $provinceCode ? ($regencyCodes[$regencyKey] ?? null) : null;
    $npsn = trim((string) $record[$columns['npsn']]);
    $name = trim((string) $record[$columns['name']]);
    if (!$provinceCode || !$regencyCode) {
        $unmapped++;
        $regionName = $provinceName . ' / ' . (string) $record[$columns['city']];
        $unmappedRegions[$regionName] = ($unmappedRegions[$regionName] ?? 0) + 1;
        continue;
    }
    if ($name === '') {
        $emptyNames++;
        continue;
    }
    if (!preg_match('/^\d{8}$/', $npsn)) {
        $npsnWithoutValidNumber++;
        $npsn = '';
    }
    $batch[] = [
        $npsn ?: null,
        mb_substr($name, 0, 255, 'UTF-8'),
        $provinceCode,
        $regencyCode,
        mb_substr(trim((string) $record[$columns['type']]), 0, 40, 'UTF-8'),
    ];
    if (count($batch) >= $batchSize) {
        $insertBatch($batch);
        $inserted += count($batch);
        $batch = [];
        if ($inserted % 25000 === 0) {
            echo "Dibaca dan dipetakan: {$inserted} sekolah." . PHP_EOL;
        }
    }
}
fclose($input);
unlink($temporaryFile);

if ($batch) {
    $insertBatch($batch);
    $inserted += count($batch);
}
if ($inserted === 0) {
    throw new RuntimeException('Tidak ada sekolah yang cocok dengan data wilayah SIPINTAR; direktori lama tidak diubah.');
}

$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM school_directory');
    $pdo->exec('INSERT INTO school_directory (npsn,name,province_code,regency_code,school_type) SELECT npsn,name,province_code,regency_code,school_type FROM school_directory_stage');
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

echo "Direktori sekolah siap: {$inserted} sekolah diimpor." . PHP_EOL;
if ($unmapped > 0) {
    arsort($unmappedRegions);
    $examples = [];
    foreach (array_slice($unmappedRegions, 0, 30, true) as $regionName => $count) {
        $examples[] = "{$regionName} ({$count})";
    }
    echo "{$unmapped} baris tidak cocok dengan wilayah SIPINTAR; wilayah: " . implode('; ', $examples) . PHP_EOL;
}
echo "Sekolah tanpa NPSN 8 digit: {$npsnWithoutValidNumber}; nama kosong {$emptyNames}, baris rusak {$malformedRows}." . PHP_EOL;
