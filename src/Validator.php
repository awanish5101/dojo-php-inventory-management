<?php
declare(strict_types=1);

namespace App;

use InvalidArgumentException;

class Validator {
    public static function validateAsset(array $data, bool $isUpdate = false): array {
        $errors = [];

        if (!$isUpdate || array_key_exists('asset_tag', $data)) {
            $tag = trim($data['asset_tag'] ?? '');
            if (empty($tag)) {
                $errors['asset_tag'] = 'Asset tag is required.';
            } elseif (!preg_match('/^[A-Z0-9\-_]{3,30}$/i', $tag)) {
                $errors['asset_tag'] = 'Asset tag must be 3-30 alphanumeric characters with hyphens or underscores.';
            }
        }

        if (!$isUpdate || array_key_exists('name', $data)) {
            $name = trim($data['name'] ?? '');
            if (empty($name)) {
                $errors['name'] = 'Asset name is required.';
            } elseif (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
                $errors['name'] = 'Asset name must be between 3 and 150 characters.';
            }
        }

        if (!$isUpdate || array_key_exists('category_id', $data)) {
            $categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
            if ($categoryId === false || $categoryId <= 0) {
                $errors['category_id'] = 'Valid category ID is required.';
            }
        }

        if (!$isUpdate || array_key_exists('quantity', $data)) {
            $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 0) {
                $errors['quantity'] = 'Quantity must be a non-negative integer.';
            }
        }

        if (!$isUpdate || array_key_exists('unit_price', $data)) {
            $price = filter_var($data['unit_price'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($price === false || $price < 0.0) {
                $errors['unit_price'] = 'Unit price must be a non-negative number.';
            }
        }

        if (!$isUpdate || array_key_exists('reorder_level', $data)) {
            $reorder = filter_var($data['reorder_level'] ?? 5, FILTER_VALIDATE_INT);
            if ($reorder === false || $reorder < 0) {
                $errors['reorder_level'] = 'Reorder level must be a non-negative integer.';
            }
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode(['validation_errors' => $errors]));
        }

        // Return sanitized fields
        $sanitized = [];
        if (isset($data['asset_tag'])) $sanitized['asset_tag'] = htmlspecialchars(trim($data['asset_tag']), ENT_QUOTES, 'UTF-8');
        if (isset($data['name'])) $sanitized['name'] = htmlspecialchars(trim($data['name']), ENT_QUOTES, 'UTF-8');
        if (isset($data['category_id'])) $sanitized['category_id'] = (int)$data['category_id'];
        if (isset($data['quantity'])) $sanitized['quantity'] = (int)$data['quantity'];
        if (isset($data['unit_price'])) $sanitized['unit_price'] = round((float)$data['unit_price'], 2);
        if (isset($data['reorder_level'])) $sanitized['reorder_level'] = (int)$data['reorder_level'];
        if (isset($data['location'])) $sanitized['location'] = htmlspecialchars(trim($data['location']), ENT_QUOTES, 'UTF-8');
        if (isset($data['supplier'])) $sanitized['supplier'] = htmlspecialchars(trim($data['supplier']), ENT_QUOTES, 'UTF-8');
        if (isset($data['status'])) $sanitized['status'] = htmlspecialchars(trim($data['status']), ENT_QUOTES, 'UTF-8');

        return $sanitized;
    }

    public static function validateAdjustment(array $data): array {
        $errors = [];
        $adjustment = filter_var($data['adjustment'] ?? null, FILTER_VALIDATE_INT);
        if ($adjustment === false || $adjustment === 0) {
            $errors['adjustment'] = 'Adjustment quantity must be a non-zero integer.';
        }

        $reason = trim($data['reason'] ?? '');
        if (empty($reason)) {
            $errors['reason'] = 'Reason for stock adjustment is required.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode(['validation_errors' => $errors]));
        }

        return [
            'adjustment' => $adjustment,
            'reason' => htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'),
            'performed_by' => htmlspecialchars(trim($data['performed_by'] ?? 'Staff Engineer'), ENT_QUOTES, 'UTF-8'),
        ];
    }
}
