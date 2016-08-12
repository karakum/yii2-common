<?php


namespace karakum\common\upload;

use FileUpload\FileNameGenerator\Random;
use FileUpload\FileUpload;
use FileUpload\Util;

class RandomNameGenerator extends Random
{

    private $originalName = [];

    /**
     * Maximum length of the filename
     * @var int
     */
    private $name_length = 32;

    /**
     * Pathresolver
     * @var PathResolver
     */
    private $pathresolver;

    /**
     * Filesystem
     * @var FileSystem
     */
    private $filesystem;

    function __construct($name_length = 32)
    {
        $this->name_length = $name_length;
    }

    public function getFileName($source_name, $type, $tmp_name, $index, $content_range, FileUpload $upload)
    {
        $this->pathresolver = $upload->getPathResolver();
        $this->filesystem = $upload->getFileSystem();
        if (strrpos($source_name, '.')) {
            $extension = substr($source_name, strrpos($source_name, '.') + 1);
        } else {
            if (strrpos($type, 'image/') !== false) {
                $extension = substr($type, strrpos($type, 'image/') + 6);
                if ($extension == 'jpeg') {
                    $extension = 'jpg';
                }
            } else {
                $extension = 'bin';
            }
        }
        $name = $this->getUniqueFilename($source_name, $type, $index, $content_range, $extension);
        $this->originalName[$name] = $source_name;
        return $name;
    }

    protected function generateRandom()
    {
        return (substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $this->name_length));
    }

    /**
     * Get unique but consistent name
     * @param  string $name
     * @param  string $type
     * @param  integer $index
     * @param  array $content_range
     * @param  string $extension
     * @return string
     */
    protected function getUniqueFilename($name, $type, $index, $content_range, $extension)
    {
        $name = $this->generateRandom() . "." . $extension;
        while ($this->filesystem->isDir($this->pathresolver->getUploadPath($name))) {
            $name = $this->generateRandom() . "." . $extension;
        }

        $uploaded_bytes = Util::fixIntegerOverflow(intval($content_range[1]));

        while ($this->filesystem->isFile($this->pathresolver->getUploadPath($name))) {
            if ($uploaded_bytes == $this->filesystem->getFilesize($this->pathresolver->getUploadPath($name))) {
                break;
            }

            $name = $this->generateRandom() . "." . $extension;
        }

        return $name;
    }

    public function getOriginalName($name)
    {
        return $this->originalName[$name];
    }
}
