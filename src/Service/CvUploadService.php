<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class CvUploadService
{
    private string $cvsDirectory;

    public function __construct(
        private SluggerInterface $slugger,
        string $projectDir
    ) {
        $this->cvsDirectory = $projectDir . '/public/uploads/cvs';
    }

    /**
     * Upload a CV file
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
        
        // Validate extension
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowedExtensions, true)) {
            $ext = 'pdf';
        }
        
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $ext;

        // Create directory if it doesn't exist
        if (!is_dir($this->cvsDirectory)) {
            mkdir($this->cvsDirectory, 0755, true);
        }

        try {
            $file->move($this->cvsDirectory, $newFilename);
        } catch (FileException $e) {
            throw new FileException('Failed to upload CV: ' . $e->getMessage());
        }

        return 'uploads/cvs/' . $newFilename;
    }

    /**
     * Delete a CV file
     * 
     * @param string $filePath The relative path to the file
     * @return bool True if deleted, false otherwise
     */
    public function delete(string $filePath): bool
    {
        $fullPath = dirname($this->cvsDirectory, 2) . '/' . $filePath;
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }

    /**
     * Get the full path to a CV file
     * 
     * @param string $relativePath The relative path
     * @return string The full filesystem path
     */
    public function getFullPath(string $relativePath): string
    {
        return dirname($this->cvsDirectory, 2) . '/' . $relativePath;
    }

    /**
     * Get allowed MIME types for CVs
     */
    public function getAllowedMimeTypes(): array
    {
        return [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
        ];
    }

    /**
     * Get max file size in bytes (10MB)
     */
    public function getMaxFileSize(): int
    {
        return 10 * 1024 * 1024; // 10MB
    }
}
