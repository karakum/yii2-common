<?php

namespace karakum\common\upload;

use finfo;
use karakum\common\upload\models\Attachment;
use karakum\common\upload\models\Thumbnails;
use FileUpload\File;
use FileUpload\FileUpload;
use Intervention\Image\ImageManagerStatic;
use karakum\PathRegistry\PathManager;
use karakum\PathRegistry\PathOrganizer;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;

class AttachManager extends Component
{
    /**
     * @var string application identity class.
     */
    public $identityClass;

    public $validators;
    public $thumbnails;

    public function __construct($config = [])
    {
        parent::__construct($config);
        if (!$this->identityClass) {
            throw new InvalidConfigException('You should configure "identityClass"');
        }
    }

    public function init()
    {
        parent::init();

    }

    public function getValidatorProfile($name)
    {
        if (!isset($this->validators[$name])) {
            throw new InvalidParamException('Validator profile "' . $name . '" not found');
        }
        return $this->validators[$name];
    }

    public function getThumbnailProfile($name)
    {
        if (!isset($this->thumbnails[$name])) {
            throw new InvalidParamException('Thumbnail profile "' . $name . '" not found');
        }
        return $this->thumbnails[$name];
    }

    /**
     * @return PathManager
     */
    private function getPathManager()
    {
        /** @var PathManager $pathManager */
        $pathManager = Yii::$app->pathManager;
        return $pathManager;
    }

    /**
     * @param $storageNamespace
     * @param $validatorProfile
     * @param $thumbnailProfile
     * @param $attribute
     * @param Attachment $image
     * @param Attachment $thumb
     * @param \Closure $attachCallback
     * @return array
     */
    public function importImage($storageNamespace, $validatorProfile, $thumbnailProfile, $filename, $image, $thumb, \Closure $attachCallback)
    {
        $pathManager = $this->getPathManager();

        // Simple validation (max file size 2MB and only two allowed mime types)
        $validatorProfile = $this->getValidatorProfile($validatorProfile);
        $validator = new \FileUpload\Validator\Simple($validatorProfile['size'], $validatorProfile['mimeType']);
        $thumbProfile = $this->getThumbnailProfile($thumbnailProfile);


        // Simple path resolver, where uploads will be put
        $pathResolver = new HashPathResolver($storageNamespace);

        // The machine's filesystem
        $fileSystem = new ImportFilesystem();

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $upload = [
            'name' => basename($filename),
            'type' => $finfo->file($filename),
            'tmp_name' => $filename,
            'error' => UPLOAD_ERR_OK,
            'size' => $fileSystem->getFilesize($filename),
        ];

        $fileUpload = new FileUpload($upload, $_SERVER);

        $fileNameGenerator = new RandomNameGenerator();
        $fileUpload->setFileNameGenerator($fileNameGenerator);
        $fileUpload->setPathResolver($pathResolver);
        $fileUpload->setFileSystem($fileSystem);
        $fileUpload->addValidator($validator);

        $oldFiles = [];
        if ($image->file) {
            $oldFiles = $this->collectOldFiles($image, $oldFiles);
        }
        if ($thumb->file) {
            $oldFiles = $this->collectOldFiles($thumb, $oldFiles);
        }

        $fileUpload->addCallback('completed', function (File $file) use ($pathManager, $attachCallback, $fileUpload, $image, $thumb, $thumbProfile) {
            Yii::error($file);
            $res = false;
            $pathResolver = $fileUpload->getPathResolver();
            $fileNameGenerator = $fileUpload->getFileNameGenerator();
            $fileSystem = $fileUpload->getFileSystem();

            /** @var PathOrganizer $pathOrganizer */
            $pathOrganizer = $pathResolver->getFileData($file->name);
            if ($pathOrganizer) {
                $pathManager->countUpPath($pathOrganizer);

                $newImage = new Attachment();
                $newImage->attributes = $image->attributes;

                $newImage->path_id = $pathOrganizer->id;
                $newImage->name = $fileNameGenerator->getOriginalName($file->name);
                $newImage->file = $pathOrganizer->path . '/' . $file->name;
                $newImage->mime_type = $file->type;
                $newImage->size = $fileSystem->getFilesize($newImage->getFullName());

                $thImg = ImageManagerStatic::make($file->path);
                $thImg->resize($thumbProfile['width'], $thumbProfile['height']);
                $thumbName = $fileNameGenerator->getFileName('thumb' . $file->name, $file->type, null, 0, null, $fileUpload);
                $thumbPath = $pathResolver->getUploadPath($thumbName);

                /** @var PathOrganizer $pathThumbOrganizer */
                $pathThumbOrganizer = $pathResolver->getFileData($thumbName);
                $thImg->save($thumbPath);

                $pathManager->countUpPath($pathThumbOrganizer);

                $newThumb = new Attachment();
                $newThumb->attributes = $thumb->attributes;
                $newThumb->path_id = $pathThumbOrganizer->id;
                $newThumb->name = $newImage->name;
                $newThumb->file = $pathThumbOrganizer->path . '/' . $thumbName;
                $newThumb->mime_type = $file->type;
                $newThumb->size = $fileSystem->getFilesize($thumbPath);

                if ($attachCallback($file, $newImage, $newThumb)) {
                    $res = true;
                } else {
                    $pathManager->countDownPath($pathOrganizer);
                    $pathManager->countDownPath($pathThumbOrganizer);
                }
            }
            if (!$res) {
                if ($fileSystem->isFile($file->path)) {
                    $fileSystem->unlink($file->path);
                }
                $file->error_code = 100;
            }

        });

        // Doing the deed
        list($files, $headers) = $fileUpload->processAll();

        $result = array_filter(array_map(
            function ($file) {
                if (!is_numeric($file->error)) {
                    $file->error = Yii::t('app', $file->error);
                }
                return $file;
            }, $files),
            function ($file) {
                return $file->error_code != 100;
            });
        $result2 = array_filter($files, function ($file) {
            return $file->error_code == 0;
        });
        if (count($result2)) {
            Attachment::deleteAll(['id' => [$image->id, $thumb->id]]);
            // удаляем старые только при успешной загрузке новых
            $this->deleteOldFiles($oldFiles);
        }

        return ['files' => $result];
    }

