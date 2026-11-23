<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use App\Rules\Base64Image;
use App\Services\Hikvision\EmployeeSyncService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

/**
 * Integratsiya API (docs/integration-api.md): tashqi tizim panel foydalanuvchisi nomidan xodim qo'shadi,
 * tahrirlaydi, o'chiradi.
 *
 * So'rov davomida foydalanuvchi tizimga kirgan (`api.user`), shuning uchun global scope’lar o'z holicha ishlaydi:
 * xodim — faqat foydalanuvchi bo'limidan, terminal — faqat foydalanuvchiga biriktirilganlari (AssignedDevicesScope).
 *
 * Bu “xodim terminalga faqat Sinxronizatsiya orqali boradi” qoidasidan ataylab qilingan istisno: foydalanuvchi
 * qarori bilan xodim terminallarga darhol yoziladi.
 */
class IntegrationEmployeeService
{
    public function __construct(
        private readonly EmployeeService $employees,
        private readonly EmployeeSyncService $sync,
    ) {}

    /**
     * Sozlangan jadval — umumiy yoki foydalanuvchi bo'limiga tegishli bo'lsa. Aks holda `null` (API 503).
     */
    public function scheduleId(User $user): ?int
    {
        $id = config('integration_api.schedule_id');

        if ($id === null) {
            return null;
        }

        $topildi = Schedule::withoutGlobalScopes()
            ->whereKey((int) $id)
            ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $user->department_id))
            ->value('id');

        return $topildi !== null ? (int) $topildi : null;
    }

    /**
     * @param  array<string, mixed>  $data  StoreIntegrationEmployeeRequest::validated()
     * @return array{employee: Employee, terminals: list<array{id: int, name: string, status: string, message: string}>}
     */
    public function create(User $user, array $data, int $scheduleId): array
    {
        $rasm = $this->rasmFayli((string) $data['face']);

        try {
            $employee = $this->employees->create([
                'last_name' => $data['last_name'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'card' => (string) $data['jshshir'],
                'gender' => (int) $data['gender'],
                'position' => $data['position'] ?? null,
                'schedule_id' => $scheduleId,
                'department_id' => $user->department_id,
                'group_id' => $data['group_id'] ?? null,
                'valid_from_dt' => $this->sana($data['valid_from']),
                'valid_to_dt' => $this->sana($data['valid_to']),
                'face' => $rasm,
            ]);
        } finally {
            @unlink($rasm->getPathname());
        }

        return ['employee' => $employee, 'terminals' => $this->pushToTerminals($employee)];
    }

    /**
     * Faqat yuborilgan maydonlar o'zgaradi. Ismning berilmagan qismlari bazadagidek qoladi — to'liq ism
     * EmployeeService da qismlardan qayta yig'iladi. Keyin xodim terminallarga qayta yoziladi.
     *
     * @param  array<string, mixed>  $data  UpdateIntegrationEmployeeRequest::validated()
     * @return array{employee: Employee, terminals: list<array{id: int, name: string, status: string, message: string}>}
     */
    public function update(Employee $employee, array $data): array
    {
        $yangi = [
            'last_name' => array_key_exists('last_name', $data) ? $data['last_name'] : $employee->last_name,
            'first_name' => array_key_exists('first_name', $data) ? $data['first_name'] : $employee->first_name,
            'middle_name' => array_key_exists('middle_name', $data) ? $data['middle_name'] : $employee->middle_name,
        ];

        foreach (['gender', 'position', 'group_id'] as $maydon) {
            if (array_key_exists($maydon, $data)) {
                $yangi[$maydon] = $data[$maydon];
            }
        }

        if (array_key_exists('valid_from', $data)) {
            $yangi['valid_from_dt'] = $this->sana($data['valid_from']);
        }

        if (array_key_exists('valid_to', $data)) {
            $yangi['valid_to_dt'] = $this->sana($data['valid_to']);
        }

        $rasm = isset($data['face']) ? $this->rasmFayli((string) $data['face']) : null;

        try {
            $employee = $this->employees->update($employee, [...$yangi, ...($rasm ? ['face' => $rasm] : [])]);
        } finally {
            if ($rasm !== null) {
                @unlink($rasm->getPathname());
            }
        }

        return ['employee' => $employee, 'terminals' => $this->pushToTerminals($employee)];
    }

    /**
     * Xodimni u yozilgan **hamma** terminallardan (faqat foydalanuvchiniki emas — panel qoidasi kabi) so'rovning
     * o'zida o'chiradi, keyin bazadan. Biror terminaldan o'chmasa xodim bazada qoladi (`deleted: false`) —
     * navbatga qo'yilmaydi: serverda queue worker ishlamasa, o'chirilgan xodim terminalda qolib, o'taverardi.
     *
     * @return array{deleted: bool, terminals: list<array{id: int, name: string, status: string, message: string}>}
     */
    public function delete(Employee $employee): array
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $terminals = $employee->devices()
            ->withoutGlobalScopes()
            ->orderBy('devices.id')
            ->get()
            ->map(function (Device $device) use ($employee): array {
                $natija = $this->sync->removeFromLinkedDevices($employee, [$device->id]);
                $ochdi = $natija['success'] === 1;

                return [
                    'id' => $device->id,
                    'name' => (string) $device->name,
                    'status' => $ochdi ? 'success' : 'failed',
                    'message' => $ochdi ? 'O‘chirildi' : ($natija['errors'][0]['message'] ?? 'Terminal xodimni o‘chirmadi'),
                ];
            })
            ->values()
            ->all();

        $hammasi = collect($terminals)->every(fn (array $terminal): bool => $terminal['status'] === 'success');

        if ($hammasi) {
            // Terminal bog'lanishlari yuqorida uzildi — EmployeeService navbatga hech narsa qo'ymaydi.
            $this->employees->delete($employee);
        }

        return ['deleted' => $hammasi, 'terminals' => $terminals];
    }

    /**
     * Xodimni foydalanuvchiga biriktirilgan har bir terminalga yozadi (so'rovning o'zida).
     *
     * @return list<array{id: int, name: string, status: string, message: string}>
     */
    public function pushToTerminals(Employee $employee): array
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $employee->loadMissing('face');

        return Device::query()
            ->orderBy('id')
            ->get()
            ->map(function (Device $device) use ($employee): array {
                $natija = $this->sync->transferOne($device, $employee);
                $xabar = (string) ($natija['message'] ?? '');

                return [
                    'id' => $device->id,
                    'name' => (string) $device->name,
                    'status' => (string) $natija['status'],
                    'message' => $xabar === 'device disconnected' ? 'Terminal oflayn' : $xabar,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function employeePayload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'jshshir' => $employee->card,
            'fish' => $employee->name,
            'last_name' => $employee->last_name,
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'gender' => $employee->gender,
            'position' => $employee->position,
            'department_id' => $employee->department_id,
            'group_id' => $employee->group_id,
            'valid_from' => $employee->valid_from_dt?->format('Y-m-d H:i:s'),
            'valid_to' => $employee->valid_to_dt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Zonasiz sana tizim vaqti (`app.timezone`) deb olinadi; `Z` yoki `+03:00` bilan kelgani tizim vaqtiga
     * o'giriladi — aks holda kirish muddati soatlab siljirdi.
     */
    private function sana(mixed $qiymat): string
    {
        $zona = (string) config('app.timezone');

        return Carbon::parse((string) $qiymat, $zona)->setTimezone($zona)->format('Y-m-d H:i:s');
    }

    /** Base64 rasmni EmployeeService kutadigan fayl ko'rinishiga keltiradi (Base64Image tekshirgan). */
    private function rasmFayli(string $base64): UploadedFile
    {
        $baytlar = (string) Base64Image::decode($base64);
        $mime = Base64Image::mime($baytlar);
        $yol = (string) tempnam(sys_get_temp_dir(), 'face');
        file_put_contents($yol, $baytlar);

        return new UploadedFile($yol, $mime === 'image/png' ? 'face.png' : 'face.jpg', $mime, null, true);
    }
}
