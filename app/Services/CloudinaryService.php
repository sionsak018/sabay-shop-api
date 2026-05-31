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
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
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
            $upload = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder' => $folder,
            ]);

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
