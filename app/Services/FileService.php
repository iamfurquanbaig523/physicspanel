<?php

namespace App\Services;

use App\Jobs\AddWatermarkJob;
use App\Models\Setting;
use App\Services\HelperService;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class FileService
{
    /**
     * @param $requestFile
     * @param string $folder
     * @param bool $addWaterMark
     * @return string|false
     */
    public static function compressAndUpload($requestFile, string $folder, bool $addWaterMark = false)
    {
        $filenameWithoutExt = pathinfo($requestFile->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($requestFile->getClientOriginalExtension());
        $fileName = time() . '-' . Str::slug($filenameWithoutExt) . '.' . $extension;
        $disk = config('filesystems.default');
        $path = $folder . '/' . $fileName;

        try {
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                // Compress and save image
                $image = Image::make($requestFile)->encode($extension, 80);
                Storage::disk($disk)->put($path, (string) $image);

                // Get absolute path safely
                $absolutePath = self::getAbsolutePath($disk, $path);
                if (! $absolutePath) {
                    // Log::warning('Cannot get absolute path for image', ['disk' => $disk, 'path' => $path]);
                    return $path;
                }
                // Queue watermark if enabled
                if ($addWaterMark && HelperService::getWatermarkConfigStatus()) {
                    // Log::info('Watermark: watermark is enable');
                    AddWatermarkJob::dispatch($absolutePath, $extension);
                } elseif ($addWaterMark) {
                    Log::info('Watermark skipped: watermark is disabled');
                }

                return $path;
            }

            // Non-image files
            $requestFile->storeAs($folder, $fileName, $disk);
            return $path;
        } catch (Exception $e) {
            Log::error('FileService::compressAndUpload error: ' . $e->getMessage(), ['file' => $path]);
            return false;
        }
    }

    /**
     * Get absolute path for a file stored on a disk
     *
     * @param string $disk
     * @param string $path
     * @return string|null
     */
    private static function getAbsolutePath(string $disk, string $path): ?string
    {
        try {
            $storagePath = Storage::disk($disk)->path($path);
            if (file_exists($storagePath)) {
                return $storagePath;
            }
        } catch (Exception $e) {
            Log::error('FileService::getAbsolutePath error: ' . $e->getMessage(), ['disk' => $disk, 'path' => $path]);
        }

        return null;
    }



    /**
     * @param $requestFile
     * @param $folder
     * @return string
     */
    public static function upload($requestFile, $folder)
    {
        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();
        Storage::disk(config('filesystems.default'))->putFileAs($folder, $requestFile, $file_name);
        return $folder . '/' . $file_name;
    }

    /**
     * @param $requestFile
     * @param $folder
     * @param $deleteRawOriginalImage
     * @return string
     */
    public static function replace($requestFile, $folder, $deleteRawOriginalImage)
    {
        self::delete($deleteRawOriginalImage);
        return self::upload($requestFile, $folder);
    }

    /**
     * @param $requestFile
     * @param $folder
     * @param $deleteRawOriginalImage
     * @return string
     */
    public static function compressAndReplace($requestFile, $folder, $deleteRawOriginalImage, bool $addWaterMark = false)
    {
        if (!empty($deleteRawOriginalImage)) {
            self::delete($deleteRawOriginalImage);
        }
        return self::compressAndUpload($requestFile, $folder, $addWaterMark);
    }


    /**
     * @param $requestFile
     * @param $code
     * @return string
     */
    public static function uploadLanguageFile($requestFile, $code)
    {
        $filename = $code . '.' . $requestFile->getClientOriginalExtension();
        if (file_exists(base_path('resources/lang/') . $filename)) {
            File::delete(base_path('resources/lang/') . $filename);
        }
        $requestFile->move(base_path('resources/lang/'), $filename);
        return $filename;
    }

    /**
     * @param $file
     * @return bool
     */
    public static function deleteLanguageFile($file)
    {
        if (file_exists(base_path('resources/lang/') . $file)) {
            return File::delete(base_path('resources/lang/') . $file);
        }
        return true;
    }


    /**
     * @param $image = rawOriginalPath
     * @return bool
     */
    public static function delete($image)
    {

        if (!empty($image) && Storage::disk(config('filesystems.default'))->exists($image)) {
            return Storage::disk(config('filesystems.default'))->delete($image);
        }

        //Image does not exist in server so feel free to upload new image
        return true;
    }

    /**
     * @throws Exception
     */
    public static function compressAndUploadWithWatermark($requestFile, $folder)
    {
        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();

        try {
            if (in_array($requestFile->getClientOriginalExtension(), ['jpg', 'jpeg', 'png'])) {
                $watermarkPath = Setting::where('name', 'watermark_image')->value('value');

                $fullWatermarkPath = storage_path('app/public/' . $watermarkPath);
                $watermark = null;

                $imagePath = $requestFile->getPathname();
                if (!file_exists($imagePath) || !is_readable($imagePath)) {
                    throw new RuntimeException("Uploaded image file is not readable at path: " . $imagePath);
                }
                $image = Image::make($imagePath)->encode(null, 60);
                $imageWidth = $image->width();
                $imageHeight = $image->height();

                if (!empty($watermarkPath) && file_exists($fullWatermarkPath)) {
                    $watermark = Image::make($fullWatermarkPath)
                        ->resize($imageWidth, $imageHeight, function ($constraint) {
                            $constraint->aspectRatio(); // Preserve aspect ratio
                        })
                        ->opacity(10);
                }

                if ($watermark) {
                    $image->insert($watermark, 'center');
                }

                Storage::disk(config('filesystems.default'))->put($folder . '/' . $file_name, (string)$image->encode());
            } else {
                // Else assign file as it is
                $file = $requestFile;
                $file->storeAs($folder, $file_name, 'public');
            }
            return $folder . '/' . $file_name;
        } catch (Exception $e) {
            throw new RuntimeException($e);
            //            $file = $requestFile;
            //            return  $file->storeAs($folder, $file_name, 'public');
        }
    }
    public static function compressAndReplaceWithWatermark($requestFile, $folder, $deleteRawOriginalImage = null)
    {

        if (!empty($deleteRawOriginalImage)) {
            self::delete($deleteRawOriginalImage);
        }

        $file_name = uniqid('', true) . time() . '.' . $requestFile->getClientOriginalExtension();

        try {
            if (in_array($requestFile->getClientOriginalExtension(), ['jpg', 'jpeg', 'png'])) {
                $watermarkPath = Setting::where('name', 'watermark_image')->value('value');
                $fullWatermarkPath = storage_path('app/public/' . $watermarkPath);
                $watermark = null;
                $imagePath = $requestFile->getPathname();
                if (!file_exists($imagePath) || !is_readable($imagePath)) {
                    throw new RuntimeException("Uploaded image file is not readable at path: " . $imagePath);
                }
                $image = Image::make($imagePath)->encode(null, 60);
                $imageWidth = $image->width();
                $imageHeight = $image->height();


                if (!empty($watermarkPath) && file_exists($fullWatermarkPath)) {
                    $watermark = Image::make($fullWatermarkPath)
                        ->resize($imageWidth, $imageHeight, function ($constraint) {
                            $constraint->aspectRatio(); // Preserve aspect ratio
                        })
                        ->opacity(10);
                }

                if ($watermark) {
                    $image->insert($watermark, 'center');
                }


                Storage::disk(config('filesystems.default'))->put($folder . '/' . $file_name, (string)$image->encode());
            } else {

                $file = $requestFile;
                $file->storeAs($folder, $file_name, 'public');
            }

            return $folder . '/' . $file_name;
        } catch (Exception $e) {
            throw new RuntimeException($e);
        }
    }

    public static function renameLanguageFiles(string $oldCode, string $newCode): void
    {
        $langPath = resource_path('lang');

        // Rename JSON file (for frontend)
        if (file_exists($langPath . '/' . $oldCode . '.json')) {
            rename(
                $langPath . '/' . $oldCode . '.json',
                $langPath . '/' . $newCode . '.json'
            );
        }

        // Rename PHP language folder (for backend)
        if (is_dir($langPath . '/' . $oldCode)) {
            rename(
                $langPath . '/' . $oldCode,
                $langPath . '/' . $newCode
            );
        }
    }
}
