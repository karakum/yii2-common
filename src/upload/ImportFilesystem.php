<?php

namespace karakum\common\upload;


use FileUpload\FileSystem\Simple;

class ImportFilesystem extends Simple
{
    protected $remove;

    /**
     * ImportFilesystem constructor.
     * @param bool $remove Remove imported file
     */
    public function __construct($remove = false)
    {
        $this->remove = $remove;
    }

    public function isUploadedFile($path)
    {
        return true;
    }

    public function moveUploadedFile($from_path, $to_path)
    {
        return copy($from_path, $to_path) && ($this->remove ? unlink($from_path) : true);
    }

}