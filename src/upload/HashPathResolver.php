<?php

namespace karakum\common\upload;

use FileUpload\PathResolver\PathResolver;
use Yii;

class HashPathResolver implements PathResolver
{

    protected $namespace;

    private $cache = [];

    /**
     * A construct to remember
     * @param string $ns Storage namespace where files should be stored
     */
    public function __construct($ns)
    {
        $this->namespace = $ns;
    }

    /**
     * Get absolute final destination path
     * @param  string $name
     * @return string
     */
    public function getUploadPath($name = null)
    {
        if (is_null($name)) {
            return '';
        } else {
            if (!isset($this->cache[$name])) {
                $this->cache[$name] = Yii::$app->pathManager->getPath($this->namespace);
            }
        }
        return $this->cache[$name]->getFullPath($name);
    }

    /**
     * Ensure consistent name
     * @param  string $name
     * @return string
     */
    public function upcountName($name)
    {
        return preg_replace_callback('/(?:(?: \(([\d]+)\))?(\.[^.]+))?$/', function ($matches) {
            $index = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
            $ext = isset($matches[2]) ? $matches[2] : '';

            return ' (' . $index . ')' . $ext;
        }, $name, 1);
    }

    public function getFileData($name)
    {
        return $this->cache[$name];
    }
}