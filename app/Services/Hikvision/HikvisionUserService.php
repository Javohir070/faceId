<?php

namespace App\Services\Hikvision;

use App\Models\Device;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;

class HikvisionUserService
{
    public function __construct(
        private readonly IsapiClient $client,
        private readonly FaceImageOptimizer $optimizer,
    ) {}

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
            'gender' => 'unknown',
            'localUIRight' => false,
            'userVerifyMode' => '',
            'userLevel' => 'Employee',
        ];
    }
}
