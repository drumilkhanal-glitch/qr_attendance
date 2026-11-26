<?php
declare(strict_types=1);

/**
 * Photo Cleanup Cron Job
 * 
 * Run this script daily to clean up old photos:
 * 0 2 * * * /usr/bin/php /var/www/html/tasks/cleanup_photos.php
 */

require_once __DIR__ . '/../lib/photo_manager.php';
require_once __DIR__ . '/../config.php';

$config = require __DIR__ . '/../config.php';

// Initialize PhotoManager
PhotoManager::init($config['storage']);

// Get retention days from config
$retentionDays = $config['storage']['photo_retention_days'] ?? 90;

// Clean up old photos
$deletedCount = PhotoManager::cleanupOldPhotos($retentionDays);

echo "Cleaned up $deletedCount old photo(s) (retention: $retentionDays days)" . PHP_EOL;

// Log to activity log if database is available
try {
    require_once __DIR__ . '/../lib/db.php';
    $pdo = db();
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (user_type, user_id, action, description, ip_address)
        VALUES ('admin', NULL, 'photo_cleanup', 'Deleted ? old photos', 'cron')
    ");
    $stmt->execute([$deletedCount]);
} catch (Exception $e) {
    echo "Database logging failed: " . $e->getMessage() . PHP_EOL;
}
