<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment-specific settings.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Manila');

class Database {
    private $servername = "localhost";
    private $username = "root";
    private $password = "";
    private $dbname;

    public function __construct() {
        $this->dbname = $_ENV['DATABASE_NAME'] ?? 'db-oaprms-system';
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
