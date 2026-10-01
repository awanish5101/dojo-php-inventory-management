<?php
declare(strict_types=1);

namespace App;

use PDO;
use Exception;
use RuntimeException;
use InvalidArgumentException;

class AssetService {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getAllAssets(?string $category = null, ?string $status = null, ?string $search = null): array {
        $sql = "SELECT a.*, c.name AS category_name, c.code AS category_code 
                FROM assets a 
                JOIN categories c ON a.category_id = c.id 
                WHERE 1=1";
        $params = [];

        if (!empty($category)) {
            $sql .= " AND (c.code = :category_code OR c.name = :category_name)";
            $params[':category_code'] = $category;
            $params[':category_name'] = $category;
        }

        if (!empty($status)) {
            $sql .= " AND a.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($search)) {
            $sql .= " AND (a.name LIKE :search OR a.asset_tag LIKE :search_tag OR a.location LIKE :search_loc)";
            $params[':search'] = "%{$search}%";
            $params[':search_tag'] = "%{$search}%";
            $params[':search_loc'] = "%{$search}%";
        }

        $sql .= " ORDER BY a.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAssetById(int $id): ?array {
        $sql = "SELECT a.*, c.name AS category_name, c.code AS category_code 
                FROM assets a 
                JOIN categories c ON a.category_id = c.id 
                WHERE a.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $asset = $stmt->fetch();

        if (!$asset) {
            return null;
        }

        // Fetch transactions history
        $txSql = "SELECT * FROM inventory_transactions WHERE asset_id = :asset_id ORDER BY id DESC LIMIT 20";
        $txStmt = $this->db->prepare($txSql);
        $txStmt->execute([':asset_id' => $id]);
        $asset['transactions'] = $txStmt->fetchAll();

        return $asset;
    }

