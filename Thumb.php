<?php


namespace Source\Support;

use Exception;
use Intervention\Image\ImageManager;

/**
 * Class Thumb
 * @package Source\Support
 */
class Thumb
{
    /** @var ImageManager */
    private $imageMaker;

    /** @var String */
    private $cachePath;

    /** @var string */
    private $imagePath;

    /** @var string */
    private $imageName;

    /** @var string */
    private $imageMime;

    /** @var string */
    private $imageInfo;

    /** @var array */
    private static $allowedExt = ['image/jpeg', "image/png"];

    /**
     * Thumb constructor.
     * @throws Exception
     */
    public function __construct()
    {
        $this->imageMaker = new ImageManager();
        $this->cachePath = CONF_IMAGE_CACHE;

        if (!file_exists($this->cachePath) || !is_dir($this->cachePath)) {
            if (!mkdir($this->cachePath, 0755)) {
                throw new Exception("Could not create cache folder");
            }
        }
    }

    /**
     * @param string $image
     * @param int $width
     * @param int|null $height
     * @return string
     */
    public function make(string $image, int $width, ?int $height = null): string
    {
        if (!file_exists($image)) {
            return "Image not found";
        }

        $height = (empty($height) ? $width / 1.777 : $height);
        $height = (int)$height;

        $this->imagePath = $image;
        $this->imageMime = mime_content_type($this->imagePath);
        $this->imageInfo = pathinfo($this->imagePath);

        if (!in_array($this->imageMime, self::$allowedExt)) {
            return "Not a valid JPG or PNG image";
        }

        $this->imageName = $this->name($this->imagePath, $width, $height);
        if (file_exists("{$this->cachePath}/{$this->imageName}") && is_file("{$this->cachePath}/{$this->imageName}")) {
            return "{$this->cachePath}/{$this->imageName}";
        }

        return $this->imageMaker->make($image)
            ->fit($width, $height, function ($constraint) {
                $constraint->upsize();
            })
            ->save("{$this->cachePath}/{$this->imageName}", CONF_IMAGE_QUALITY['jpg'])
            ->basePath();
    }

    /**
     * @param string $image
     * @param int|null $width
     * @param int|null $height
     * @return string
     */
    public function name(string $image, int $width = null, int $height = null): string
    {
        $filterName = filter_var(mb_strtolower(pathinfo($image)["filename"]), FILTER_SANITIZE_STRIPPED);
        $formats = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜüÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿRr"!@#$%&*()_-+={[}]/?;:.,\\\'<>°ºª';
        $replace = 'aaaaaaaceeeeiiiidnoooooouuuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr                                 ';
        $trimName = trim(strtr(utf8_decode($filterName), utf8_decode($formats), $replace));
        $name = str_replace(["-----", "----", "---", "--"], "-", str_replace(" ", "-", $trimName));

        $hash = $this->hash($this->imagePath);
        $ext = ($this->imageMime == "image/jpeg" ? ".jpg" : ".png");
        $widthName = ($width ? "-{$width}" : "");
        $heightName = ($height ? "x{$height}" : "");

        return "{$name}{$widthName}{$heightName}-{$hash}{$ext}";
    }

    /**
     * @param string $path
     * @return string
     */
    protected function hash(string $path): string
    {
        return hash("crc32", pathinfo($path)['basename']);
    }

    /* @param string|null $imagePath
     * @example $t->flush("images/image.jpg"); clear image name and variations size
     * @example $t->flush(); clear all image cache folder
     */
    public function flush(string $imagePath = null): void
    {
        foreach (scandir($this->cachePath) as $file) {
            $file = "{$this->cachePath}/{$file}";
            if ($imagePath && strpos($file, $this->hash($imagePath))) {
                $this->imageDestroy($file);
            } elseif (!$imagePath) {
                $this->imageDestroy($file);
            }
        }
    }

    /**
     * @param string $imagePatch
     */
    private function imageDestroy(string $imagePatch): void
    {
        if (file_exists($imagePatch) && is_file($imagePatch)) {
            unlink($imagePatch);
        }
    }
}