    /**
     * @param $storageNamespace
     * @param $validatorProfile
     * @param $thumbnailProfile
     * @param $attribute
     * @param Attachment $image
     * @param Attachment $thumb
     * @param \Closure $attachCallback
     * @return array
     */
    public function uploadImage($storageNamespace, $validatorProfile, $thumbnailProfile, $attribute, $image, $thumb, \Closure $attachCallback)
    {
        $pathManager = $this->getPathManager();

        // Simple validation (max file size 2MB and only two allowed mime types)
        $validatorProfile = $this->getValidatorProfile($validatorProfile);
        $validator = new \FileUpload\Validator\Simple($validatorProfile['size'], $validatorProfile['mimeType']);
        $thumbProfile = $this->getThumbnailProfile($thumbnailProfile);


        // Simple path resolver, where uploads will be put
        $pathResolver = new HashPathResolver($storageNamespace);

        // The machine's filesystem
        $fileSystem = new \FileUpload\FileSystem\Simple();

//        Yii::error($_FILES[$attribute]);
        $fileUpload = new FileUpload(isset($_FILES[$attribute]) ? $_FILES[$attribute] : null, $_SERVER);

        $fileNameGenerator = new RandomNameGenerator();
        $fileUpload->setFileNameGenerator($fileNameGenerator);
        $fileUpload->setPathResolver($pathResolver);
        $fileUpload->setFileSystem($fileSystem);
        $fileUpload->addValidator($validator);

        $oldFiles = [];
        if ($image->file) {
            $oldFiles = $this->collectOldFiles($image, $oldFiles);
        }
        if ($thumb->file) {
            $oldFiles = $this->collectOldFiles($thumb, $oldFiles);
        }

        $fileUpload->addCallback('completed', function (File $file) use ($pathManager, $attachCallback, $fileUpload, $image, $thumb, $thumbProfile) {
            Yii::error($file);
            $res = false;
            $pathResolver = $fileUpload->getPathResolver();
            $fileNameGenerator = $fileUpload->getFileNameGenerator();
            $fileSystem = $fileUpload->getFileSystem();

            /** @var PathOrganizer $pathOrganizer */
            $pathOrganizer = $pathResolver->getFileData($file->name);
            if ($pathOrganizer) {
                $pathManager->countUpPath($pathOrganizer);

                $newImage = new Attachment();
                $newImage->attributes = $image->attributes;

                $newImage->path_id = $pathOrganizer->id;
                $newImage->name = $fileNameGenerator->getOriginalName($file->name);
                $newImage->file = $pathOrganizer->path . '/' . $file->name;
                $newImage->mime_type = $file->type;
                $newImage->size = $fileSystem->getFilesize($newImage->getFullName());

                $thImg = ImageManagerStatic::make($file->path);
                $thImg->resize($thumbProfile['width'], $thumbProfile['height']);
                $thumbName = $fileNameGenerator->getFileName('thumb' . $file->name, $file->type, null, 0, null, $fileUpload);
                $thumbPath = $pathResolver->getUploadPath($thumbName);

                /** @var PathOrganizer $pathThumbOrganizer */
                $pathThumbOrganizer = $pathResolver->getFileData($thumbName);
                $thImg->save($thumbPath);

                $pathManager->countUpPath($pathThumbOrganizer);

                $newThumb = new Attachment();
                $newThumb->attributes = $thumb->attributes;
                $newThumb->path_id = $pathThumbOrganizer->id;
                $newThumb->name = $newImage->name;
                $newThumb->file = $pathThumbOrganizer->path . '/' . $thumbName;
                $newThumb->mime_type = $file->type;
                $newThumb->size = $fileSystem->getFilesize($thumbPath);

                if ($attachCallback($file, $newImage, $newThumb)) {
                    $res = true;
                } else {
                    $pathManager->countDownPath($pathOrganizer);
                    $pathManager->countDownPath($pathThumbOrganizer);
                }
            }
            if (!$res) {
                if ($fileSystem->isFile($file->path)) {
                    $fileSystem->unlink($file->path);
                }
                $file->error_code = 100;
            }

        });

        // Doing the deed
        list($files, $headers) = $fileUpload->processAll();

        // Outputting it, for example like this
        foreach ($headers as $header => $value) {
            header($header . ': ' . $value);
        }
        Yii::error($files);
        $result = array_filter(array_map(
            function ($file) {
                if (!is_numeric($file->error)) {
                    $file->error = Yii::t('app', $file->error);
                }
                return $file;
            }, $files),
            function ($file) {
                return $file->error_code != 100;
            });
        $result2 = array_filter($files, function ($file) {
            return $file->error_code == 0;
        });
        if (count($result2)) {
            Attachment::deleteAll(['id' => [$image->id, $thumb->id]]);
            // удаляем старые только при успешной загрузке новых
            $this->deleteOldFiles($oldFiles);
        }

        return ['files' => $result];
    }

