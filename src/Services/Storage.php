<?php

namespace KhaledHajSalem\Zatca\Services;

use KhaledHajSalem\Zatca\Exceptions\ZatcaStorageException;

/**
 * Simple storage service for file operations.
 */
class Storage
{
    private string $disk;

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function __construct(string $disk = 'local')
    {
        $this->disk = $disk;
    }

    /**
     * Put content to a file.
     *
     * @param string $path
     * @param string $content
     * @return bool
     * @throws ZatcaStorageException
     */
    public function put(string $path, string $content): bool
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new ZatcaStorageException("Failed to create directory: $directory");
        }

        if (file_put_contents($path, $content) === false) {
            throw new ZatcaStorageException("Failed to write file: $path");
        }

        return true;
    }

    /**
     * Get content from a file.
     *
     * @param string $path
     * @return string|false
     */
    public function getContent(string $path)
    {
        return file_get_contents($path);
    }

    /**
     * Upload file by content.
     *
     * @param string $path
     * @param string $content
     * @return bool
     * @throws ZatcaStorageException
     */
    public function uploadFileByContent(string $path, string $content): bool
    {
        return $this->put($path, $content);
    }
} 