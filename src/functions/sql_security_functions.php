<?php
// src/functions/sql_security_functions.php

/**
 * SQL Injection koruması için güvenli veritabanı işlemleri
 */

/**
 * Güvenli WHERE IN clause oluşturur
 * @param PDO $pdo Veritabanı bağlantısı
 * @param array $values Değerler dizisi
 * @param string $type Veri tipi ('int', 'string')
 * @return array ['placeholders' => string, 'params' => array]
 */
function create_safe_in_clause(PDO $pdo, array $values, string $type = 'int'): array {
    if (empty($values)) {
        return ['placeholders' => '(-1)', 'params' => []]; // Boş sonuç için
    }

    $placeholders = [];
    $params = [];
    
    foreach ($values as $index => $value) {
        $placeholder = ":in_param_$index";
        $placeholders[] = $placeholder;
        
        // Tip kontrolü ve validasyon
        switch ($type) {
            case 'int':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException("Invalid integer value in IN clause: $value");
                }
                $params[$placeholder] = (int)$value;
                break;
            case 'string':
                if (strlen($value) > 255) { // Maksimum string uzunluğu
                    throw new InvalidArgumentException("String too long in IN clause");
                }
                $params[$placeholder] = (string)$value;
                break;
            default:
                throw new InvalidArgumentException("Unsupported type for IN clause: $type");
        }
    }
    
    return [
        'placeholders' => '(' . implode(',', $placeholders) . ')',
        'params' => $params
    ];
}

/**
 * Güvenli ORDER BY clause oluşturur
 * @param string $column Sütun adı
 * @param string $direction Sıralama yönü
 * @param array $allowed_columns İzin verilen sütunlar
 * @param array $allowed_directions İzin verilen yönler
 * @return string Güvenli ORDER BY clause
 */
function create_safe_order_by(
    string $column, 
    string $direction = 'ASC', 
    array $allowed_columns = [],
    array $allowed_directions = ['ASC', 'DESC']
): string {
    // Sütun adı kontrolü
    if (!empty($allowed_columns) && !in_array($column, $allowed_columns, true)) {
        throw new InvalidArgumentException("Invalid column for ORDER BY: $column");
    }
    
    // Sütun adı format kontrolü (sadece harfler, rakamlar ve alt çizgi)
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $column)) {
        throw new InvalidArgumentException("Invalid column name format: $column");
    }
    
    // Yön kontrolü
    $direction = strtoupper($direction);
    if (!in_array($direction, $allowed_directions, true)) {
        throw new InvalidArgumentException("Invalid direction for ORDER BY: $direction");
    }
    
    return "`$column` $direction";
}

/**
 * Güvenli LIMIT clause oluşturur
 * @param int $limit Limit değeri
 * @param int $offset Offset değeri
 * @param int $max_limit Maksimum izin verilen limit
 * @return string Güvenli LIMIT clause
 */
function create_safe_limit(int $limit, int $offset = 0, int $max_limit = 1000): string {
    if ($limit < 1) {
        throw new InvalidArgumentException("Limit must be positive: $limit");
    }
    
    if ($limit > $max_limit) {
        throw new InvalidArgumentException("Limit too large: $limit (max: $max_limit)");
    }
    
    if ($offset < 0) {
        throw new InvalidArgumentException("Offset must be non-negative: $offset");
    }
    
    if ($offset > 0) {
        return "LIMIT $offset, $limit";
    }
    
    return "LIMIT $limit";
}

/**
 * Güvenli LIKE pattern oluşturur
 * @param string $search Arama terimi
 * @param string $type Pattern tipi ('contains', 'starts', 'ends', 'exact')
 * @return string Escape edilmiş LIKE pattern
 */
function create_safe_like_pattern(string $search, string $type = 'contains'): string {
    // Özel karakterleri escape et
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    
    // Maksimum uzunluk kontrolü
    if (strlen($escaped) > 100) {
        throw new InvalidArgumentException("Search term too long");
    }
    
    switch ($type) {
        case 'contains':
            return "%$escaped%";
        case 'starts':
            return "$escaped%";
        case 'ends':
            return "%$escaped";
        case 'exact':
            return $escaped;
        default:
            throw new InvalidArgumentException("Invalid LIKE pattern type: $type");
    }
}

/**
 * SQL sorgusunu güvenlik açısından analiz eder
 * @param string $query SQL sorgusu
 * @return array Analiz sonucu
 */
