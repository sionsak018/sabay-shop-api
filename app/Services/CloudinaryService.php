<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    protected $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key'    => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
        ]);
    }

    /**
     * Upload a file to Cloudinary.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string|null The secure URL of the uploaded image.
     */
    public function upload(UploadedFile $file, string $folder = 'sabay-shop')
    {
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

    /**
     * Delete an image from Cloudinary using its URL.
     */
    public function delete(?string $url)
    {
        if (!$url) {
            return false;
        }

        try {
            // Extract the public_id from the URL
            // Format: https://res.cloudinary.com/{cloud_name}/image/upload/{version}/{folder}/{subfolder}/{public_id}.{ext}

            // 1. Remove the domain part and the version part
            // We want everything after 'upload/' or 'v[0-9]+/'

            $path = parse_url($url, PHP_URL_PATH);
            if (!$path) return false;

            // Path usually starts with /cloud_name/image/upload/v12345/folder/public_id.jpg
            $segments = explode('/', ltrim($path, '/'));

            // Find 'upload' segment
            $uploadIndex = array_search('upload', $segments);
            if ($uploadIndex === false) {
                \Log::warning("Cloudinary delete: 'upload' segment not found in URL: $url");
                return false;
            }

            // The segments after 'upload' starting with 'v' followed by digits is the version
            $versionIndex = $uploadIndex + 1;
            if (isset($segments[$versionIndex]) && preg_match('/^v[0-9]+$/', $segments[$versionIndex])) {
                $startIndex = $versionIndex + 1;
            } else {
                $startIndex = $versionIndex;
            }fdf

            // Join the remaining segments and remove extension
            $publicIdWithExt = implode('/', array_slice($segments, $startIndex));
            $publicId = preg_replace('/\.[^.]+$/', '', $publicIdWithExt);

            \Log::info("Cloudinary attempt delete public_id: " . $publicId);

            $result = $this->cloudinary->uploadApi()->destroy($publicId);

            if (isset($result['result']) && $result['result'] === 'ok') {
                \Log::info("Cloudinary delete success for: " . $publicId);
                return true;
            } else {
                \Log::warning("Cloudinary delete result not 'ok': " . json_encode($result));
                return false;
            }
        } catch (\Exception $e) {
            \Log::error('Cloudinary deletion failed: ' . $e->getMessage());
            return false;
        }
    }
}
