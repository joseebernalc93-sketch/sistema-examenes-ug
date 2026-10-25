<?php
// db_supabase_adapter.php — Adaptador de compatibilidad MySQLi sobre PostgreSQL PDO para Supabase
// Permite que todo el código existente basado en $conn->query() / $conn->prepare() funcione con Supabase sin cambios.

if (!defined('MYSQLI_ASSOC')) {
    define('MYSQLI_ASSOC', 1);
}
if (!defined('MYSQLI_NUM')) {
    define('MYSQLI_NUM', 2);
}
if (!defined('MYSQLI_BOTH')) {
    define('MYSQLI_BOTH', 3);
}

class SupabaseResult {
    private $rows = [];
    private $currentIndex = 0;
    public $num_rows = 0;

    public function __construct(PDOStatement $stmt) {
        $this->rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc() {
        if ($this->currentIndex < $this->num_rows) {
            $row = $this->rows[$this->currentIndex++];
            foreach ($row as $k => $v) {
                if (is_resource($v)) {
                    $row[$k] = stream_get_contents($v);
                } elseif (is_string($v) && strpos($v, '\\x') === 0 && strlen($v) % 2 === 0 && ctype_xdigit(substr($v, 2))) {
                    $row[$k] = hex2bin(substr($v, 2));
                }
            }
            return $row;
        }
        return null;
    }

    public function fetch_all($mode = MYSQLI_NUM) {
        if ($mode === MYSQLI_ASSOC) {
            $res = [];
            $this->data_seek(0);
            while ($r = $this->fetch_assoc()) {
                $res[] = $r;
            }
            return $res;
        }
        $result = [];
        $this->data_seek(0);
        while ($r = $this->fetch_assoc()) {
            $result[] = array_values($r);
        }
        return $result;
    }

    public function fetch_row() {
        $row = $this->fetch_assoc();
        return $row ? array_values($row) : null;
    }

    public function data_seek($offset = 0) {
        $offset = (int)$offset;
        if ($offset >= 0 && $offset <= $this->num_rows) {
            $this->currentIndex = $offset;
            return true;
        }
        return false;
    }

    public function fetch_array($mode = MYSQLI_BOTH) {
        $row = $this->fetch_assoc();
        if (!$row) return null;
        if ($mode === MYSQLI_ASSOC) return $row;
        if ($mode === MYSQLI_NUM) return array_values($row);
        $both = $row;
        $i = 0;
        foreach ($row as $v) {
            $both[$i++] = $v;
        }
        return $both;
    }

    public function fetch_object($class_name = 'stdClass', $params = []) {
        $row = $this->fetch_assoc();
        if (!$row) return null;
        $obj = new $class_name(...$params);
        foreach ($row as $k => $v) {
            $obj->$k = $v;
        }
        return $obj;
    }

    public function free() {
        $this->rows = [];
        $this->num_rows = 0;
    }

    public function close() {
        $this->free();
    }
}

class SupabaseStmt {
    private $pdo;
    private $stmt;
    private $params = [];
    public $insert_id = 0;
    public $affected_rows = 0;
    public $error = '';

    public function __construct(PDO $pdo, PDOStatement $stmt) {
        $this->pdo = $pdo;
        $this->stmt = $stmt;
    }

    public function bind_param($types, &...$args) {
        $this->params = [];
        foreach ($args as $i => &$arg) {
            $this->params[$i + 1] = &$arg;
        }
    }

    public function execute() {
        try {
            foreach ($this->params as $idx => $val) {
                $type = PDO::PARAM_STR;
                if (is_int($val)) $type = PDO::PARAM_INT;
                elseif (is_bool($val)) $type = PDO::PARAM_BOOL;
                elseif (is_null($val)) $type = PDO::PARAM_NULL;
                $this->stmt->bindValue($idx, $val, $type);
            }
            $success = $this->stmt->execute();
            $this->affected_rows = $this->stmt->rowCount();
            $this->insert_id = 0;
            try {
                $this->insert_id = (int)$this->pdo->lastInsertId();
            } catch (Throwable $e) {
                $this->insert_id = 0;
            }
            return $success;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            error_log('STMT EXECUTE ERROR: ' . $this->error);
            return false;
        }
    }

    public function get_result() {
        return new SupabaseResult($this->stmt);
    }

    public function close() {
        $this->stmt = null;
        return true;
    }
}

class SupabasePdoAdapter {
    public $pdo;
    public $insert_id = 0;
    public $affected_rows = 0;
    public $error = '';

