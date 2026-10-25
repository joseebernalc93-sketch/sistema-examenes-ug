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
            return $this->rows[$this->currentIndex++];
        }
        return null;
    }

    public function fetch_all($mode = MYSQLI_NUM) {
        if ($mode === MYSQLI_ASSOC) {
            return $this->rows;
        }
        $result = [];
        foreach ($this->rows as $row) {
            $result[] = array_values($row);
        }
        return $result;
    }

    public function fetch_row() {
        $row = $this->fetch_assoc();
        return $row ? array_values($row) : null;
    }

    public function free() {
        $this->rows = [];
        $this->num_rows = 0;
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
            $this->insert_id = (int)$this->pdo->lastInsertId();
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
                $this->insert_id = (int)$this->pdo->lastInsertId();
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
