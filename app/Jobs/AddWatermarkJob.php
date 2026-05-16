<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Services\CachingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class AddWatermarkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $imagePath;
    public string $extension;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 300; // 5 minutes

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 60; // Wait 60 seconds before retry

    /**
     * Create a new job instance.
     *
     * @param string $imagePath
     * @param string $extension
     */
    public function __construct(string $imagePath, string $extension)
    {
        $this->imagePath = $imagePath;
        $this->extension = $extension;
    }

    public function handle(): void
    {
        try {
            // Log::info('AddWatermarkJob started', [
            //     'imagePath' => $this->imagePath,
            //     'extension' => $this->extension,
            //     'file_exists' => file_exists($this->imagePath)
            // ]);

            $startTime = microtime(true);

            // Get all settings from cache
            $settings = CachingService::getSystemSettings()->toArray();

            // Check if watermark is enabled
            $watermarkEnabled = isset($settings['watermark_enabled']) && (int)$settings['watermark_enabled'] === 1;
            if (!$watermarkEnabled) {
                // Log::info('Watermark is disabled, skipping watermark job');
                return;
            }
            
            // Log::info('Watermark is enabled, processing job');
            
            // Helper function to extract relative path from URL or return path as is
            $extractPathFromUrl = function($pathOrUrl) {
                if (empty($pathOrUrl)) {
                    return null;
                }
                
                // If it's a URL, extract the path after /storage/
                if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
                    $parsedUrl = parse_url($pathOrUrl);
                    $path = $parsedUrl['path'] ?? '';
                    
                    // Extract path after /storage/
                    if (preg_match('#/storage/(.+)$#', $path, $matches)) {
                        return $matches[1];
                    }
                    
                    // If /storage/ not found, try to extract from path
                    if (preg_match('#/([^/]+/.+)$#', $path, $matches)) {
                        return $matches[1];
                    }
                }
                
                // Return as is if it's already a relative path
                return $pathOrUrl;
            };
            
            // Get watermark image path
            $watermarkImage = $settings['watermark_image'] ?? null;
            $watermarkPath = null;
            $disk = config('filesystems.default');

            if (!empty($watermarkImage)) {
                // Extract relative path from URL if needed
                $watermarkRelativePath = $extractPathFromUrl($watermarkImage);
                
                
                // Try to get absolute path from storage
                if ($watermarkRelativePath && Storage::disk($disk)->exists($watermarkRelativePath)) {
                    $watermarkPath = Storage::disk($disk)->path($watermarkRelativePath);
                    // Log::info('Watermark found in storage', ['path' => $watermarkPath]);
                } else {
                    // Try direct path if it's a file path
                    if (file_exists($watermarkImage)) {
                        $watermarkPath = $watermarkImage;
                        // Log::info('Watermark found as direct path', ['path' => $watermarkPath]);
                    } else {
                        // Fallback to public path
                        $watermarkPath = public_path('assets/images/logo/' . basename($watermarkImage));
                        // Log::info('Trying public path fallback', ['path' => $watermarkPath]);
                    }
                }
            }

            // If watermark image not found, try company logo
            if (empty($watermarkPath) || !file_exists($watermarkPath)) {
                // Log::info('Watermark image not found, trying company logo');
                $companyLogo = $settings['company_logo'] ?? null;
                
                if (!empty($companyLogo)) {
                    // Extract relative path from URL if needed
                    $companyLogoRelativePath = $extractPathFromUrl($companyLogo);
                    
                    if ($companyLogoRelativePath && Storage::disk($disk)->exists($companyLogoRelativePath)) {
                        $watermarkPath = Storage::disk($disk)->path($companyLogoRelativePath);
                        // Log::info('Company logo found in storage', ['path' => $watermarkPath]);
                    } elseif (file_exists($companyLogo)) {
                        $watermarkPath = $companyLogo;
                        // Log::info('Company logo found as direct path', ['path' => $watermarkPath]);
                    } else {
                        $watermarkPath = public_path('assets/images/logo/' . basename($companyLogo));
                        // Log::info('Trying company logo public path', ['path' => $watermarkPath]);
                    }
                } else {
                    $watermarkPath = public_path('assets/images/logo/logo.png');
                    // Log::info('Using default logo path', ['path' => $watermarkPath]);
                }
            }

            if (!file_exists($watermarkPath)) {
                return;
            }
            // Get watermark settings with validation
            $opacity = isset($settings['watermark_opacity']) ? (int)$settings['watermark_opacity'] : 25;
            $size = isset($settings['watermark_size']) ? (int)$settings['watermark_size'] : 10;
            $style = $settings['watermark_style'] ?? 'tile';
            $position = $settings['watermark_position'] ?? 'center';
            $rotation = isset($settings['watermark_rotation']) ? (int)$settings['watermark_rotation'] : 0;
            
            // Validate and clamp values to safe ranges
            $opacity = max(0, min(100, $opacity)); // Clamp between 0-100
            $size = max(1, min(100, $size)); // Clamp between 1-100
            $rotation = max(-360, min(360, $rotation)); // Clamp between -360 to 360
            
            // Normalize rotation to -180 to 180 range for efficiency
            while ($rotation > 180) $rotation -= 360;
            while ($rotation < -180) $rotation += 360;
            
            // Validate style
            if (!in_array($style, ['tile', 'single', 'center'])) {
                $style = 'tile';
            }
            
            // Validate position
            if (!in_array($position, ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'])) {
                $position = 'center';
            }
            
            // If style is 'center', force position to center
            if ($style === 'center') {
                $position = 'center';
            }
        
            
            // Intervention/Image rotates in the opposite direction compared to our UI/CSS preview.
            // To keep backend output consistent with the admin preview, invert the rotation sign.
            $appliedRotation = -$rotation;

            // Load image
            if (!file_exists($this->imagePath)) {
                return;
            }
            
            $image = Image::make($this->imagePath);
            $originalWidth = $image->width();
            $originalHeight = $image->height();
            

            // Only resize very large images (over 3000px) to speed up processing
            // This maintains quality for most images while improving performance
            $maxDimension = 3000;
            $needsResize = false;

            if ($originalWidth > $maxDimension || $originalHeight > $maxDimension) {
                $needsResize = true;
                if ($originalWidth > $originalHeight) {
                    $image->resize($maxDimension, null, fn($c) => $c->aspectRatio());
                } else {
                    $image->resize(null, $maxDimension, fn($c) => $c->aspectRatio());
                }
            }

            // Load and prepare watermark
            $watermark = Image::make($watermarkPath);
            $watermarkOriginalWidth = $watermark->width();
            $watermarkOriginalHeight = $watermark->height();
            
            // Validate watermark dimensions
            if ($watermarkOriginalWidth <= 0 || $watermarkOriginalHeight <= 0) {
                return;
            }
            
            // Calculate watermark size based on percentage
            // Ensure minimum size of 10px to prevent invisible watermarks
            $watermarkWidth = max(10, $image->width() * ($size / 100));
            // Also ensure it doesn't exceed image dimensions
            $watermarkWidth = min($watermarkWidth, $image->width());
            
            $watermark->resize($watermarkWidth, null, fn($c) => $c->aspectRatio());
            
            // Store dimensions before rotation for tiling calculations
            $watermarkWidthBeforeRotation = $watermark->width();
            $watermarkHeightBeforeRotation = $watermark->height();
            
            // Validate dimensions after resize
            if ($watermarkWidthBeforeRotation <= 0 || $watermarkHeightBeforeRotation <= 0) {
                return;
            }
            
            // Apply opacity (only if > 0, otherwise skip processing)
            if ($opacity > 0) {
                $watermark->opacity($opacity);
            } else {
                // Log::info('Watermark opacity is 0, watermark will be invisible');
            }
            
            // Rotate the watermark if needed
            if ($appliedRotation != 0) {
                $watermark->rotate($appliedRotation);
            }
            
            // Log::info('Watermark prepared', [
            //     'original_size' => "{$watermarkOriginalWidth}x{$watermarkOriginalHeight}",
            //     'resized_size' => "{$watermarkWidthBeforeRotation}x{$watermarkHeightBeforeRotation}",
            //     'after_rotation_size' => "{$watermark->width()}x{$watermark->height()}",
            //     'rotation' => $appliedRotation
            // ]);

            /**
             * 🧩 Apply watermark based on style
             */
            if ($style === 'tile') {
                // Use dimensions before rotation for spacing calculation to ensure proper coverage
                $baseSpacing = 1.5;
                $xStep = (int)($watermarkWidthBeforeRotation * $baseSpacing);
                $yStep = (int)($watermarkHeightBeforeRotation * $baseSpacing);
                
                // Ensure minimum step size to prevent infinite loops
                $xStep = max(1, $xStep);
                $yStep = max(1, $yStep);

                // Calculate number of tiles and optimize spacing if too many
                $tilesX = (int)ceil($image->width() / max(1, $xStep));
                $tilesY = (int)ceil($image->height() / max(1, $yStep));
                $totalTiles = $tilesX * $tilesY;

                // Limit to max 150 tiles for performance (adjust spacing if needed)
                $maxTiles = 150;
                if ($totalTiles > $maxTiles) {
                    $factor = sqrt($totalTiles / $maxTiles);
                    $xStep = max(1, (int)($xStep * $factor));
                    $yStep = max(1, (int)($yStep * $factor));
                    // Recalculate after adjustment
                    $tilesX = (int)ceil($image->width() / max(1, $xStep));
                    $tilesY = (int)ceil($image->height() / max(1, $yStep));
                }

                // ::info('Tiling calculation', [
                //     'tiles_x' => $tilesX,
                //     'tiles_y' => $tilesY,
                //     'total_tiles' => $tilesX * $tilesY,
                //     'x_step' => $xStep,
                //     Log'y_step' => $yStep
                // ]);

                // Apply tiles - Intervention Image can reuse the same watermark object
                $tileCount = 0;
                try {
                    for ($y = 0; $y < $image->height(); $y += $yStep) {
                        for ($x = 0; $x < $image->width(); $x += $xStep) {
                            $image->insert($watermark, 'top-left', $x, $y);
                            $tileCount++;
                        }
                    }
                    // Log::info('Tiles applied successfully', ['count' => $tileCount]);
                } catch (\Exception $e) {
                    // Log::error('Error applying tiles', [
                    //     'error' => $e->getMessage(),
                    //     'tiles_applied' => $tileCount,
                    //     'trace' => $e->getTraceAsString()
                    // ]);
                    throw $e;
                }
            } else {
                // Single watermark or center style
                $padding = 10;
                // Single watermark at specified position
                switch ($position) {
                    case 'top-left':
                        $image->insert($watermark, 'top-left', $padding, $padding);
                        break;
                    case 'top-right':
                        $image->insert($watermark, 'top-right', $padding, $padding);
                        break;
                    case 'bottom-left':
                        $image->insert($watermark, 'bottom-left', $padding, $padding);
                        break;
                    case 'bottom-right':
                        $image->insert($watermark, 'bottom-right', $padding, $padding);
                        break;
                    case 'center':
                    default:
                        $image->insert($watermark, 'center');
                        break;
                }
                
                // ::info('Single watermark applied', [
                //     'position' => $position,
                //     Log'style' => $style
                // ]);
            }

            /**
             * 💾 Save image (replace original)
             */
            // Normalize extension for encoding (jpg -> jpeg)
            $encodeFormat = strtolower($this->extension);
            if ($encodeFormat === 'jpg') {
                $encodeFormat = 'jpeg';
            }
            
            // Log::info('Saving watermarked image', [
            //     'path' => $this->imagePath,
            //     'format' => $encodeFormat,
            //     'quality' => 85
            // ]);
            
            try {
                $image->encode($encodeFormat, 85)->save($this->imagePath);
                
                // Verify the file was saved
                if (!file_exists($this->imagePath)) {
                    throw new \Exception('Image file was not saved successfully');
                }
                
                $fileSize = filesize($this->imagePath);
                // Log::info('Watermark job completed successfully', [
                //     'image_path' => $this->imagePath,
                //     'file_size' => $fileSize,
                //     'processing_time' => round(microtime(true) - $startTime, 2) . 's'
                // ]);
            } catch (\Exception $e) {
                Log::error('Error saving watermarked image', [
                    'error' => $e->getMessage(),
                    'path' => $this->imagePath,
                    'format' => $encodeFormat,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        } catch (\Throwable $e) {
            Log::error('Error in AddWatermarkJob: ' . $e->getMessage(), [
                'imagePath' => $this->imagePath,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