    public function __construct($host, $user, $pass, $name, $port = 5432) {
        $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode=require";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    public function translate_query($sql) {
        // Quitar comillas invertidas (backticks) de MySQL
        $sql = str_replace('`', '', $sql);

        // Traducir INSERT IGNORE INTO -> INSERT INTO ... ON CONFLICT DO NOTHING
        if (preg_match('/^INSERT\s+IGNORE\s+INTO\s+(.*)$/is', $sql, $m)) {
            $sql = "INSERT INTO " . $m[1];
            if (stripos($sql, 'ON CONFLICT') === false) {
                $sql = rtrim($sql, '; ') . " ON CONFLICT DO NOTHING";
            }
        }

        // Traducir SHOW TABLES LIKE 'xyz'
        if (preg_match('/SHOW\s+TABLES\s+LIKE\s+[\'"](.*?)[\'"]/i', $sql, $m)) {
            $tbl = str_replace(['%', '_'], ['.*', '.'], $m[1]);
            return "SELECT table_name AS Tables_in_db FROM information_schema.tables WHERE table_schema = 'public' AND table_name ~* '^{$tbl}$'";
        }

        // Traducir SHOW COLUMNS FROM tabla LIKE 'col'
        if (preg_match('/SHOW\s+COLUMNS\s+FROM\s+(\w+)\s+LIKE\s+[\'"](.*?)[\'"]/i', $sql, $m)) {
            $tbl = $m[1];
            $col = $m[2];
            return "SELECT column_name AS \"Field\", data_type AS \"Type\", is_nullable AS \"Null\" FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '{$tbl}' AND column_name = '{$col}'";
        }

        // Traducir SUBSTRING_INDEX(str, delim, count) -> split_part(str, delim, count)
        $sql = preg_replace('/SUBSTRING_INDEX\s*\(\s*(.*?)\s*,\s*(.*?)\s*,\s*(\d+)\s*\)/i', 'split_part($1, $2, $3)', $sql);

        // Traducir GROUP_CONCAT(...) -> string_agg(...)
        if (stripos($sql, 'GROUP_CONCAT') !== false) {
            $sql = preg_replace_callback('/GROUP_CONCAT\s*\(\s*(DISTINCT\s+)?(.*?)(?:\s+ORDER\s+BY\s+[^\)\'\"]+?)?(?:\s+SEPARATOR\s+([\'"].*?[\'"]))?\s*\)/is', function($m) {
                $distinct = !empty($m[1]) ? $m[1] : '';
                $expr = trim($m[2]);
                $sep = !empty($m[3]) ? $m[3] : "', '";
                if ($sep === "'\\n'" || $sep === '"\\n"') {
                    $sep = "E'\\n'";
                }
                return "string_agg({$distinct}({$expr})::text, {$sep})";
            }, $sql);
        }

        // Traducir IFNULL(a, b) -> COALESCE(a, b)
        $sql = preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $sql);

        // Corregir operadores seguidos directamente de números negativos (ej. !=-1 -> != -1) para evitar operador inválido en PG
        $sql = preg_replace('/([!<>]=?|=)(-[\d\.]+)/', '$1 $2', $sql);

        // Traducir FIELD(col, a, b, c) -> array_position(ARRAY[a, b, c], col)
        $sql = preg_replace('/FIELD\s*\(\s*(\w+)\s*,\s*(.*?)\s*\)/i', 'array_position(ARRAY[$2], $1)', $sql);

        // Traducir RAND() -> RANDOM()
        $sql = preg_replace('/\bRAND\s*\(\s*\)/i', 'RANDOM()', $sql);

        // Traducir INTERVAL X MINUTE/DAY/HOUR/SECOND -> INTERVAL 'X MINUTE'
        $sql = preg_replace('/\bINTERVAL\s+(\d+)\s+([a-zA-Z]+)/i', "INTERVAL '$1 $2'", $sql);

        // Eliminar 'AFTER columna' de ALTER TABLE (no soportado en PG)
        $sql = preg_replace('/\s+AFTER\s+[`"\w]+/i', '', $sql);

        // Traducir ON DUPLICATE KEY UPDATE para areas_materias (UK nombre_materia)
        if (stripos($sql, 'areas_materias') !== false && stripos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
            $sql = preg_replace('/ON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.*)$/i', 'ON CONFLICT (nombre_materia) DO UPDATE SET $1', $sql);
        }

        // Traducir ON DUPLICATE KEY UPDATE para usuarios (UK cedula)
        if (stripos($sql, 'usuarios') !== false && stripos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
            $sql = preg_replace('/ON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.*)$/i', 'ON CONFLICT (cedula) DO UPDATE SET $1', $sql);
        }

        return $sql;
    }

    public function query($sql) {
        $translated = $this->translate_query($sql);
        try {
            $trimmed = trim(strtoupper($translated));
            if (strpos($trimmed, 'SELECT') === 0 || strpos($trimmed, 'SHOW') === 0 || strpos($trimmed, 'WITH') === 0) {
                $stmt = $this->pdo->query($translated);
                return new SupabaseResult($stmt);
            } else {
                $this->affected_rows = $this->pdo->exec($translated);
                $this->insert_id = 0;
                if (strpos($trimmed, 'INSERT') === 0) {
                    try {
                        $this->insert_id = (int)$this->pdo->lastInsertId();
                    } catch (Throwable $e) {
                        $this->insert_id = 0;
                    }
                }
                return true;
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            error_log("PG QUERY ERROR: {$this->error} | SQL: {$translated}");
            return false;
        }
    }

    public function prepare($sql) {
        $translated = $this->translate_query($sql);
        try {
            $stmt = $this->pdo->prepare($translated);
            return new SupabaseStmt($this->pdo, $stmt);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            error_log("PG PREPARE ERROR: {$this->error} | SQL: {$translated}");
            return false;
        }
    }

    public function real_escape_string($str) {
        if ($str === null) return '';
        // Si el string contiene bytes nulos o no es UTF-8 valido, es un archivo binario (BYTEA)
        if (strpos($str, "\0") !== false || !mb_check_encoding($str, 'UTF-8')) {
            return '\\x' . bin2hex($str);
        }
        $quoted = $this->pdo->quote($str);
        return substr($quoted, 1, -1);
    }

    public function escape_string($str) {
        return $this->real_escape_string($str);
    }

    public function set_charset($charset) {
        return true;
    }

    public function begin_transaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollBack();
    }
}