function analyze_query_security(string $query): array {
    $risks = [];
    $warnings = [];
    
    // Küçük harfe çevir analiz için
    $query_lower = strtolower($query);
    
    // Tehlikeli fonksiyonlar - DÜZELTME: sadece fonksiyon çağrıları kontrol et
    $dangerous_patterns = [
        '/\bload_file\s*\(/i',
        '/\binto\s+outfile\b/i', 
        '/\binto\s+dumpfile\b/i',
        '/\bload\s+data\b/i',
        '/\bexec\s*\(/i',
        '/\bsystem\s*\(/i',  // system() fonksiyon çağrısı
        '/\beval\s*\(/i',
        '/\bconcat_ws\s*\(/i'
    ];
    
    foreach ($dangerous_patterns as $pattern) {
        if (preg_match($pattern, $query)) {
            $risks[] = "Dangerous function detected in query";
        }
    }
    
    // Union-based injection belirtileri
    if (preg_match('/union\s+select/i', $query)) {
        $risks[] = "UNION SELECT detected - potential injection";
    }
    
    // Yorum karakterleri
    if (strpos($query, '--') !== false || strpos($query, '/*') !== false) {
        $warnings[] = "SQL comments detected";
    }
    
    // Çoklu statement
    if (substr_count($query, ';') > 1) {
        $risks[] = "Multiple statements detected";
    }
    
    // Prepared statement kontrolü
    if (strpos($query, '?') === false && strpos($query, ':') === false) {
        $warnings[] = "No parameter placeholders found";
    }
    
    return [
        'is_safe' => empty($risks),
        'risks' => $risks,
        'warnings' => $warnings,
        'risk_level' => count($risks) > 0 ? 'high' : (count($warnings) > 0 ? 'medium' : 'low')
    ];
}

/**
 * Prepared statement'ı güvenli şekilde execute eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $query SQL sorgusu
 * @param array $params Parametreler
 * @param bool $analyze_security Güvenlik analizi yapılsın mı
 * @return PDOStatement|false
 */
function execute_safe_query(PDO $pdo, string $query, array $params = [], bool $analyze_security = true) {
    try {
        // Güvenlik analizi
        if ($analyze_security) {
            $analysis = analyze_query_security($query);
            if (!$analysis['is_safe']) {
                error_log("SECURITY RISK in query: " . implode(', ', $analysis['risks']));
                error_log("Query: " . $query);
                throw new SecurityException("Query contains security risks");
            }
        }
        
        // Statement hazırla
        $stmt = $pdo->prepare($query);
        if (!$stmt) {
            throw new DatabaseException("Failed to prepare statement");
        }
        
        // Parametreleri bind et ve validate et
        foreach ($params as $key => $value) {
            $param_type = PDO::PARAM_STR; // Varsayılan
            
            if (is_int($value)) {
                $param_type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $param_type = PDO::PARAM_BOOL;
                $value = $value ? 1 : 0;
            } elseif (is_null($value)) {
                $param_type = PDO::PARAM_NULL;
            } elseif (is_string($value)) {
                // String uzunluk kontrolü
                if (strlen($value) > 65535) { // TEXT limit
                    throw new InvalidArgumentException("Parameter value too long: $key");
                }
            }
            
            $stmt->bindValue($key, $value, $param_type);
        }
        
        // Execute
        $result = $stmt->execute();
        if (!$result) {
            throw new DatabaseException("Failed to execute statement");
        }
        
        return $stmt;
        
    } catch (PDOException $e) {
        error_log("Database error in execute_safe_query: " . $e->getMessage());
        error_log("Query: " . $query);
        error_log("Params: " . json_encode($params));
        throw new DatabaseException("Database operation failed");
    }
}

/**
 * Input validation - SQL injection pattern detection (Permission key'ler için özel)
 * @param string $input Kontrol edilecek input
 * @param string $type Input tipi ('general', 'permission_key', 'role_name')
 * @return bool Güvenliyse true
 */
function validate_sql_input(string $input, string $type = 'general'): bool {
    // Input uzunluk kontrolü
    if (strlen($input) > 500) { // Genel maksimum uzunluk
        return false;
    }
    
    switch ($type) {
        case 'permission_key':
            // Permission key'ler için: sadece harfler, rakamlar, nokta ve alt çizgi
            return preg_match('/^[a-zA-Z][a-zA-Z0-9._]{1,99}$/', $input) === 1;
            
        case 'role_name':
            // Rol adları için: sadece küçük harfler, rakamlar ve alt çizgi
            return preg_match('/^[a-z][a-z0-9_]{1,49}$/', $input) === 1;
            
        case 'action':
            // Action'lar için: sadece harfler ve alt çizgi
            return preg_match('/^[a-z_]{2,30}$/', $input) === 1;
            
        case 'general':
        default:
            // Genel güvenlik kontrolleri
            $dangerous_patterns = [
                '/(\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b)/i',
                '/(\b(or|and)\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?)/i', // 1=1, 1='1' etc.
                '/[\'";]/', // Quotes ve semicolon
                '/--/', // SQL comments
                '/\/\*.*?\*\//', // Block comments
                '/\bchar\s*\(/i', // CHAR function
                '/\bcast\s*\(/i', // CAST function
            ];
            
            foreach ($dangerous_patterns as $pattern) {
                if (preg_match($pattern, $input)) {
                    return false;
                }
            }
            
            return true;
    }
}

