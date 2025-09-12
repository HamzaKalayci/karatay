<?php
// test_connection.php
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Karatay Binicilik - Veritabanı Bağlantı Testi</h1>";

try {
    // Database sınıfını dahil et
    require_once 'config/database.php';
    
    echo "<p>✅ Database.php dosyası başarıyla yüklendi</p>";
    
    // Database bağlantısını test et
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<p>✅ Veritabanı bağlantısı başarılı</p>";
        
        // Tabloların varlığını kontrol et
        $tables = ['appointments', 'admins', 'settings'];
        foreach ($tables as $table) {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() > 0) {
                echo "<p>✅ Tablo '$table' mevcut</p>";
            } else {
                echo "<p>❌ Tablo '$table' bulunamadı</p>";
            }
        }
        
        // Appointments tablosundan veri sayısını al
        $stmt = $db->query("SELECT COUNT(*) as count FROM appointments");
        $count = $stmt->fetch()['count'];
        echo "<p>📊 Toplam randevu sayısı: $count</p>";
        
        // Son 5 randevuyu göster
        $stmt = $db->query("SELECT appointment_date, appointment_time, student_name FROM appointments ORDER BY created_at DESC LIMIT 5");
        $appointments = $stmt->fetchAll();
        
        if (count($appointments) > 0) {
            echo "<h3>Son Randevular:</h3><ul>";
            foreach ($appointments as $apt) {
                echo "<li>{$apt['appointment_date']} {$apt['appointment_time']} - {$apt['student_name']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>Henüz randevu bulunmuyor</p>";
        }
        
    } else {
        echo "<p>❌ Veritabanı bağlantısı başarısız</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Hata: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Sistem Bilgileri:</h3>";
echo "<p>PHP Sürümü: " . PHP_VERSION . "</p>";
echo "<p>PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'Aktif' : 'Pasif') . "</p>";
echo "<p>Tarih/Saat: " . date('Y-m-d H:i:s') . "</p>";

echo "<hr>";
echo "<h3>API Test:</h3>";
echo "<p><a href='api/appointments.php' target='_blank'>API Endpoints Testi</a></p>";
echo "<p><a href='index.html'>Ana Sayfaya Dön</a></p>";
?>