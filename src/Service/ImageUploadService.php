<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploadService
{
    public function __construct(private string $uploadDir) {}

    /**
     * Upload an image file and return the relative web path.
     */
    public function upload(UploadedFile $file): string
    {
        $extension = $file->guessExtension() ?? 'jpg';
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array(strtolower($extension), $allowedExtensions)) {
            throw new \InvalidArgumentException('Invalid image type: ' . $extension);
        }

        // Max 5MB
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Image too large (max 5MB)');
        }

        $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $file->move($this->uploadDir, $filename);

        return 'uploads/images/' . $filename;
    }
}
