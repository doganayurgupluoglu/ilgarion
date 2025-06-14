<?php
// /admin/audit/api/export_audit_logs.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// BASE_PATH tanımı
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../../'));
}

require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';

// Yetki kontrolü
if (!is_user_logged_in()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!has_permission($pdo, 'admin.audit_log.export')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

try {
    // POST verilerini al
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $format = $_POST['format'] ?? 'csv';
    $includeUserAgent = isset($_POST['include_user_agent']) && $_POST['include_user_agent'] === 'true';
    $includeValues = isset($_POST['include_values']) && $_POST['include_values'] === 'true';
    $includeIpAddress = isset($_POST['include_ip_address']) && $_POST['include_ip_address'] === 'true';
    
    // Tarih validasyonu
    if (empty($startDate) || empty($endDate)) {
        throw new Exception('Başlangıç ve bitiş tarihi gerekli');
    }
    
    $startDateTime = new DateTime($startDate);
    $endDateTime = new DateTime($endDate);
    
    if ($startDateTime > $endDateTime) {
        throw new Exception('Başlangıç tarihi bitiş tarihinden büyük olamaz');
    }
    
    // Maksimum 1 yıl sınırlaması
    $maxDate = clone $startDateTime;
    $maxDate->add(new DateInterval('P1Y'));
    if ($endDateTime > $maxDate) {
        throw new Exception('Maksimum 1 yıllık veri export edilebilir');
    }
    
    // SQL sorgusu oluştur
    $selectFields = [
        'al.id',
        'al.user_id',
        'u.username',
        'al.action',
        'al.target_type',
        'al.target_id',
        'al.created_at'
    ];
    
    if ($includeIpAddress) {
        $selectFields[] = 'al.ip_address';
    }
    
    if ($includeUserAgent) {
        $selectFields[] = 'al.user_agent';
    }
    
    if ($includeValues) {
        $selectFields[] = 'al.old_values';
        $selectFields[] = 'al.new_values';
    }
    
    $query = "
        SELECT " . implode(', ', $selectFields) . "
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.created_at >= :start_date 
        AND al.created_at <= :end_date
        ORDER BY al.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':start_date', $startDateTime->format('Y-m-d H:i:s'));
    $stmt->bindValue(':end_date', $endDateTime->format('Y-m-d H:i:s'));
    $stmt->execute();
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        throw new Exception('Belirtilen tarih aralığında veri bulunamadı');
    }
    
    // Format'a göre export et
    switch ($format) {
        case 'csv':
            exportToCsv($logs, $includeUserAgent, $includeValues, $includeIpAddress);
            break;
        case 'json':
            exportToJson($logs);
            break;
        case 'xlsx':
            exportToXlsx($logs, $includeUserAgent, $includeValues, $includeIpAddress);
            break;
        default:
            throw new Exception('Geçersiz format');
    }

} catch (Exception $e) {
    error_log("Audit export error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * CSV formatında export
 */
function exportToCsv($logs, $includeUserAgent, $includeValues, $includeIpAddress) {
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "audit_log_$timestamp.csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: no-cache, must-revalidate');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM ekle
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header'ları oluştur
    $headers = ['ID', 'User ID', 'Username', 'Action', 'Target Type', 'Target ID', 'Created At'];
    
    if ($includeIpAddress) {
        $headers[] = 'IP Address';
    }
    
    if ($includeUserAgent) {
        $headers[] = 'User Agent';
    }
    
    if ($includeValues) {
        $headers[] = 'Old Values';
        $headers[] = 'New Values';
    }
    
    fputcsv($output, $headers);
    
    // Verileri yaz
    foreach ($logs as $log) {
        $row = [
            $log['id'],
            $log['user_id'] ?: 'N/A',
            $log['username'] ?: 'System',
            $log['action'],
            $log['target_type'] ?: 'N/A',
            $log['target_id'] ?: 'N/A',
            $log['created_at']
        ];
        
        if ($includeIpAddress) {
            $row[] = $log['ip_address'] ?: 'N/A';
        }
        
        if ($includeUserAgent) {
            $row[] = $log['user_agent'] ?: 'N/A';
        }
        
        if ($includeValues) {
            $row[] = $log['old_values'] ?: 'N/A';
            $row[] = $log['new_values'] ?: 'N/A';
        }
        
        fputcsv($output, $row);
    }
    
    fclose($output);
}

/**
 * JSON formatında export
 */
function exportToJson($logs) {
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "audit_log_$timestamp.json";
    
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: no-cache, must-revalidate');
    
    // JSON değerlerini parse et
    foreach ($logs as &$log) {
        if (isset($log['old_values']) && $log['old_values']) {
            $log['old_values_parsed'] = json_decode($log['old_values'], true);
        }
        if (isset($log['new_values']) && $log['new_values']) {
            $log['new_values_parsed'] = json_decode($log['new_values'], true);
        }
    }
    
    $exportData = [
        'export_info' => [
            'exported_at' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['user_id'],
            'total_records' => count($logs),
            'format' => 'json'
        ],
        'logs' => $logs
    ];
    
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Excel formatında export (basit CSV benzeri implementasyon)
 */
function exportToXlsx($logs, $includeUserAgent, $includeValues, $includeIpAddress) {
    // Bu basit bir XLSX implementasyonu, gerçek projede PhpSpreadsheet kullanılabilir
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "audit_log_$timestamp.xlsx";
    
    // Şimdilik CSV olarak export et ama .xlsx uzantısı ile
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: no-cache, must-revalidate');
    
    // CSV formatını kullan (gerçek projede PhpSpreadsheet kullanılmalı)
    exportToCsv($logs, $includeUserAgent, $includeValues, $includeIpAddress);
}
?>