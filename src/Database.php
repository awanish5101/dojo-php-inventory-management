<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $driver = getenv('DB_DRIVER') ?: 'sqlite';
            
            if ($driver === 'mysql') {
                $host = getenv('DB_HOST') ?: '127.0.0.1';
                $port = getenv('DB_PORT') ?: '3306';
                $dbName = getenv('DB_NAME') ?: 'inventory_db';
                $user = getenv('DB_USER') ?: 'root';
                $pass = getenv('DB_PASS') ?: 'rootpassword';

                $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
                try {
                    self::$instance = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                } catch (PDOException $e) {
                    // Fall back to sqlite if MySQL connection is unavailable
                    error_log("MySQL connection failed: " . $e->getMessage() . ". Falling back to SQLite.");
                    self::$instance = self::createSqliteConnection();
                }
            } else {
                self::$instance = self::createSqliteConnection();
            }
        }

        return self::$instance;
    }

    private static function createSqliteConnection(): PDO {
        $dbPath = getenv('SQLITE_PATH') ?: __DIR__ . '/../database/inventory.sqlite';
        $isNew = !file_exists($dbPath);

        $pdo = new PDO("sqlite:{$dbPath}", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        if ($isNew) {
            self::initializeSchema($pdo);
        }

        return $pdo;
    }

    public static function initializeSchema(PDO $pdo): void {
        $schemaSql = <<<SQL
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            code TEXT NOT NULL UNIQUE,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS assets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            asset_tag TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            category_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 0,
            unit_price REAL NOT NULL DEFAULT 0.00,
            status TEXT NOT NULL DEFAULT 'In Stock',
            reorder_level INTEGER NOT NULL DEFAULT 5,
            location TEXT NOT NULL DEFAULT 'Main Warehouse',
            supplier TEXT NOT NULL DEFAULT 'Enterprise Supply Co.',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories (id)
        );

        CREATE TABLE IF NOT EXISTS inventory_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            asset_id INTEGER NOT NULL,
            transaction_type TEXT NOT NULL,
            quantity INTEGER NOT NULL,
            previous_quantity INTEGER NOT NULL,
            new_quantity INTEGER NOT NULL,
            reason TEXT NOT NULL,
            performed_by TEXT NOT NULL DEFAULT 'System Admin',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE
        );

        INSERT OR IGNORE INTO categories (id, name, code, description) VALUES
        (1, 'Computing & Laptops', 'COMP-01', 'Enterprise laptops, workstations, and portable computers'),
        (2, 'Servers & Datacenter', 'SRV-02', 'Rack servers, blade chassis, and datacenter hardware'),
        (3, 'Networking Equipment', 'NET-03', 'Switches, routers, enterprise firewalls, and access points'),
        (4, 'Peripherals & Displays', 'PER-04', 'Monitors, docking stations, and ergonomic input devices');

        INSERT OR IGNORE INTO assets (id, asset_tag, name, category_id, quantity, unit_price, status, reorder_level, location, supplier) VALUES
        (1, 'AST-1001', 'ThinkPad P16 Gen 2 Workstation', 1, 14, 2150.00, 'In Stock', 5, 'IT Depot A-01', 'Lenovo Enterprise'),
        (2, 'AST-1002', 'MacBook Pro 16 M3 Max', 1, 8, 3499.00, 'In Stock', 4, 'IT Depot A-02', 'Apple Direct'),
        (3, 'AST-1003', 'Dell PowerEdge R760 Server', 2, 3, 6800.00, 'Low Stock', 4, 'Datacenter Rack 4', 'Dell Technologies'),
        (4, 'AST-1004', 'Cisco Catalyst 9300 48-Port Switch', 3, 6, 4200.00, 'In Stock', 3, 'Network Room B', 'Cisco Systems'),
        (5, 'AST-1005', 'Fortinet FortiGate 200F Firewall', 3, 2, 5100.00, 'Low Stock', 3, 'Network Room A', 'Fortinet Global'),
        (6, 'AST-1006', 'Dell UltraSharp 32 4K Monitor', 4, 22, 650.00, 'In Stock', 8, 'Peripherals B-04', 'Dell Technologies'),
        (7, 'AST-1007', 'CalDigit TS4 Thunderbolt Dock', 4, 18, 399.00, 'In Stock', 6, 'Peripherals B-05', 'CalDigit Direct');

        INSERT OR IGNORE INTO inventory_transactions (id, asset_id, transaction_type, quantity, previous_quantity, new_quantity, reason, performed_by) VALUES
        (1, 1, 'INITIAL_STOCK', 14, 0, 14, 'Initial enterprise procurement intake', 'Awanish Chaurasiya'),
        (2, 2, 'INITIAL_STOCK', 10, 0, 10, 'Initial hardware inventory deployment', 'Awanish Chaurasiya'),
        (3, 2, 'CHECK_OUT', -2, 10, 8, 'Dispatched to Senior Engineering onboarding', 'Awanish Chaurasiya'),
        (4, 3, 'INITIAL_STOCK', 3, 0, 3, 'Datacenter hardware provisioning', 'Awanish Chaurasiya'),
        (5, 4, 'INITIAL_STOCK', 6, 0, 6, 'Core network infrastructure rollout', 'Awanish Chaurasiya'),
        (6, 5, 'INITIAL_STOCK', 2, 0, 2, 'Security boundary firewall installation', 'Awanish Chaurasiya');
SQL;
        $pdo->exec($schemaSql);
    }

    public static function setConnection(?PDO $pdo): void {
        self::$instance = $pdo;
    }
}
