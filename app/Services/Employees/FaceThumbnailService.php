<?php

namespace App\Services\Employees;

use App\Services\Images\ImageOrientation;

/**
 * Ro‘yxat va panellar uchun yuzning kichik kvadrat nusxasi. Bazadagi asl rasm o‘zgarmaydi;
 * 500 ta xodimli panelda asl rasmlar o‘nlab megabayt bo‘lardi.
 */
class FaceThumbnailService
{
    public function __construct(
        private readonly ImageOrientation $orientation,
    ) {}

    public function make(string $face, int $size = 240): ?string
    {
        $binary = base64_decode(str_contains($face, ',') && str_starts_with($face, 'data:') ? explode(',', $face, 2)[1] : $face, true);

        if ($binary === false || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return null;
        }

        $image = $this->orientation->apply($image, $binary);

        // O‘rtadan kvadrat kesiladi: yuz odatda rasm markazida.
        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $thumbnail = imagecreatetruecolor($size, $size);
        imagecopyresampled(
            $thumbnail,
            $image,
            0,
            0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            $size,
            $size,
            $side,
            $side,
        );

        ob_start();
        imagejpeg($thumbnail, null, 82);
        $output = ob_get_clean();

        return is_string($output) && $output !== '' ? $output : null;
    }
}
