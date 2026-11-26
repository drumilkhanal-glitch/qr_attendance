<?php
declare(strict_types=1);

class PhotoManager {
    private static array $config = [];
    
    public static function init(array $config): void {
        self::$config = $config;
    }
    
    /**
     * Save a base64-encoded photo
     * @return string|null Relative path to photo or null on failure
     */
    public static function savePhoto(string $photoData, int $sessionId, int $studentId): ?string {
        if (!self::validatePhotoData($photoData)) {
            return null;
        }
        
        // Validate and extract base64 data
        if (!preg_match('/^data:image\/(jpeg|png);base64,(.*)$/', $photoData, $matches)) {
            return null;
        }
        
        $photoType = $matches[1];
        $photoBlob = base64_decode($matches[2], true);
        
        if ($photoBlob === false) {
            return null;
        }
        
        // Validate file size
        $maxSize = (self::$config['max_photo_size_mb'] ?? 5) * 1024 * 1024;
        if (strlen($photoBlob) > $maxSize) {
            return null;
        }
        
        $photoDir = self::$config['photos_dir'] ?? __DIR__ . '/../../data/photos';
        
        // Create directory if not exists (secure permissions)
        if (!is_dir($photoDir)) {
            @mkdir($photoDir, 0755, true);
        }
        
        // Use random token instead of time() to prevent path prediction
        $randomToken = bin2hex(random_bytes(16));
        $photoFile = sprintf(
            'photo_%d_%d_%s.%s',
            $sessionId,
            $studentId,
            $randomToken,
            $photoType
        );
        
        $relativePath = 'photos/' . $photoFile;
        $fullPath = $photoDir . '/' . $photoFile;
        
        if (!file_put_contents($fullPath, $photoBlob)) {
            return null;
        }
        
        // Set secure file permissions (readable by web server, not world-writable)
        @chmod($fullPath, 0644);
        
        return $relativePath;
    }
    
    /**
     * Validate base64 photo data format and type
     */
    private static function validatePhotoData(string $photoData): bool {
        // Check format
        if (!preg_match('/^data:image\/(jpeg|png);base64,/', $photoData)) {
            return false;
        }
        
        // Validate that it's actual base64
        $matches = [];
        if (!preg_match('/^data:image\/\w+;base64,(.*)$/', $photoData, $matches)) {
            return false;
        }
        
        // Check if base64 is valid
        $decoded = base64_decode($matches[1], true);
        if ($decoded === false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Delete an old photo file
     */
    public static function deletePhoto(string $photoPath): bool {
        $photoDir = self::$config['photos_dir'] ?? __DIR__ . '/../../data/photos';
        $fullPath = $photoDir . '/' . basename($photoPath);
        
        // Security: prevent directory traversal
        if (realpath($fullPath) === false || strpos(realpath($fullPath), realpath($photoDir)) !== 0) {
            return false;
        }
        
        return @unlink($fullPath);
    }
    
    /**
     * Clean up old photos based on retention policy
     */
    public static function cleanupOldPhotos(int $retentionDays = 90): int {
        $photoDir = self::$config['photos_dir'] ?? __DIR__ . '/../../data/photos';
        
        if (!is_dir($photoDir)) {
            return 0;
        }
        
        $deletedCount = 0;
        $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
        
        $files = glob($photoDir . '/photo_*.{jpg,png}', GLOB_BRACE);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (@unlink($file)) {
                    $deletedCount++;
                }
            }
        }
        
        return $deletedCount;
    }
}