    /**
     * @param $cropData
     * @param Attachment $image
     * @param Attachment $thumb
     * @param \Closure $attachCallback
     * @return array
     */
    public function cropThumb($storageNamespace, $thumbnailProfile, $cropData, $image, $thumb, \Closure $attachCallback)
    {
        $pathManager = $this->getPathManager();
        $thumbProfile = $this->getThumbnailProfile($thumbnailProfile);

        $oldFiles = [];
        if ($thumb->file) {
            $oldFiles = $this->collectOldFiles($thumb, $oldFiles);
        }

        $pathResolver = new HashPathResolver($storageNamespace);
        $fileSystem = new \FileUpload\FileSystem\Simple();
        $fileNameGenerator = new RandomNameGenerator();

        $fileUpload = new FileUpload(null, $_SERVER);
        $fileUpload->setFileNameGenerator($fileNameGenerator);
        $fileUpload->setPathResolver($pathResolver);
        $fileUpload->setFileSystem($fileSystem);


        $thImg = ImageManagerStatic::make($image->getFullName());
        $thImg->crop($cropData['width'], $cropData['height'], $cropData['left'], $cropData['top']);
        if ($cropData['width'] > $thumbProfile['width']) {
            $thImg->resize($thumbProfile['width'], $thumbProfile['height']);
        }

        $thumbName = $fileNameGenerator->getFileName('thumb', $image->mime_type, null, 0, null, $fileUpload);
        $thumbPath = $pathResolver->getUploadPath($thumbName);

        /** @var PathOrganizer $pathThumbOrganizer */
        $pathThumbOrganizer = $pathResolver->getFileData($thumbName);

        $thImg->save($thumbPath);

        $newThumb = new Attachment();
        $newThumb->attributes = $thumb->attributes;

        $newThumb->path_id = $pathThumbOrganizer->id;
        $newThumb->name = $image->name;
        $newThumb->file = $pathThumbOrganizer->path . '/' . $thumbName;
        $newThumb->mime_type = $image->mime_type;
        $newThumb->size = $fileSystem->getFilesize($thumbPath);

        $result = false;
        if ($newThumb->save()) {
            $callbackResult = $attachCallback($image, $newThumb);
            if ($callbackResult) {
                Attachment::deleteAll(['id' => $thumb->id]);
                $pathManager->countUpPath($pathThumbOrganizer);
                $this->deleteOldFiles($oldFiles);

                $result = $callbackResult;
            }
        }
        if (!$result) {
            if ($fileSystem->isFile($thumbPath)) {
                $fileSystem->unlink($thumbPath);
            }
            $result = [
                'error' => 'Crop error',
            ];
        }
        return $result;
    }

