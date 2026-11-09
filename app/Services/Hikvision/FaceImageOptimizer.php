<?php

namespace App\Services\Hikvision;

use App\Services\Images\ImageOrientation;
use GdImage;
use Illuminate\Support\Facades\Log;

class FaceImageOptimizer
{
    /** Avval sifat, keyin o‘lcham kamaytiriladi: terminal yuzni piksellar bo‘yicha taniydi. */
    private const SCALES = [1.0, 0.75, 0.5, 0.35, 0.25];

    private const QUALITIES = [85, 70, 55];

    private const MIN_SIDE = 100;

    public function __construct(
        private readonly ImageOrientation $orientation,
    ) {}

    public function optimize(string $binary): string
    {
        $maxBytes = (int) config('hikvision.max_face_image_bytes', 200 * 1024);

        if (strlen($binary) <= $maxBytes && $this->isJpeg($binary)) {
            return $binary;
        }

        if (! function_exists('imagecreatefromstring')) {
            Log::warning('FaceImageOptimizer: GD extension yo‘q, rasm siqilmadi.');

            return $binary;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return $binary;
        }

        $image = $this->orientation->apply($image, $binary);
        $compressed = $binary;

        foreach (self::SCALES as $scale) {
            $scaled = $this->scale($image, $scale);

            foreach (self::QUALITIES as $quality) {
                $compressed = $this->compressJpeg($scaled, $quality);

                if (strlen($compressed) <= $maxBytes) {
                    return $compressed;
                }
            }
        }

        return $compressed;
    }

    private function isJpeg(string $binary): bool
    {
        return str_starts_with($binary, "\xFF\xD8\xFF");
    }

    private function scale(GdImage $image, float $scale): GdImage
    {
        if ($scale === 1.0) {
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $newWidth = max((int) ($width * $scale), self::MIN_SIDE);
        $newHeight = max((int) ($height * $scale), self::MIN_SIDE);

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private function compressJpeg(GdImage $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);
        $output = ob_get_clean();

        return is_string($output) ? $output : '';
    }
}
