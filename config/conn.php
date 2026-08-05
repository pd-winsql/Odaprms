<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment-specific settings while keeping the current database as
// the safe default when the feature-database switch is missing or disabled.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

class Database {
    private $servername = "localhost";
    private $username = "root";
    private $password = "";
    private $dbname;

    public function __construct() {
        $useFeatureDatabase = filter_var(
            $_ENV['USE_FEATURE_DATABASE'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $this->dbname = $useFeatureDatabase
            ? ($_ENV['FEATURE_DATABASE_NAME'] ?? 'av-clinica-dental-feature')
            : ($_ENV['CURRENT_DATABASE_NAME'] ?? 'av-clinica-dental');
    }

    public function connect() {
        try {
            $conn = new PDO("mysql:host=" . $this->servername . ";dbname=" . $this->dbname, $this->username, $this->password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            return null;
        }
    }
}
