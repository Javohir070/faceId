<?php

namespace App\Services\Images;

use GdImage;

/**
 * JPEG ichidagi EXIF burilishini (Orientation) GD rasmiga qo‘llaydi.
 */
class ImageOrientation
{
    /**
     * GD EXIF ni o‘qimaydi va qayta kodlashda tashlab yuboradi: telefon rasmi yonboshlab
     * qolsa terminal yuzni topolmaydi, panel rasmi ham yonboshlab chiqadi. exif kengaytmasi serverda bo‘lishi shart emas.
     */
    public function apply(GdImage $image, string $binary): GdImage
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

    private function isJpeg(string $binary): bool
    {
        return str_starts_with($binary, '���');
    }
}
