<?php

namespace Tests\Feature\Hikvision;

use App\Services\Hikvision\FaceImageOptimizer;
use GdImage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FaceImageOptimizerTest extends TestCase
{
    private const LIMIT = 200 * 1024;

    public function test_small_jpeg_is_sent_unchanged(): void
    {
        $jpeg = $this->encode($this->noise(100, 100), 80);

        $this->assertLessThan(self::LIMIT, strlen($jpeg));
        $this->assertSame($jpeg, app(FaceImageOptimizer::class)->optimize($jpeg));
    }

    public function test_photo_above_the_terminal_limit_is_compressed_to_a_jpeg_below_it(): void
    {
        $jpeg = $this->encode($this->noise(700, 700), 90);

        $this->assertGreaterThan(self::LIMIT, strlen($jpeg));

        $optimized = app(FaceImageOptimizer::class)->optimize($jpeg);

        $this->assertLessThanOrEqual(self::LIMIT, strlen($optimized));
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($optimized)[2]);
    }

    public function test_photo_keeps_its_size_when_lower_quality_is_enough(): void
    {
        mt_srand(42);
        $image = imagecreatetruecolor(1400, 1400);

        for ($y = 0; $y < 1400; $y++) {
            for ($x = 0; $x < 1400; $x++) {
                $level = 100 + mt_rand(-5, 5);
                imagesetpixel($image, $x, $y, ($level << 16) | ($level << 8) | $level);
            }
        }

        $jpeg = $this->encode($image, 100);

        $this->assertGreaterThan(self::LIMIT, strlen($jpeg));

        $optimized = app(FaceImageOptimizer::class)->optimize($jpeg);

        $this->assertLessThanOrEqual(self::LIMIT, strlen($optimized));
        $this->assertSame(1400, getimagesizefromstring($optimized)[0]);
    }

    public function test_small_png_is_converted_to_jpeg(): void
    {
        ob_start();
        imagepng($this->noise(60, 60));
        $png = (string) ob_get_clean();

        $optimized = app(FaceImageOptimizer::class)->optimize($png);

        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($optimized)[2]);
    }

    public function test_unreadable_bytes_are_returned_unchanged(): void
    {
        $this->assertSame('rasm-emas', app(FaceImageOptimizer::class)->optimize('rasm-emas'));
    }

    /**
     * @return array<string, array{int, string, bool, bool}>
     */
    public static function orientations(): array
    {
        return [
            'oddiy' => [1, 'II', false, true],
            '180 daraja' => [3, 'MM', false, false],
            'soat yo‘nalishida 90, little-endian' => [6, 'II', true, true],
            'soat yo‘nalishida 90, big-endian' => [6, 'MM', true, true],
            'soatga teskari 90' => [8, 'II', true, false],
        ];
    }

    /**
     * Rasmning chap yarmi qora, o‘ng yarmi oq: burilishning yo‘nalishi ham tekshiriladi.
     */
    #[DataProvider('orientations')]
    public function test_exif_orientation_survives_reencoding(int $orientation, string $byteOrder, bool $portrait, bool $darkFirst): void
    {
        $image = imagecreatetruecolor(900, 500);

        for ($y = 0; $y < 500; $y++) {
            for ($x = 0; $x < 900; $x++) {
                $level = $x < 450 ? mt_rand(0, 60) : mt_rand(195, 255);
                imagesetpixel($image, $x, $y, ($level << 16) | (mt_rand(0, 255) << 8) | $level);
            }
        }

        $jpeg = $this->withExifOrientation($this->encode($image, 90), $orientation, $byteOrder);

        $this->assertGreaterThan(self::LIMIT, strlen($jpeg));

        $optimized = app(FaceImageOptimizer::class)->optimize($jpeg);
        [$width, $height] = getimagesizefromstring($optimized);

        $this->assertSame($portrait, $height > $width);

        $result = imagecreatefromstring($optimized);
        [$first, $second] = $portrait
            ? [$this->red($result, (int) ($width / 2), (int) ($height / 8)), $this->red($result, (int) ($width / 2), (int) ($height * 7 / 8))]
            : [$this->red($result, (int) ($width / 8), (int) ($height / 2)), $this->red($result, (int) ($width * 7 / 8), (int) ($height / 2))];

        $this->assertSame($darkFirst, $first < $second);
    }

    private function noise(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }

        return $image;
    }

    private function encode(GdImage $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }

    private function red(GdImage $image, int $x, int $y): int
    {
        return (imagecolorat($image, $x, $y) >> 16) & 0xFF;
    }

    private function withExifOrientation(string $jpeg, int $orientation, string $byteOrder): string
    {
        [$short, $long] = $byteOrder === 'II' ? ['v', 'V'] : ['n', 'N'];

        $tiff = $byteOrder.pack($short, 42).pack($long, 8)
            .pack($short, 1)
            .pack($short, 0x0112).pack($short, 3).pack($long, 1).pack($short, $orientation).pack($short, 0)
            .pack($long, 0);
        $payload = "Exif\0\0".$tiff;

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
    }
}
