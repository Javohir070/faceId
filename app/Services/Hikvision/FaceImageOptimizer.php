<?php

namespace App\Services\Hikvision;

use GdImage;
use Illuminate\Support\Facades\Log;

class FaceImageOptimizer
{
    /** Avval sifat, keyin o‘lcham kamaytiriladi: terminal yuzni piksellar bo‘yicha taniydi. */
    private const SCALES = [1.0, 0.75, 0.5, 0.35, 0.25];

    private const QUALITIES = [85, 70, 55];

    private const MIN_SIDE = 100;

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

        $image = $this->applyOrientation($image, $binary);
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

    /**
     * GD EXIF ni o‘qimaydi va qayta kodlashda tashlab yuboradi: telefon rasmi yonboshlab
     * qolsa terminal yuzni topolmaydi. exif kengaytmasi serverda bo‘lishi shart emas.
     */
    private function applyOrientation(GdImage $image, string $binary): GdImage
    {
        // imagerotate soat yo‘nalishiga teskari aylantiradi.
        $angle = match ($this->jpegOrientation($binary)) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated === false ? $image : $rotated;
    }

    private function jpegOrientation(string $bytes): int
    {
        if (! $this->isJpeg($bytes)) {
            return 1;
        }

        $length = strlen($bytes);
        $offset = 2;

        while ($offset + 4 <= $length && $bytes[$offset] === "\xFF") {
            $marker = ord($bytes[$offset + 1]);
            $size = unpack('n', substr($bytes, $offset + 2, 2))[1];

            if ($marker === 0xE1 && substr($bytes, $offset + 4, 6) === "Exif\0\0") {
                return $this->tiffOrientation(substr($bytes, $offset + 10, max($size - 8, 0)));
            }

            // SOS dan keyin rasm ma’lumoti boshlanadi, EXIF undan oldin bo‘ladi.
            if ($marker === 0xDA) {
                return 1;
            }

            $offset += 2 + $size;
        }

        return 1;
    }

    private function tiffOrientation(string $tiff): int
    {
        [$short, $long] = match (substr($tiff, 0, 2)) {
            'II' => ['v', 'V'],
            'MM' => ['n', 'N'],
            default => [null, null],
        };

        if ($short === null || strlen($tiff) < 8) {
            return 1;
        }

        $directory = unpack($long, substr($tiff, 4, 4))[1];

        if ($directory + 2 > strlen($tiff)) {
            return 1;
        }

        $entries = unpack($short, substr($tiff, $directory, 2))[1];

        for ($index = 0; $index < $entries; $index++) {
            $entry = substr($tiff, $directory + 2 + $index * 12, 12);

            if (strlen($entry) < 12) {
                return 1;
            }

            if (unpack($short, substr($entry, 0, 2))[1] === 0x0112) {
                return unpack($short, substr($entry, 8, 2))[1];
            }
        }

        return 1;
    }
}
