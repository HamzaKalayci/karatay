<?php
// api/appointments.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch($method) {
        case 'GET':
            getAppointments($db);
            break;
        case 'POST':
            createAppointment($db, $input);
            break;
        case 'DELETE':
            deleteAppointment($db, $input);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Sunucu hatası: ' . $e->getMessage()]);
}

function getAppointments($db) {
    try {
        $date = $_GET['date'] ?? null;
        
        if ($date) {
            // Belirli bir tarih için randevuları getir
            $query = "SELECT id, appointment_date, appointment_time, student_name, notes, created_at 
                     FROM appointments 
                     WHERE appointment_date = ? 
                     ORDER BY appointment_time";
            $stmt = $db->prepare($query);
            $stmt->execute([$date]);
        } else {
            // Tüm randevuları getir (son 30 gün)
            $query = "SELECT id, appointment_date, appointment_time, student_name, notes, created_at 
                     FROM appointments 
                     WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                     ORDER BY appointment_date, appointment_time";
            $stmt = $db->prepare($query);
            $stmt->execute();
        }
        
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Tarihlere göre grupla
        $groupedAppointments = [];
        foreach ($appointments as $appointment) {
            $date = $appointment['appointment_date'];
            if (!isset($groupedAppointments[$date])) {
                $groupedAppointments[$date] = [];
            }
            $groupedAppointments[$date][] = [
                'id' => $appointment['id'],
                'time' => substr($appointment['appointment_time'], 0, 5), // HH:MM formatında
                'student' => $appointment['student_name'],
                'notes' => $appointment['notes'],
                'createdAt' => $appointment['created_at']
            ];
        }
        
        echo json_encode($groupedAppointments);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Randevular getirilemedi: ' . $e->getMessage()]);
    }
}

function createAppointment($db, $input) {
    try {
        // Gerekli alanları kontrol et
        if (empty($input['date']) || empty($input['time']) || empty($input['student'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Tarih, saat ve öğrenci adı zorunludur']);
            return;
        }
        
        // Tarih formatını kontrol et
        $date = $input['date'];
        $time = $input['time'];
        $student = trim($input['student']);
        $notes = isset($input['notes']) ? trim($input['notes']) : '';
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['error' => 'Geçersiz tarih formatı']);
            return;
        }
        
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            http_response_code(400);
            echo json_encode(['error' => 'Geçersiz saat formatı']);
            return;
        }
        
        // Aynı tarih ve saatte randevu var mı kontrol et
        $checkQuery = "SELECT id FROM appointments WHERE appointment_date = ? AND appointment_time = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$date, $time . ':00']);
        
        if ($checkStmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Bu saatte zaten randevu bulunuyor']);
            return;
        }
        
        // Randevuyu kaydet
        $insertQuery = "INSERT INTO appointments (appointment_date, appointment_time, student_name, notes, created_at) 
                       VALUES (?, ?, ?, ?, NOW())";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->execute([$date, $time . ':00', $student, $notes]);
        
        $appointmentId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'id' => $appointmentId,
            'message' => 'Randevu başarıyla oluşturuldu'
        ]);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Randevu oluşturulamadı: ' . $e->getMessage()]);
    }
}

function deleteAppointment($db, $input) {
    try {
        if (empty($input['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Randevu ID gerekli']);
            return;
        }
        
        $appointmentId = $input['id'];
        
        // Randevu var mı kontrol et
        $checkQuery = "SELECT id, student_name, appointment_date, appointment_time FROM appointments WHERE id = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$appointmentId]);
        
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Randevu bulunamadı']);
            return;
        }
        
        $appointmentData = $checkStmt->fetch();
        
        // Randevuyu sil
        $deleteQuery = "DELETE FROM appointments WHERE id = ?";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->execute([$appointmentId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Randevu başarıyla silindi',
            'deleted' => $appointmentData
        ]);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Randevu silinemedi: ' . $e->getMessage()]);
    }
}
?>