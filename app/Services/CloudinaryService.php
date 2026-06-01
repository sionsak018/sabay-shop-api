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
     * Note: This is optional and requires the public_id.
     */
    public function delete(string $url)
    {
        // Extraction of public_id from URL can be complex, skipping for now unless needed.
    }
}
