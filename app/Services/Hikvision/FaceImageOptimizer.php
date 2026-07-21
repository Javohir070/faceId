<?php

namespace App\Services\Hikvision;

use Illuminate\Support\Facades\Log;

class FaceImageOptimizer
{
    public function optimize(string $binary): string
    {
        $maxBytes = (int) config('hikvision.max_face_image_bytes', 512 * 1024);

        if (strlen($binary) <= $maxBytes) {
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

        $width = imagesx($image);
        $height = imagesy($image);
        $newWidth = max((int) ($width / 2), 100);
        $newHeight = max((int) ($height / 2), 100);

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        $quality = 0.7;
        $compressed = $this->compressJpeg($resized, $quality);

        while (strlen($compressed) > $maxBytes && $quality > 0.1) {
            $quality -= 0.15;
            $compressed = $this->compressJpeg($resized, $quality);
        }

        imagedestroy($resized);

        return $compressed;
    }

    private function compressJpeg(\GdImage $image, float $quality): string
    {
        ob_start();
        imagejpeg($image, null, (int) round($quality * 100));
        $output = ob_get_clean();

        return is_string($output) ? $output : '';
    }
}
