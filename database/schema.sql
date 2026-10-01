-- ============================================================================
-- Enterprise Asset & Inventory Management Database Schema
-- Compatible with MySQL 8.0+ and SQLite (for portable testing)
-- ============================================================================

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS assets (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    asset_tag VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 0,
    unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status VARCHAR(30) NOT NULL DEFAULT 'In Stock',
    reorder_level INTEGER NOT NULL DEFAULT 5,
    location VARCHAR(100) NOT NULL DEFAULT 'Main Warehouse',
    supplier VARCHAR(150) NOT NULL DEFAULT 'Enterprise Supply Co.',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_asset_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    asset_id INTEGER NOT NULL,
    transaction_type VARCHAR(20) NOT NULL, -- 'CHECK_IN', 'CHECK_OUT', 'ADJUSTMENT', 'INITIAL_STOCK'
    quantity INTEGER NOT NULL,
    previous_quantity INTEGER NOT NULL,
    new_quantity INTEGER NOT NULL,
    reason VARCHAR(255) NOT NULL,
    performed_by VARCHAR(100) NOT NULL DEFAULT 'System Admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE
);

-- Optimization Indexes
CREATE INDEX idx_assets_category ON assets (category_id);
CREATE INDEX idx_assets_status ON assets (status);
CREATE INDEX idx_assets_quantity ON assets (quantity);
CREATE INDEX idx_tx_asset_id ON inventory_transactions (asset_id);
CREATE INDEX idx_tx_created_at ON inventory_transactions (created_at);

-- Seed Initial Categories
INSERT INTO categories (name, code, description) VALUES
('Computing & Laptops', 'COMP-01', 'Enterprise laptops, workstations, and portable computers'),
('Servers & Datacenter', 'SRV-02', 'Rack servers, blade chassis, and datacenter hardware'),
('Networking Equipment', 'NET-03', 'Switches, routers, enterprise firewalls, and access points'),
('Peripherals & Displays', 'PER-04', 'Monitors, docking stations, and ergonomic input devices');

-- Seed Initial Assets
INSERT INTO assets (asset_tag, name, category_id, quantity, unit_price, status, reorder_level, location, supplier) VALUES
('AST-1001', 'ThinkPad P16 Gen 2 Workstation', 1, 14, 2150.00, 'In Stock', 5, 'IT Depot A-01', 'Lenovo Enterprise'),
('AST-1002', 'MacBook Pro 16 M3 Max', 1, 8, 3499.00, 'In Stock', 4, 'IT Depot A-02', 'Apple Direct'),
('AST-1003', 'Dell PowerEdge R760 Server', 2, 3, 6800.00, 'Low Stock', 4, 'Datacenter Rack 4', 'Dell Technologies'),
('AST-1004', 'Cisco Catalyst 9300 48-Port Switch', 3, 6, 4200.00, 'In Stock', 3, 'Network Room B', 'Cisco Systems'),
('AST-1005', 'Fortinet FortiGate 200F Firewall', 3, 2, 5100.00, 'Low Stock', 3, 'Network Room A', 'Fortinet Global'),
('AST-1006', 'Dell UltraSharp 32 4K Monitor', 4, 22, 650.00, 'In Stock', 8, 'Peripherals B-04', 'Dell Technologies'),
('AST-1007', 'CalDigit TS4 Thunderbolt Dock', 4, 18, 399.00, 'In Stock', 6, 'Peripherals B-05', 'CalDigit Direct');

-- Seed Initial Transaction Logs
INSERT INTO inventory_transactions (asset_id, transaction_type, quantity, previous_quantity, new_quantity, reason, performed_by) VALUES
(1, 'INITIAL_STOCK', 14, 0, 14, 'Initial enterprise procurement intake', 'Awanish Chaurasiya'),
(2, 'INITIAL_STOCK', 10, 0, 10, 'Initial hardware inventory deployment', 'Awanish Chaurasiya'),
(2, 'CHECK_OUT', -2, 10, 8, 'Dispatched to Senior Engineering onboarding', 'Awanish Chaurasiya'),
(3, 'INITIAL_STOCK', 3, 0, 3, 'Datacenter hardware provisioning', 'Awanish Chaurasiya'),
(4, 'INITIAL_STOCK', 6, 0, 6, 'Core network infrastructure rollout', 'Awanish Chaurasiya'),
(5, 'INITIAL_STOCK', 2, 0, 2, 'Security boundary firewall installation', 'Awanish Chaurasiya');