    /**
     * @param $storageNamespace string Path manager's namespace
     * @param $cropData
     * @param Attachment $image
     * @param \Closure $attachCallback
     * @return array
     */
    public function cropImage($storageNamespace, $cropData, $image, \Closure $attachCallback)
    {
        $pathManager = $this->getPathManager();

        $oldFiles = $this->collectOldFiles($image, []);

        $pathResolver = new HashPathResolver($storageNamespace);
        $fileSystem = new \FileUpload\FileSystem\Simple();
        $fileNameGenerator = new RandomNameGenerator();

        $fileUpload = new FileUpload(null, $_SERVER);
        $fileUpload->setFileNameGenerator($fileNameGenerator);
        $fileUpload->setPathResolver($pathResolver);
        $fileUpload->setFileSystem($fileSystem);


        $thImg = ImageManagerStatic::make($image->getFullName());
        $thImg->crop($cropData['width'], $cropData['height'], $cropData['left'], $cropData['top']);

        $thumbName = $fileNameGenerator->getFileName('resize', $image->mime_type, null, 0, null, $fileUpload);
        $thumbPath = $pathResolver->getUploadPath($thumbName);

        /** @var PathOrganizer $pathThumbOrganizer */
        $pathThumbOrganizer = $pathResolver->getFileData($thumbName);

        $thImg->save($thumbPath);
        $newImage = new Attachment();
        $newImage->attributes = $image->attributes;

        $newImage->path_id = $pathThumbOrganizer->id;
        $newImage->file = $pathThumbOrganizer->path . '/' . $thumbName;
        $newImage->size = $fileSystem->getFilesize($thumbPath);

        $result = false;
        if ($newImage->save()) {
            $callbackResult = $attachCallback($newImage);
            if ($callbackResult) {
                Attachment::deleteAll(['id' => $image->id]);
                $pathManager->countUpPath($pathThumbOrganizer);
                $this->deleteOldFiles($oldFiles);

                $result = $callbackResult;
            }
        }
        if (!$result) {
            if ($fileSystem->isFile($thumbPath)) {
                $fileSystem->unlink($thumbPath);
            }
            $result = [
                'error' => 'Crop error',
            ];
        }
        return $result;
    }

