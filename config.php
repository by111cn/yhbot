<?php
/**
 * 白屿云平台 BotAPI 管理系统 - 配置文件
 */

define('DEBUG_MODE', false);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
}

define('SITE_NAME', '白屿云平台 BotAPI 管理系统');
define('SITE_VERSION', '1.0.0');

$__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO']==='https') ? 'https' : 'http';
$__host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $__scheme.'://'.$__host);
$__isHttps = ($__scheme==='https');
unset($__scheme, $__host);

date_default_timezone_set('Asia/Shanghai');

if (session_status() === PHP_SESSION_NONE) {
    if (PHP_VERSION_ID>=70300) {
        session_set_cookie_params(['lifetime'=>86400*7,'path'=>'/','domain'=>'','secure'=>$__isHttps,'httponly'=>true,'samesite'=>'Lax']);
    }
    session_name('YHSESSION');
    session_start();
    unset($__isHttps);
}

class DB {
    private static $instance = null;
    private $pdo;
    private function __construct() {
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'yh_by111_cn';
        $user = getenv('DB_USER') ?: 'yh_by111_cn';
        $pass = getenv('DB_PASS') ?: 'AZ25DapYi5MsHFdS';
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false];
        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
            $this->pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            if (ini_get('display_errors')) die('数据库连接失败: '.$e->getMessage());
            die('数据库连接失败，请联系管理员。');
        }
    }
    public static function getInstance() {
        if (self::$instance===null) self::$instance=new self();
        return self::$instance;
    }
    public function getPDO() { return $this->pdo; }
    public function prepare($sql) { return $this->pdo->prepare($sql); }
    public function query($sql) { return $this->pdo->query($sql); }
    public function lastInsertId() { return $this->pdo->lastInsertId(); }
    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollBack() { return $this->pdo->rollBack(); }
}
function db() { return DB::getInstance(); }
