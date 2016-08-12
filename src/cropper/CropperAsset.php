<?php

namespace karakum\common\cropper;

use yii\web\AssetBundle;

/**
 * CropperAsset
 *
 * @url https://github.com/fengyuanchen/cropper
 */
class CropperAsset extends AssetBundle
{
    public $sourcePath = '@bower';
    public $css = [
        'cropper/dist/cropper.min.css',
    ];
    public $js = [
        'cropper/dist/cropper.min.js',
    ];
    public $depends = [
        'yii\web\JqueryAsset',
    ];
}