    /**
     * @param $thumb Attachment
     * @param $width
     * @param $height
     * @return Thumbnails
     */
    public function getThumbnail($thumb, $width, $height)
    {

        $th = null;
        /** @var Thumbnails $_th */
        foreach ($thumb->thumbnails as $_th) {
            if ($_th->width == $width && $_th->height == $height) {
                $th = $_th;
                break;
            }
        }
        if ($th) {
            return $th;
        }

        $pathResolver = new HashPathResolver('thumbs');
        $fileSystem = new \FileUpload\FileSystem\Simple();
        $fileNameGenerator = new RandomNameGenerator();

        $fileUpload = new FileUpload(null, $_SERVER);
        $fileUpload->setFileNameGenerator($fileNameGenerator);
        $fileUpload->setPathResolver($pathResolver);
        $fileUpload->setFileSystem($fileSystem);


        $thImg = ImageManagerStatic::make($thumb->getFullName());
        $w = $thImg->getWidth();
        $h = $thImg->getHeight();
        if ($width == $height && $w != $h) {// если запросили квадрат, но имеем прямоугольник - вырежем квадрат по центру
            $minSide = min($w, $h);
            $thImg->crop($minSide, $minSide, (int)(($w - $minSide) / 2), (int)(($h - $minSide) / 2));
        }
        $thImg->resize($width, $height);

        $thumbName = $fileNameGenerator->getFileName('thumb', $thumb->mime_type, null, 0, null, $fileUpload);
        $thumbPath = $pathResolver->getUploadPath($thumbName);

        /** @var PathOrganizer $pathThumbOrganizer */
        $pathThumbOrganizer = $pathResolver->getFileData($thumbName);

        $thImg->save($thumbPath);

        $th = new Thumbnails();
        $th->user_id = $thumb->user_id;
        $th->attachment_id = $thumb->id;
        $th->path_id = $pathThumbOrganizer->id;
        $th->width = $width;
        $th->height = $height;
        $th->file = $pathThumbOrganizer->path . '/' . $thumbName;
        $th->mime_type = $thumb->mime_type;
        $th->size = $fileSystem->getFilesize($thumbPath);

        if ($th->save()) {
            $pathManager = $this->getPathManager();
            $pathManager->countUpPath($pathThumbOrganizer);
        } else {
            Yii::error($th->getErrors());
            if ($fileSystem->isFile($thumbPath)) {
                $fileSystem->unlink($thumbPath);
            }
            $th->path_id = $thumb->path_id;
            $th->file = $thumb->file;
            $th->size = $thumb->size;
        }
        return $th;
    }

    public function removeAttachments($attachList)
    {
        $oldFiles = [];
        $attIdList = [];
        /** @var Attachment $att */
        foreach ($attachList as $att) {
            $attIdList[] = $att->id;
            $oldFiles = $this->collectOldFiles($att, $oldFiles);
        }
        try {
            Attachment::deleteAll(['id' => $attIdList]);
            $this->deleteOldFiles($oldFiles);
            return true;
        } catch (\Exception $e) {
            Yii::error('Attachments delete error:', $e->getMessage());
        }

        return false;
    }

    /**
     * @param Attachment $att
     * @param array|null $oldFiles
     * @return array
     */
    private function collectOldFiles($att, $oldFiles = null)
    {
        $res = $oldFiles;
        if (!$res) {
            $res = [];
        }
        $res[$att->path_id][] = $att->file;
        /** @var Thumbnails $th */
        $thumbs = $att->getThumbnails()->all();
        foreach ($thumbs as $th) {
            $res[$th->path_id][] = $th->file;
        }
        return $res;
    }

    private function deleteOldFiles($oldFiles)
    {
        Yii::trace($oldFiles);
        $pathManager = $this->getPathManager();
        $fileSystem = new \FileUpload\FileSystem\Simple();
        foreach ($oldFiles as $pathId => $files) {
            $pathObj = PathOrganizer::findOne($pathId);
            foreach ($files as $file) {
                $f = $pathObj->getBasePath($file);
                Yii::warning('Remove old file: ' . $f);
                if ($fileSystem->isFile($f)) {
                    $fileSystem->unlink($f);
                }
                $pathManager->countDownPath($pathObj);
            }
        }
    }
}
