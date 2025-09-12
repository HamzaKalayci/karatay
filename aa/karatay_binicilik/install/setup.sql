-- Karatay Binicilik Randevu Sistemi
-- Veritabanı kurulum scripti

-- Veritabanını oluştur (eğer yoksa)
CREATE DATABASE IF NOT EXISTS karatay_binicilik 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_turkish_ci;

-- Veritabanını kullan
USE karatay_binicilik;

-- Randevular tablosu
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- İndeksler performans için
    INDEX idx_date_time (appointment_date, appointment_time),
    INDEX idx_date (appointment_date),
    INDEX idx_created_at (created_at),
    
    -- Aynı tarih ve saatte sadece bir randevu olabilir
    UNIQUE KEY unique_date_time (appointment_date, appointment_time)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_turkish_ci
  COMMENT='Binicilik kulübü randevu tablosu';

-- Örnek veriler (isteğe bağlı)
INSERT IGNORE INTO appointments (appointment_date, appointment_time, student_name, notes) VALUES
('2025-09-06', '10:00:00', 'Ahmet Yılmaz', 'İlk ders'),
('2025-09-06', '14:00:00', 'Ayşe Kaya', 'Atlama dersi'),
('2025-09-06', '16:00:00', 'Mehmet Demir', 'Temel binicilik');

-- Yönetici tablosu (gelecekte kullanım için)
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    passcode VARCHAR(4) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_username (username),
    INDEX idx_active (is_active)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_turkish_ci
  COMMENT='Sistem yöneticileri tablosu';

-- Varsayılan yönetici (şifre: 1234)
INSERT IGNORE INTO admins (username, passcode, full_name, email) VALUES
('admin', '1234', 'Sistem Yöneticisi', 'admin@karatay.com');

-- Sistem ayarları tablosu
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_turkish_ci
  COMMENT='Sistem ayarları';

-- Varsayılan ayarlar
INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES
('club_name', 'Karatay Binicilik Kulübü', 'Kulüp adı'),
('work_start_time', '09:00', 'Çalışma saatlerinin başlangıcı'),
('work_end_time', '20:00', 'Çalışma saatlerinin bitişi'),
('appointment_duration', '60', 'Randevu süresi (dakika)'),
('max_daily_appointments', '12', 'Günlük maksimum randevu sayısı'),
('timezone', 'Europe/Istanbul', 'Saat dilimi');

-- Loglama tablosu (işlem kayıtları için)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_type ENUM('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT') NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    admin_username VARCHAR(50),
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    INDEX idx_admin (admin_username),
    
    FOREIGN KEY (admin_username) REFERENCES admins(username) ON DELETE SET NULL
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_turkish_ci
  COMMENT='Sistem aktivite logları';

-- Görünüm (View) - Günlük randevu özeti
CREATE OR REPLACE VIEW daily_appointment_summary AS
SELECT 
    appointment_date as date,
    COUNT(*) as total_appointments,
    COUNT(CASE WHEN appointment_time BETWEEN '09:00' AND '12:00' THEN 1 END) as morning_appointments,
    COUNT(CASE WHEN appointment_time BETWEEN '13:00' AND '17:00' THEN 1 END) as afternoon_appointments,
    COUNT(CASE WHEN appointment_time BETWEEN '18:00' AND '20:00' THEN 1 END) as evening_appointments,
    GROUP_CONCAT(
        CONCAT(appointment_time, ' - ', student_name) 
        ORDER BY appointment_time 
        SEPARATOR '; '
    ) as appointments_list
FROM appointments 
GROUP BY appointment_date
ORDER BY appointment_date DESC;

-- Stored Procedure - Randevu istatistikleri
DELIMITER //
CREATE PROCEDURE GetAppointmentStats(IN start_date DATE, IN end_date DATE)
BEGIN
    SELECT 
        'Toplam Randevu' as metric,
        COUNT(*) as value
    FROM appointments 
    WHERE appointment_date BETWEEN start_date AND end_date
    
    UNION ALL
    
    SELECT 
        'Farklı Öğrenci Sayısı' as metric,
        COUNT(DISTINCT student_name) as value
    FROM appointments 
    WHERE appointment_date BETWEEN start_date AND end_date
    
    UNION ALL
    
    SELECT 
        'En Yoğun Saat' as metric,
        appointment_time as value
    FROM appointments 
    WHERE appointment_date BETWEEN start_date AND end_date
    GROUP BY appointment_time
    ORDER BY COUNT(*) DESC
    LIMIT 1;
END //
DELIMITER ;

-- Trigger - Randevu silme loglaması
DELIMITER //
CREATE TRIGGER after_appointment_delete
AFTER DELETE ON appointments
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (action_type, table_name, record_id, details)
    VALUES ('DELETE', 'appointments', OLD.id, JSON_OBJECT(
        'appointment_date', OLD.appointment_date,
        'appointment_time', OLD.appointment_time,
        'student_name', OLD.student_name,
        'notes', OLD.notes
    ));
END //
DELIMITER ;

-- Kullanışlı sorgular için saklı prosedürler

-- Belirli tarih aralığındaki randevuları getir
DELIMITER //
CREATE PROCEDURE GetAppointmentsByDateRange(IN start_date DATE, IN end_date DATE)
BEGIN
    SELECT 
        appointment_date,
        appointment_time,
        student_name,
        notes,
        created_at
    FROM appointments 
    WHERE appointment_date BETWEEN start_date AND end_date
    ORDER BY appointment_date, appointment_time;
END //
DELIMITER ;

-- Öğrenci geçmişini getir
DELIMITER //
CREATE PROCEDURE GetStudentHistory(IN student_search VARCHAR(255))
BEGIN
    SELECT 
        appointment_date,
        appointment_time,
        student_name,
        notes,
        created_at
    FROM appointments 
    WHERE student_name LIKE CONCAT('%', student_search, '%')
    ORDER BY appointment_date DESC, appointment_time DESC;
END //
DELIMITER ;

-- Veritabanı kurulumu tamamlandı
SELECT 'Karatay Binicilik Randevu Sistemi veritabanı başarıyla kuruldu!' as message;