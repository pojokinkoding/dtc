<?php
require_once '../config/config.php';

$backupFile = 'backup.sql';

if (!file_exists($backupFile)) {
    echo "<h2 style='color: red;'>File Backup Tidak Ditemukan!</h2>";
    echo "Tidak ada file backup.sql yang bisa di-restore.<br><br>";
    echo "<a href='index.php'>Kembali ke Dashboard</a>";
    exit;
}

try {
    $conn = getDBConnection();
    // Enable emulation of prepares to allow multiple queries if needed, 
    // but better yet, we can execute the file contents directly.
    $sql = file_get_contents($backupFile);
    
    // Execute the SQL queries
    $conn->exec($sql);
    
    echo "<h2 style='color: green;'>Restore Berhasil!</h2>";
    echo "Database berhasil dikembalikan dari file <b>backup.sql</b> menggunakan PDO.<br><br>";
    echo "<a href='index.php'>Kembali ke Dashboard</a>";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Restore Gagal</h2>";
    echo "<pre>PDO Error: " . htmlspecialchars($e->getMessage()) . "</pre>";
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Restore Gagal</h2>";
    echo "<pre>Error: " . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
