<?php
require_once '../config/config.php';

$backupFile = 'backup.sql';
$mysqldumpPath = 'c:\\xampp\\mysql\\bin\\mysqldump.exe';

if (!file_exists($mysqldumpPath)) {
    // Fallback to command line if it's in PATH
    $mysqldumpPath = 'mysqldump';
}

$command = sprintf('%s -h %s -u %s -p%s --hex-blob --routines --triggers %s > %s',
    escapeshellcmd($mysqldumpPath),
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_NAME),
    escapeshellarg($backupFile)
);

exec($command, $output, $returnVar);

if ($returnVar === 0) {
    if (file_exists($backupFile)) {
        echo "<h2 style='color: green;'>Backup Berhasil!</h2>";
        echo "Database berhasil di-backup ke file: <b>" . htmlspecialchars($backupFile) . "</b><br>";
        echo "Ukuran file: " . round(filesize($backupFile) / 1024, 2) . " KB<br><br>";
        echo "<a href='index.php'>Kembali ke Dashboard</a>";
    } else {
        echo "<h2 style='color: red;'>Gagal Membuat File Backup</h2>";
    }
} else {
    echo "<h2 style='color: red;'>Backup Gagal (Error Code: $returnVar)</h2>";
    echo "<pre>" . print_r($output, true) . "</pre>";
}
?>