/**
 * Güvenli SELECT sorgusu oluşturur
 * @param string $table Tablo adı
 * @param array $columns Sütunlar
 * @param array $where WHERE koşulları
 * @param array $order_by ORDER BY koşulları
 * @param int|null $limit LIMIT değeri
 * @param int $offset OFFSET değeri
 * @return array ['query' => string, 'params' => array]
 */
function build_safe_select(
    string $table,
    array $columns = ['*'],
    array $where = [],
    array $order_by = [],
    ?int $limit = null,
    int $offset = 0
): array {
    // Tablo adı kontrolü
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $table)) {
        throw new InvalidArgumentException("Invalid table name: $table");
    }
    
    // Sütun adları kontrolü
    $safe_columns = [];
    foreach ($columns as $column) {
        if ($column === '*') {
            $safe_columns[] = '*';
        } elseif (preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $column)) {
            $safe_columns[] = "`$column`";
        } else {
            throw new InvalidArgumentException("Invalid column name: $column");
        }
    }
    
    $query = "SELECT " . implode(', ', $safe_columns) . " FROM `$table`";
    $params = [];
    
    // WHERE clause
    if (!empty($where)) {
        $where_conditions = [];
        foreach ($where as $column => $value) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $column)) {
                throw new InvalidArgumentException("Invalid WHERE column: $column");
            }
            $placeholder = ":where_$column";
            $where_conditions[] = "`$column` = $placeholder";
            $params[$placeholder] = $value;
        }
        $query .= " WHERE " . implode(' AND ', $where_conditions);
    }
    
    // ORDER BY clause
    if (!empty($order_by)) {
        $order_parts = [];
        foreach ($order_by as $column => $direction) {
            $order_parts[] = create_safe_order_by($column, $direction);
        }
        $query .= " ORDER BY " . implode(', ', $order_parts);
    }
    
    // LIMIT clause
    if ($limit !== null) {
        $query .= " " . create_safe_limit($limit, $offset);
    }
    
    return ['query' => $query, 'params' => $params];
}

/**
 * Güvenli JSON query builder (JSONB için)
 * @param string $column JSON sütun adı
 * @param string $path JSON path
 * @param mixed $value Aranacak değer
 * @return array ['condition' => string, 'params' => array]
 */
function build_safe_json_query(string $column, string $path, $value): array {
    // Column name validation
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $column)) {
        throw new InvalidArgumentException("Invalid JSON column name: $column");
    }
    
    // JSON path validation (basit path'ler için)
    if (!preg_match('/^\$(\.[a-zA-Z][a-zA-Z0-9_]*)*$/', $path)) {
        throw new InvalidArgumentException("Invalid JSON path: $path");
    }
    
    $placeholder = ':json_value_' . md5($column . $path);
    
    return [
        'condition' => "JSON_EXTRACT(`$column`, :json_path) = $placeholder",
        'params' => [
            ':json_path' => $path,
            $placeholder => $value
        ]
    ];
}

/**
 * Transaction wrapper ile güvenli batch operations
 * @param PDO $pdo Veritabanı bağlantısı
 * @param callable $operations İşlemler callback'i
 * @return mixed Callback'in dönüş değeri
 */
function execute_safe_transaction(PDO $pdo, callable $operations) {
    // Eğer zaten transaction içindeyse, yeni transaction başlatma
    if ($pdo->inTransaction()) {
        return $operations($pdo);
    }
    
    try {
        $pdo->beginTransaction();
        
        $result = $operations($pdo);
        
        $pdo->commit();
        return $result;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Transaction failed: " . $e->getMessage());
        throw $e;
    }
}

// Custom exception classes
class SecurityException extends Exception {}
class DatabaseException extends Exception {}

/**
 * Veritabanı bağlantısı için güvenli PDO ayarları
 * @param string $dsn DSN string
 * @param string $username Kullanıcı adı
 * @param string $password Şifre
 * @return PDO Güvenli PDO instance
 */
function create_secure_pdo(string $dsn, string $username, string $password): PDO {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Gerçek prepared statements
        PDO::ATTR_STRINGIFY_FETCHES => false, // Tip korunumu
        PDO::MYSQL_ATTR_FOUND_ROWS => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'"
    ];
    
    try {
        $pdo = new PDO($dsn, $username, $password, $options);
        
        // Ek güvenlik ayarları
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
        $pdo->exec("SET SESSION innodb_strict_mode = 1");
        
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Failed to create secure PDO connection: " . $e->getMessage());
        throw new DatabaseException("Database connection failed");
    }
}