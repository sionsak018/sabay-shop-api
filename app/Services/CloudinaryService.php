<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    protected $cloudinary;

    public function __construct()
    {
        if ($this->shouldUseCloudinary()) {
            $this->cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => config('cloudinary.cloud_name'),
                    'api_key'    => config('cloudinary.api_key'),
                    'api_secret' => config('cloudinary.api_secret'),
                ],
            ]);
        }
    }

    /**
     * Determine if we should use Cloudinary or Local storage.
     */
    protected function shouldUseCloudinary(): bool
    {
        // 1. Force Local Storage if running on localhost (app environment is local)
        if (app()->isLocal()) {
            return false;
        }

        // 2. Otherwise, use Cloudinary if credentials are provided
        return !empty(config('cloudinary.cloud_name')) &&
               !empty(config('cloudinary.api_key')) &&
               !empty(config('cloudinary.api_secret'));
    }

    /**
     * Upload a file to Cloudinary or Local storage.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string|null The URL of the uploaded image.
     */
    public function upload(UploadedFile $file, string $folder = 'sabay-shop')
    {
        if ($this->shouldUseCloudinary()) {
            try {
                $path = $file->getRealPath();
                \Log::info("Attempting Cloudinary upload from path: $path to folder: $folder");

                $upload = $this->cloudinary->uploadApi()->upload($path, [
                    'folder' => $folder,
                ]);

                \Log::info("Cloudinary upload success: " . $upload['secure_url']);
                return $upload['secure_url'];
            } catch (\Exception $e) {
                \Log::error('Cloudinary upload failed: ' . $e->getMessage());
                return null;
            }
        }

        // Fallback to Local Storage
        try {
            $path = $file->store($folder, 'public');

            // Return only the relative path for local storage
            // This is safer as the frontend can prepend its own base URL
            \Log::info("Local storage upload success: " . $path);
            return $path;
        } catch (\Exception $e) {
            \Log::error('Local storage upload failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete an image from Cloudinary or Local storage using its URL.
     */
    public function delete(?string $url)
    {
        if (!$url) {
            return false;
        }

        // Check if it's a Cloudinary URL
        if (str_contains($url, 'cloudinary.com')) {
            return $this->deleteFromCloudinary($url);
        }

        // Otherwise assume it's Local storage
        return $this->deleteFromLocal($url);
    }

    protected function deleteFromCloudinary(string $url)
    {
        try {
            // Ensure Cloudinary is initialized even if we are in local environment
            // but need to delete an existing cloud image.
            if (!$this->cloudinary) {
                $this->cloudinary = new Cloudinary([
                    'cloud' => [
                        'cloud_name' => config('cloudinary.cloud_name'),
                        'api_key'    => config('cloudinary.api_key'),
                        'api_secret' => config('cloudinary.api_secret'),
                    ],
                ]);
            }

            $path = parse_url($url, PHP_URL_PATH);
            if (!$path) return false;

            $segments = explode('/', ltrim($path, '/'));
            $uploadIndex = array_search('upload', $segments);
            if ($uploadIndex === false) {
                \Log::warning("Cloudinary delete: 'upload' segment not found in URL: $url");
                return false;
            }

            $versionIndex = $uploadIndex + 1;
            if (isset($segments[$versionIndex]) && preg_match('/^v[0-9]+$/', $segments[$versionIndex])) {
                $startIndex = $versionIndex + 1;
            } else {
                $startIndex = $versionIndex;
            }

            $publicIdWithExt = implode('/', array_slice($segments, $startIndex));
            $publicId = preg_replace('/\.[^.]+$/', '', $publicIdWithExt);

            \Log::info("Cloudinary attempt delete public_id: " . $publicId);

            $result = $this->cloudinary->uploadApi()->destroy($publicId);

            return isset($result['result']) && $result['result'] === 'ok';
        } catch (\Exception $e) {
            \Log::error('Cloudinary deletion failed: ' . $e->getMessage());
            return false;
        }
    }

    protected function deleteFromLocal(string $urlOrPath)
    {
        try {
            // 1. If it's a full URL, extract the relative path
            $storageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url('');
            $path = str_replace($storageUrl, '', $urlOrPath);

            // Remove host if it's a mismatch (e.g. stored as localhost, but running as 127.0.0.1)
            if (str_starts_with($path, 'http')) {
                $urlParts = parse_url($path);
                if (isset($urlParts['path'])) {
                    $path = str_replace('/storage/', '', $urlParts['path']);
                }
            }

            $path = ltrim($path, '/');

            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
                \Log::info("Local storage delete success for: " . $path);
                return true;
            }
            \Log::warning("Local storage file not found for deletion: " . $path);
            return false;
        } catch (\Exception $e) {
            \Log::error('Local storage deletion failed: ' . $e->getMessage());
            return false;
        }
    }
}