    public function createAsset(array $data): array {
        $validated = Validator::validateAsset($data, false);

        // Check unique asset tag
        $chk = $this->db->prepare("SELECT id FROM assets WHERE asset_tag = :tag");
        $chk->execute([':tag' => $validated['asset_tag']]);
        if ($chk->fetch()) {
            throw new InvalidArgumentException(json_encode(['error' => "Asset tag '{$validated['asset_tag']}' already exists."]));
        }

        // Determine initial status based on stock
        $status = $validated['status'] ?? ($validated['quantity'] <= $validated['reorder_level'] ? 'Low Stock' : 'In Stock');
        if ($validated['quantity'] === 0) {
            $status = 'Out of Stock';
        }

        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO assets (asset_tag, name, category_id, quantity, unit_price, status, reorder_level, location, supplier)
                    VALUES (:asset_tag, :name, :category_id, :quantity, :unit_price, :status, :reorder_level, :location, :supplier)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':asset_tag' => $validated['asset_tag'],
                ':name' => $validated['name'],
                ':category_id' => $validated['category_id'],
                ':quantity' => $validated['quantity'],
                ':unit_price' => $validated['unit_price'],
                ':status' => $status,
                ':reorder_level' => $validated['reorder_level'],
                ':location' => $validated['location'] ?? 'Main Warehouse',
                ':supplier' => $validated['supplier'] ?? 'Enterprise Supply Co.',
            ]);

            $newId = (int)$this->db->lastInsertId();

            // Record initial transaction
            $txSql = "INSERT INTO inventory_transactions (asset_id, transaction_type, quantity, previous_quantity, new_quantity, reason, performed_by)
                      VALUES (:asset_id, 'INITIAL_STOCK', :qty, 0, :qty, 'Asset registered into enterprise inventory', 'System Admin')";
            $txStmt = $this->db->prepare($txSql);
            $txStmt->execute([
                ':asset_id' => $newId,
                ':qty' => $validated['quantity']
            ]);

            $this->db->commit();
            return $this->getAssetById($newId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateAsset(int $id, array $data): array {
        $existing = $this->getAssetById($id);
        if (!$existing) {
            throw new RuntimeException("Asset not found.");
        }

        $validated = Validator::validateAsset($data, true);

        // Check if updating asset_tag and not duplicate
        if (isset($validated['asset_tag']) && $validated['asset_tag'] !== $existing['asset_tag']) {
            $chk = $this->db->prepare("SELECT id FROM assets WHERE asset_tag = :tag AND id != :id");
            $chk->execute([':tag' => $validated['asset_tag'], ':id' => $id]);
            if ($chk->fetch()) {
                throw new InvalidArgumentException(json_encode(['error' => "Asset tag '{$validated['asset_tag']}' is already in use."]));
            }
        }

        $fields = [];
        $params = [':id' => $id];
        foreach ($validated as $key => $val) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $val;
        }

        if (empty($fields)) {
            return $existing;
        }

        $sql = "UPDATE assets SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->getAssetById($id);
    }

    public function adjustStock(int $id, array $data): array {
        $adjustmentData = Validator::validateAdjustment($data);
        $adjustment = $adjustmentData['adjustment'];
        $reason = $adjustmentData['reason'];
        $performedBy = $adjustmentData['performed_by'];

        $this->db->beginTransaction();
        try {
            $asset = $this->getAssetById($id);
            if (!$asset) {
                throw new RuntimeException("Asset not found.");
            }

            $currentQty = (int)$asset['quantity'];
            $newQty = $currentQty + $adjustment;

            if ($newQty < 0) {
                throw new InvalidArgumentException(json_encode(['error' => "Insufficient stock. Current: {$currentQty}, requested reduction: " . abs($adjustment)]));
            }

            $reorderLevel = (int)$asset['reorder_level'];
            $newStatus = 'In Stock';
            if ($newQty === 0) {
                $newStatus = 'Out of Stock';
            } elseif ($newQty <= $reorderLevel) {
                $newStatus = 'Low Stock';
            }

            // Update asset
            $upSql = "UPDATE assets SET quantity = :quantity, status = :status WHERE id = :id";
            $upStmt = $this->db->prepare($upSql);
            $upStmt->execute([
                ':quantity' => $newQty,
                ':status' => $newStatus,
                ':id' => $id
            ]);

            // Insert transaction
            $type = $adjustment > 0 ? 'CHECK_IN' : 'CHECK_OUT';
            $txSql = "INSERT INTO inventory_transactions (asset_id, transaction_type, quantity, previous_quantity, new_quantity, reason, performed_by)
                      VALUES (:asset_id, :type, :qty, :prev_qty, :new_qty, :reason, :performed_by)";
            $txStmt = $this->db->prepare($txSql);
            $txStmt->execute([
                ':asset_id' => $id,
                ':type' => $type,
                ':qty' => $adjustment,
                ':prev_qty' => $currentQty,
                ':new_qty' => $newQty,
                ':reason' => $reason,
                ':performed_by' => $performedBy,
            ]);

            $this->db->commit();
            return $this->getAssetById($id);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteAsset(int $id): bool {
        $asset = $this->getAssetById($id);
        if (!$asset) {
            throw new RuntimeException("Asset not found.");
        }

        $this->db->beginTransaction();
        try {
            // Delete transactions first (or cascade)
            $delTx = $this->db->prepare("DELETE FROM inventory_transactions WHERE asset_id = :id");
            $delTx->execute([':id' => $id]);

            $stmt = $this->db->prepare("DELETE FROM assets WHERE id = :id");
            $result = $stmt->execute([':id' => $id]);

            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getCategories(): array {
        $stmt = $this->db->query("SELECT * FROM categories ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function getStatistics(): array {
        $totalAssets = (int)$this->db->query("SELECT COUNT(*) FROM assets")->fetchColumn();
        $totalItems = (int)$this->db->query("SELECT COALESCE(SUM(quantity), 0) FROM assets")->fetchColumn();
        $totalValue = (float)$this->db->query("SELECT COALESCE(SUM(quantity * unit_price), 0.0) FROM assets")->fetchColumn();
        $lowStockCount = (int)$this->db->query("SELECT COUNT(*) FROM assets WHERE status = 'Low Stock' OR status = 'Out of Stock'")->fetchColumn();

        $recentTx = $this->db->query("
            SELECT t.*, a.name AS asset_name, a.asset_tag 
            FROM inventory_transactions t 
            JOIN assets a ON t.asset_id = a.id 
            ORDER BY t.id DESC 
            LIMIT 5
        ")->fetchAll();

        return [
            'total_asset_types' => $totalAssets,
            'total_inventory_items' => $totalItems,
            'total_valuation' => round($totalValue, 2),
            'low_stock_alerts' => $lowStockCount,
            'recent_transactions' => $recentTx
        ];
    }
}
