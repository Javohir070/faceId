<?php

namespace App\Services\Hikvision;

use App\Exceptions\HikvisionConnectionException;
use App\Models\Device;
use App\Models\Employee;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class HikvisionUserService
{
    private const MAX_PAGES_PER_DEVICE = 100;

    public function __construct(
        private readonly IsapiClient $client,
        private readonly FaceImageOptimizer $optimizer,
    ) {}

    /**
     * Qurilmadagi barcha foydalanuvchilarni UserInfo/Search orqali tortib oladi.
     *
     * @return list<array{employeeNo:string, name:string, beginTime:?string, endTime:?string, picURL:?string, gender:?string}>
     */
    public function fetchAllUsers(Device $device): array
    {
        $users = [];
        $position = 0;
        $pageSize = max((int) config('hikvision.employees_pull_page_size', 30), 1);

        for ($page = 0; $page < self::MAX_PAGES_PER_DEVICE; $page++) {
            $payload = $this->searchUsers($device, $position, $pageSize);
            $items = $this->normalizeUserInfoList(data_get($payload, 'UserInfoSearch.UserInfo', []));
            $status = (string) data_get($payload, 'UserInfoSearch.responseStatusStrg', '');

            foreach ($items as $item) {
                $employeeNo = (string) (data_get($item, 'employeeNo') ?? '');

                if ($employeeNo === '') {
                    continue;
                }

                $users[] = [
                    'employeeNo' => $employeeNo,
                    'name' => (string) (data_get($item, 'name') ?? ''),
                    'beginTime' => data_get($item, 'Valid.beginTime'),
                    'endTime' => data_get($item, 'Valid.endTime'),
                    'picURL' => $this->extractPicUrl($item),
                    'gender' => data_get($item, 'gender'),
                ];
            }

            $matchCount = count($items);
            $position += $matchCount > 0 ? $matchCount : $pageSize;

            if ($status !== 'MORE' || $matchCount === 0) {
                break;
            }
        }

        return $users;
    }

    /**
     * Qurilmadan yuz rasmini base64 qilib qaytaradi (topilmasa null).
     */
    public function downloadFaceBase64(Device $device, string $employeeNo, ?string $picUrl = null): ?string
    {
        $bytes = null;

        if (is_string($picUrl) && trim($picUrl) !== '') {
            $bytes = $this->downloadImageBytes($device, $picUrl);
        }

        if ($bytes === null) {
            $bytes = $this->downloadFaceViaFdSearch($device, $employeeNo);
        }

        if ($bytes === null) {
            $bytes = $this->downloadFaceViaPictureEndpoint($device, $employeeNo);
        }

        if ($bytes === null || $bytes === '') {
            return null;
        }

        if (! $this->looksLikeImage($bytes)) {
            Log::warning('Hikvision: yuz javobi rasm emas', [
                'device_id' => $device->id,
                'employee_no' => $employeeNo,
                'bytes' => strlen($bytes),
            ]);

            return null;
        }

        return base64_encode($bytes);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractPicUrl(array $item): ?string
    {
        foreach (['picURL', 'pictureURL', 'faceURL', 'PersonInfoExtend.faceURL'] as $key) {
            $value = data_get($item, $key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function downloadImageBytes(Device $device, string $urlOrPath): ?string
    {
        $absolute = $this->resolveDeviceMediaUrl($device, $urlOrPath);

        if ($absolute === null) {
            return null;
        }

        // data:image/jpeg;base64,...
        if (str_starts_with($absolute, 'data:image')) {
            $parts = explode(',', $absolute, 2);

            if (count($parts) !== 2) {
                return null;
            }

            $decoded = base64_decode($parts[1], true);

            return $decoded === false ? null : $decoded;
        }

        try {
            $response = $this->client->download($device, $absolute);
        } catch (ConnectionException $exception) {
            Log::warning('Hikvision: yuz URL yuklanmadi', [
                'device_id' => $device->id,
                'url' => $absolute,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $body = $response->body();

        return $body !== '' ? $body : null;
    }

    private function resolveDeviceMediaUrl(Device $device, string $urlOrPath): ?string
    {
        $urlOrPath = trim($urlOrPath);

        if ($urlOrPath === '') {
            return null;
        }

        if (str_starts_with($urlOrPath, 'data:')) {
            return $urlOrPath;
        }

        if (str_starts_with($urlOrPath, 'http://') || str_starts_with($urlOrPath, 'https://')) {
            return $urlOrPath;
        }

        if (str_starts_with($urlOrPath, '/')) {
            return rtrim($device->isapiBaseUrl(), '/').$urlOrPath;
        }

        return rtrim($device->isapiBaseUrl(), '/').'/'.$urlOrPath;
    }

    private function downloadFaceViaFdSearch(Device $device, string $employeeNo): ?string
    {
        try {
            $response = $this->client->request(
                $device,
                'POST',
                '/ISAPI/Intelligent/FDLib/FDSearch',
                ['format' => 'json'],
                [
                    'searchResultPosition' => 0,
                    'maxResults' => 1,
                    'faceLibType' => config('hikvision.face_lib_type', 'blackFD'),
                    'FDID' => (string) config('hikvision.face_lib_id', '1'),
                    'FPID' => $employeeNo,
                ],
            );
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $contentType = (string) $response->header('Content-Type');
        $body = $response->body();

        if (str_contains(strtolower($contentType), 'multipart') || str_contains($body, '--MIME_boundary') || str_contains($body, 'Content-Type: image/')) {
            $fromMultipart = $this->extractImageFromMultipart($body);

            if ($fromMultipart !== null) {
                return $fromMultipart;
            }
        }

        $json = $response->json();
        $match = data_get($json, 'MatchList.0') ?? data_get($json, 'FDSearchResult.MatchList.0');

        if (is_array($match)) {
            $faceUrl = data_get($match, 'faceURL')
                ?? data_get($match, 'picURL')
                ?? data_get($match, 'pictureURL');

            if (is_string($faceUrl) && $faceUrl !== '') {
                return $this->downloadImageBytes($device, $faceUrl);
            }
        }

        return null;
    }

    private function downloadFaceViaPictureEndpoint(Device $device, string $employeeNo): ?string
    {
        $fdid = (string) config('hikvision.face_lib_id', '1');
        $path = '/ISAPI/Intelligent/FDLib/'.$fdid.'/picture/'.$employeeNo;

        try {
            $response = $this->client->download(
                $device,
                $this->client->url($device, $path, ['format' => 'json']),
            );
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $body = $response->body();

        if ($body === '') {
            return null;
        }

        if ($this->looksLikeImage($body)) {
            return $body;
        }

        // Ba’zi qurilmalar JSON ichida faceURL qaytaradi
        $json = json_decode($body, true);

        if (is_array($json)) {
            $faceUrl = data_get($json, 'faceURL')
                ?? data_get($json, 'picURL')
                ?? data_get($json, 'FaceAppendData.faceURL');

            if (is_string($faceUrl) && $faceUrl !== '') {
                return $this->downloadImageBytes($device, $faceUrl);
            }
        }

        return null;
    }

    private function extractImageFromMultipart(string $body): ?string
    {
        if (preg_match('/Content-Type:\s*image\/[a-z0-9+.-]+\s*(?:\r?\n(?:Content-[^\r\n]+\r?\n)*)?\r?\n([\s\S]*?)(?=\r?\n--)/i', $body, $matches)) {
            $image = $matches[1];

            // Boundary oldidagi CRLF ni olib tashlash
            $image = rtrim($image, "\r\n");

            return $image !== '' ? $image : null;
        }

        return null;
    }

    private function looksLikeImage(string $bytes): bool
    {
        if (strlen($bytes) < 24) {
            return false;
        }

        // JPEG
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return true;
        }

        // PNG
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return true;
        }

        // GIF
        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return true;
        }

        // WebP
        if (str_starts_with($bytes, 'RIFF') && str_contains(substr($bytes, 0, 16), 'WEBP')) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function searchUsers(Device $device, int $position = 0, ?int $maxResults = null): array
    {
        $maxResults ??= max((int) config('hikvision.employees_pull_page_size', 30), 1);

        try {
            $response = $this->client->request(
                $device,
                'POST',
                '/ISAPI/AccessControl/UserInfo/Search',
                ['format' => 'json'],
                [
                    'UserInfoSearchCond' => [
                        'searchID' => 'biocontrol-users-'.$device->id.'-'.$position,
                        'searchResultPosition' => $position,
                        'maxResults' => $maxResults,
                    ],
                ],
            );
        } catch (ConnectionException $exception) {
            throw HikvisionConnectionException::fromThrowable($device, $exception);
        }

        if ($response->failed()) {
            throw new \RuntimeException(
                'UserInfo Search javob bermadi: HTTP '.$response->status().' — '.$response->body(),
                $response->status(),
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @param  mixed  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeUserInfoList(mixed $items): array
    {
        if (! is_array($items) || $items === []) {
            return [];
        }

        if (array_key_exists('employeeNo', $items)) {
            return [$items];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    public function addOrUpdateUser(Device $device, Employee $employee): bool
    {
        $employee->loadMissing('face');

        if ($this->addUser($device, $employee)) {
            return $this->syncFace($device, $employee);
        }

        if ($this->updateUser($device, $employee)) {
            return $this->syncFace($device, $employee);
        }

        return false;
    }

    public function addUser(Device $device, Employee $employee): bool
    {
        $response = $this->client->request(
            $device,
            'POST',
            '/ISAPI/AccessControl/UserInfo/Record',
            ['format' => 'json'],
            ['UserInfo' => $this->buildUserPayload($employee)],
        );

        $body = $response->body();

        if ($response->successful()) {
            Log::info('Hikvision: user qo‘shildi', [
                'device_id' => $device->id,
                'employee_no' => $employee->card,
            ]);

            return true;
        }

        if ($response->status() === 400 && str_contains($body, 'employeeNoAlreadyExist')) {
            return false;
        }

        Log::error('Hikvision: user qo‘shishda xatolik', [
            'device_id' => $device->id,
            'employee_no' => $employee->card,
            'status' => $response->status(),
            'body' => $body,
        ]);

        return false;
    }

    public function updateUser(Device $device, Employee $employee): bool
    {
        $response = $this->client->request(
            $device,
            'PUT',
            '/ISAPI/AccessControl/UserInfo/Modify',
            ['format' => 'json'],
            ['UserInfo' => $this->buildUserPayload($employee)],
        );

        if ($response->successful()) {
            Log::info('Hikvision: user yangilandi', [
                'device_id' => $device->id,
                'employee_no' => $employee->card,
            ]);

            return true;
        }

        Log::error('Hikvision: user yangilashda xatolik', [
            'device_id' => $device->id,
            'employee_no' => $employee->card,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    public function deleteUser(Device $device, string $employeeNo): bool
    {
        $response = $this->client->request(
            $device,
            'PUT',
            '/ISAPI/AccessControl/UserInfo/Delete',
            ['format' => 'json'],
            [
                'UserInfoDelCond' => [
                    'EmployeeNoList' => [
                        ['employeeNo' => $employeeNo],
                    ],
                ],
            ],
        );

        $deleted = $response->successful();

        if ($deleted) {
            $this->deleteFace($device, $employeeNo);
            Log::info('Hikvision: user o‘chirildi', [
                'device_id' => $device->id,
                'employee_no' => $employeeNo,
            ]);
        } else {
            Log::error('Hikvision: user o‘chirishda xatolik', [
                'device_id' => $device->id,
                'employee_no' => $employeeNo,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $deleted;
    }

    public function syncFace(Device $device, Employee $employee): bool
    {
        $face = $employee->face?->face;

        if ($face === null || $face === '') {
            return true;
        }

        $binary = base64_decode($face, true);

        if ($binary === false) {
            Log::warning('Hikvision: yuz base64 decode xatoligi', [
                'employee_id' => $employee->id,
            ]);

            return false;
        }

        // Avval yuklash — muvaffaqiyatsiz bo‘lsa eski yuz o‘chib ketmasin.
        if ($this->uploadFace($device, $employee->card, $binary)) {
            return true;
        }

        // Ba’zi qurilmalar mavjud yuz ustiga yozishni rad etadi — o‘chirib qayta urinish.
        $this->deleteFace($device, $employee->card);

        return $this->uploadFace($device, $employee->card, $binary);
    }

    public function uploadFace(Device $device, string $employeeNo, string $imageBytes): bool
    {
        $optimized = $this->optimizer->optimize($imageBytes);

        $faceDataRecord = [
            'faceLibType' => config('hikvision.face_lib_type', 'blackFD'),
            'FDID' => config('hikvision.face_lib_id', '1'),
            'FPID' => $employeeNo,
        ];

        $response = $this->client->uploadMultipart(
            $device,
            '/ISAPI/Intelligent/FDLib/FDSetUp',
            ['format' => 'json'],
            $faceDataRecord,
            $optimized,
            $employeeNo.'.jpg',
        );

        if ($response->successful()) {
            Log::info('Hikvision: yuz yuklandi', [
                'device_id' => $device->id,
                'employee_no' => $employeeNo,
            ]);

            return true;
        }

        Log::error('Hikvision: yuz yuklashda xatolik', [
            'device_id' => $device->id,
            'employee_no' => $employeeNo,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    public function deleteFace(Device $device, string $employeeNo): bool
    {
        $response = $this->client->request(
            $device,
            'PUT',
            '/ISAPI/Intelligent/FDLib/FDDelete',
            ['format' => 'json'],
            [
                'FaceDelete' => [
                    'FDID' => config('hikvision.face_lib_id', '1'),
                    'FPID' => $employeeNo,
                ],
            ],
        );

        return $response->successful();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserPayload(Employee $employee): array
    {
        $doorNo = (int) config('hikvision.door_no', 1);

        return [
            'employeeNo' => $employee->card,
            'name' => $employee->name,
            'userType' => 'normal',
            'Valid' => [
                'enable' => true,
                'beginTime' => $employee->valid_from_dt->format('Y-m-d\TH:i:s'),
                'endTime' => $employee->valid_to_dt->format('Y-m-d\TH:i:s'),
                'timeType' => 'local',
            ],
            'RightPlan' => [
                [
                    'doorNo' => $doorNo,
                    'planTemplateNo' => (string) config('hikvision.plan_template_no', '1'),
                ],
            ],
            'doorRight' => (string) $doorNo,
            'gender' => match ($employee->gender) {
                Employee::GENDER_MALE => 'male',
                Employee::GENDER_FEMALE => 'female',
                default => 'unknown',
            },
            'localUIRight' => false,
            'userVerifyMode' => '',
            'userLevel' => 'Employee',
        ];
    }
}
