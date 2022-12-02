<?php

declare(strict_types=1);

namespace Rexpl\Workerman\Tools;

use Rexpl\Workerman\Exceptions\FileHandlingException;

class Files
{
    use JsonError;


    /**
     * Root path.
     * 
     * @var string
     */
    public static string $rootPath;


    /**
     * File resource locked.
     * 
     * @var array<string,resource>
     */
    protected static array $files = [];


    /**
     * Return the absolute path.
     * 
     * @param string $path
     * 
     * @return string
     */
    public static function path(string $path): string
    {
        if (str_starts_with($path, '/')) return static::$rootPath . $path;

        return static::$rootPath . '/' . $path;
    }


    /**
     * Open file.
     * 
     * @param string $path
     * @param string $mode
     *
     * @return resource
     * 
     * @throws FileHandlingException
     */
    public static function openFile(string $path, string $mode)//: resource
    {
        $file = fopen(static::path($path), $mode);

        if (false === $file) {

            throw new FileHandlingException(sprintf(
                'Could not open file "%s".', static::path($path)
            ));
        }

        return $file;
    }


    /**
     * Does this file exist.
     * 
     * @param string $path
     * 
     * @return bool
     */
    public static function fileExists(string $path): bool
    {
        return file_exists(static::path($path));
    }


    /**
     * Set file content.
     * 
     * @param string $path
     * @param mixed $content
     * 
     * @return void
     * 
     * @throws FileHandlingException
     */
    public static function setFileContent(string $path, mixed $content): void
    {
        $path = static::path($path);
        
        $content = json_encode($content);

        if (false === $content) {

            throw new FileHandlingException(sprintf(
                'Failed converting content to json when saving to file "%s". Json error: %s.', $path, static::jsonError()
            ));
        }

        if (false === file_put_contents($path, $content)) {

            throw new FileHandlingException(sprintf(
                'Failed to write to file "%s".', $path
            ));
        }
    }


    /**
     * Get the content of a file.
     * 
     * @param string $path
     * 
     * @return mixed
     * 
     * @throws FileHandlingException
     */
    public static function getFileContent(string $path): mixed
    {
        $path = static::path($path);

        $content = file_get_contents($path);

        if (false === $content) {

            throw new FileHandlingException(sprintf(
                'Failed to read file "%s".', $path
            ));
        }

        $content = json_decode($content, true);

        if (false !== $content) return $content;

        throw new FileHandlingException(sprintf(
            'Failed to parse json content from file "%s". Json error: %s.', $path, static::jsonError()
        ));
    }


    /**
     * Deletes a file if it exists.
     * 
     * @param string $path
     * 
     * @return void
     * 
     * @throws FileHandlingException
     */
    public static function deleteFile(string $path): void
    {
        if (!static::fileExists($path)) return;
        
        $path = static::path($path);

        if (false === unlink($path)) {

            throw new FileHandlingException(sprintf(
                'Failed to delete file %s', $path
            ));
        }
    }


    /**
     * Lock a file. (exclusive lock)
     * 
     * @param string $path
     * 
     * @return void
     * 
     * @throws FileHandlingException
     */
    public static function lock(string $path): void
    {
        $file = static::openFile($path, 'r+');

        if (!flock($file, LOCK_EX)) {

            throw new FileHandlingException(sprintf(
                'Failed to lock file "%s".', static::path($path)
            ));
        }

        static::$files[$path] = $file;
    }


    /**
     * Unlock a file. (and close lock stream)
     * 
     * @param $path
     * 
     * @return void
     * 
     * @throws FileHandlingException
     */
    public static function unlock(string $path): void
    {
        if (static::isLocked($path)) {

            throw new FileHandlingException(
                'Cannot unlock, unlocked file.'
            );
        }

        if (!flock(static::$files[$path], LOCK_UN)) {

            throw new FileHandlingException(sprintf(
                'Failed to unlock file "%s".', static::path($path)
            ));
        }

        fclose(static::$files[$path]);
        unset(static::$files[$path]);
    }


    /**
     * Is file locked.
     * 
     * @param $path
     * 
     * @return bool
     */
    public static function isLocked(string $path): bool
    {
        return isset(static::$files[$path]);
    }
}