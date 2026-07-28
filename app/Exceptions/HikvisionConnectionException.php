<?php

namespace App\Exceptions;

use App\Models\Device;
use Throwable;

class HikvisionConnectionException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly Device $device,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromThrowable(Device $device, Throwable $previous): self
    {
        $raw = $previous->getMessage();
        $hints = [];

        if (str_contains($raw, 'Connection was reset')
            || str_contains($raw, 'error 56')
            || str_contains($raw, 'Empty reply from server')) {
            $hints[] = 'Port yoki protokol (HTTP/HTTPS) noto‘g‘ri bo‘lishi mumkin.';
            $hints[] = 'Hikvision ISAPI odatda port 80 (HTTP) yoki 443 (HTTPS) da ishlaydi — 8000 ko‘pincha boshqa xizmat uchun.';
            $hints[] = 'Qurilma veb-interfeysiga brauzerda kirib, qaysi port/protokol ishlashini tekshiring.';
        }

        if (str_contains($raw, 'Connection refused')
            || str_contains($raw, 'error 7')
            || str_contains($raw, 'Failed to connect')
            || str_contains($raw, 'timed out')
            || str_contains($raw, 'Operation timed out')
            || str_contains($raw, 'error 28')) {
            $hints[] = 'Qurilma javob bermadi — IP, tarmoq yoki qurilma yoqilganligini tekshiring.';
        }

        // SSL hint faqat haqiqatan HTTPS urinishida (use_https=true).
        if ($device->use_https && (
            str_contains($raw, 'SSL')
            || str_contains($raw, 'certificate')
            || str_contains($raw, 'error 60')
        )) {
            $hints[] = 'HTTPS sertifikat xatosi — formasida HTTPS belgilanganligini va port 443 ni tekshiring.';
        }

        if ($hints === []) {
            $hints[] = 'Login, parol va IP manzilni tekshiring.';
        }

        $message = implode(' ', $hints).' ['.$device->isapiBaseUrl().']';

        return new self($message, $device, $previous);
    }

    public function device(): Device
    {
        return $this->device;
    }
}
