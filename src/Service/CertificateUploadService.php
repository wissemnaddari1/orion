<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class CertificateUploadService
{
    private string $certificatesDirectory;

    public function __construct(
        private SluggerInterface $slugger,
        string $projectDir
    ) {
        $this->certificatesDirectory = $projectDir . '/public/uploads/certificates';
    }

    /**
     * Upload a certificate file
     * 
     * @param UploadedFile $file The uploaded file
     * @return string The relative path to the uploaded file
     * @throws FileException If upload fails
     */
    public function upload(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'pdf');
        $ext = \in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'pdf';
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $ext;

        // Create directory if it doesn't exist
        if (!is_dir($this->certificatesDirectory)) {
            mkdir($this->certificatesDirectory, 0755, true);
        }

        try {
            $file->move($this->certificatesDirectory, $newFilename);
        } catch (FileException $e) {
            throw new FileException('Failed to upload certificate: ' . $e->getMessage());
        }

        return 'uploads/certificates/' . $newFilename;
    }

    /**
     * Delete a certificate file
     * 
     * @param string $filePath The relative path to the file
     * @return bool True if deleted, false otherwise
     */
    public function delete(string $filePath): bool
    {
        $fullPath = dirname($this->certificatesDirectory, 2) . '/' . $filePath;
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }

    /**
     * Get the full path to a certificate file
     * 
     * @param string $relativePath The relative path
     * @return string The full filesystem path
     */
    public function getFullPath(string $relativePath): string
    {
        return dirname($this->certificatesDirectory, 2) . '/' . $relativePath;
    }

    /**
     * Get allowed MIME types for certificates
     */
    public function getAllowedMimeTypes(): array
    {
        return [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
    }

    /**
     * Get max file size in bytes (5MB)
     */
    public function getMaxFileSize(): int
    {
        return 5 * 1024 * 1024; // 5MB
    }
}
