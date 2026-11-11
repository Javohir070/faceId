<?php

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Example;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * /docs/api sahifasida eski tizim API’sini (POST /api/legacy) hujjatlaydi.
 *
 * LegacyApiController tanani $request->getContent() dan o‘qigani uchun Scramble so‘rov maydonlarini o‘zi
 * topa olmaydi, RestrictLegacyApiIps qaytaradigan 403 ni ham ko‘rmaydi. Shu yerda har bir action uchun
 * so‘rov va javob namunasi beriladi. LegacyApiService yoki docs/legacy-api.md o‘zgarsa, buni ham yangilang.
 */
final class LegacyApiDocumentation implements OperationTransformer
{
    public const ROUTE_NAME = 'api.legacy';

    private const DESCRIPTION = <<<'MD'
        Eski Biocontrol tizimining `api.php` siga ulangan tashqi tizimlar uchun. So‘rov va javob formati eskisidek.

        - **So‘rov:** `POST`, tanasi JSON, bajariladigan amal `action` maydonida. Har bir amal uchun tayyor namuna bor: o‘ngdagi **Body** oynasida **Examples** ro‘yxatidan tanlang.
        - **Javob:** ruxsat berilgan IP dan har doim HTTP 200. Natija `status` maydonida (`true` / `false`), xabarlar `alerts` da.
        - Bo‘sh tana, buzuq JSON yoki noma’lum `action` uchun `{"status": false}`. Kutilmagan server xatosida `{"status": false, "alerts": ["Internal error"]}`.
        - **Kirish faqat `LEGACY_API_ALLOWED_IPS` dagi IP lardan.** Boshqa IP dan `403 {"status": false, "alerts": ["Access denied"]}` qaytadi. Shu sahifadagi «Send API Request» so‘rovni brauzeringizdan yuboradi, shuning uchun sizning IP ingiz ham ro‘yxatda bo‘lishi kerak.

        | action | Nima qiladi | Majburiy maydonlar |
        |---|---|---|
        | `add_employee` | Xodimni JSHSHIR bo‘yicha yaratadi yoki yangilaydi va **darhol barcha terminallarga yozadi** | `name`, `template`, `start`, `end` |
        | `delete_employee` | Xodimlarni avval terminallardan, keyin bazadan o‘chiradi | `cards` |
        | `attendance` | Bugungi birinchi kirish va 09:15 / 09:30 belgilari | — |
        | `add_note` | Kechikish, erta ketish yoki kelmaslik izohi | `card`, `type`, `dt`, `note` |
        | `get_reports` | Kirish va chiqish hodisalari | `start`, `end` |
        | `get_work_time` | Kunlik ishlagan vaqt | `start`, `end` |

        Vaqtlar Toshkent vaqti bilan.
        MD;

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if ($routeInfo->route->getName() !== self::ROUTE_NAME) {
            return;
        }

        // Route::any: Scramble birinchi metodni (GET) ko‘rsatadi, tashqi tizimlar esa POST yuboradi.
        $operation->setMethod('post');
        $operation->summary('Eski tizim API’si');
        $operation->description(self::DESCRIPTION);
        $operation->setTags(['Eski API']);

        $operation->addRequestBodyObject(
            RequestBodyObject::make()
                ->required()
                ->addContent('application/json', new MediaType(
                    schema: Schema::fromType($this->requestSchema()),
                    examples: $this->requestExamples(),
                )),
        );

        $inferredOk = collect($operation->responses ?? [])
            ->first(fn ($response): bool => $response instanceof Response && (string) $response->code === '200');

        $operation->responses = [
            Response::make(200)
                ->setDescription('Natija `status` maydonida. Har bir action uchun namunani ro‘yxatdan tanlang.')
                ->setHeaders($inferredOk instanceof Response ? $inferredOk->headers : [])
                ->addContent('application/json', new MediaType(
                    schema: Schema::fromType($this->responseSchema()),
                    examples: $this->responseExamples(),
                )),
            Response::make(403)
                ->setDescription('So‘rov `LEGACY_API_ALLOWED_IPS` da yo‘q IP dan keldi yoki ro‘yxat bo‘sh.')
                ->addContent('application/json', new MediaType(
                    schema: Schema::fromType($this->object([
                        'status' => (new BooleanType)->const(false),
                        'alerts' => (new ArrayType)->setItems(new StringType),
                    ], ['status', 'alerts'])),
                    example: ['status' => false, 'alerts' => ['Access denied']],
                )),
        ];
    }

    private function requestSchema(): ObjectType
    {
        return $this->object([
            'action' => $this->string('Bajariladigan amal.')
                ->enum(['add_employee', 'delete_employee', 'attendance', 'add_note', 'get_reports', 'get_work_time']),
            'name' => $this->string('**add_employee**, majburiy: xodimning F.I.Sh.'),
            'card' => $this->string(
                '**add_employee:** JSHSHIR (terminaldagi `employeeNo`), 20 belgigacha. Berilmasa 8 xonali tasodifiy raqam beriladi. '
                .'Bazada bor bo‘lsa xodim yangilanadi, bo‘limi, jadvali va guruhi o‘zgarmaydi.  '."\n"
                .'**add_note**, majburiy: izoh yoziladigan xodimning JSHSHIR i.  '."\n"
                .'**get_reports**, **get_work_time:** ixtiyoriy filtr, bo‘sh bo‘lsa hamma xodim.'
            ),
            'template' => (new IntegerType)->enum([1, 2])->setDescription(
                '**add_employee**, majburiy: `1` Authorized, `2` Blocked. '
                .'Blocked xodim terminalda qoladi, lekin o‘tolmaydi: amal qilish muddati shu daqiqada tugaydi.'
            ),
            'face' => $this->string(
                '**add_employee:** yuz rasmi, base64 JPEG yoki PNG (`data:image/jpeg;base64,` prefiksi bilan ham bo‘ladi). '
                .'Rasm bo‘lmasa xodim bazaga yoziladi, lekin terminalga yozilmaydi va bu xato sifatida qaytadi.'
            ),
            'start' => $this->string(
                '**add_employee**, majburiy: amal qilish boshlanishi, `YYYY-MM-DD HH:MM:SS`.  '."\n"
                .'**get_reports**, majburiy: oraliq boshi, `YYYY-MM-DD HH:MM:SS`.  '."\n"
                .'**get_work_time**, majburiy: birinchi kun, `YYYY-MM-DD`.'
            ),
            'end' => $this->string(
                '**add_employee**, majburiy: amal qilish tugashi, `YYYY-MM-DD HH:MM:SS`.  '."\n"
                .'**get_reports**, majburiy: oraliq oxiri, `YYYY-MM-DD HH:MM:SS`.  '."\n"
                .'**get_work_time**, majburiy: oxirgi kun, `YYYY-MM-DD`.'
            ),
            'cards' => (new ArrayType)->setItems(new StringType)->setDescription(
                '**delete_employee**, majburiy: o‘chiriladigan xodimlarning JSHSHIR lari. Topilmaganlari o‘tkazib yuboriladi.  '."\n"
                .'**attendance:** ixtiyoriy filtr, bo‘lmasa hamma xodim.'
            ),
            'type' => $this->string(
                '**add_note**, majburiy: `came_late` kechikdi, `left_early` erta ketdi, `absent` kelmadi.  '."\n"
                .'**get_reports:** ixtiyoriy, `1` kirish, `2` chiqish, bo‘sh bo‘lsa hammasi.'
            ),
            'dt' => $this->string('**add_note**, majburiy: izoh sanasi, `YYYY-MM-DD`.'),
            'note' => $this->string('**add_note**, majburiy: izoh matni. Shu xodim, kun va turga izoh bor bo‘lsa matni yangilanadi.'),
            'ip' => $this->string('**get_reports:** ixtiyoriy, terminal IP manzili bo‘yicha filtr.'),
        ], ['action']);
    }

    /**
     * @return array<string, Example>
     */
    private function requestExamples(): array
    {
        return [
            'add_employee' => new Example(
                value: [
                    'action' => 'add_employee',
                    'name' => 'Ali Valiyev',
                    'card' => '12345678901234',
                    'template' => 1,
                    'face' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ...',
                    'start' => '2026-01-01 00:00:00',
                    'end' => '2027-12-31 23:59:59',
                ],
                summary: 'add_employee: xodim qo‘shish yoki yangilash',
                description: '`face` qisqartirilgan: yuborishdan oldin to‘liq base64 rasm qo‘ying. '
                    .'Yuborilsa xodim haqiqatan yaratiladi va barcha terminallarga yoziladi.',
            ),
            'delete_employee' => new Example(
                value: ['action' => 'delete_employee', 'cards' => ['12345678901234']],
                summary: 'delete_employee: xodimlarni o‘chirish',
                description: 'Yuborilsa xodim haqiqatan terminallardan va bazadan o‘chiriladi.',
            ),
            'attendance' => new Example(
                value: ['action' => 'attendance', 'cards' => ['12345678901234']],
                summary: 'attendance: bugungi davomat',
                description: '`cards` ni olib tashlasangiz hamma xodim qaytadi.',
            ),
            'add_note' => new Example(
                value: [
                    'action' => 'add_note',
                    'card' => '12345678901234',
                    'type' => 'came_late',
                    'dt' => '2026-11-11',
                    'note' => 'Shifoxonada',
                ],
                summary: 'add_note: izoh qo‘shish',
            ),
            'get_reports' => new Example(
                value: [
                    'action' => 'get_reports',
                    'card' => '',
                    'ip' => '',
                    'type' => '',
                    'start' => '2026-11-01 00:00:00',
                    'end' => '2026-11-30 23:59:59',
                ],
                summary: 'get_reports: kirish-chiqish hodisalari',
            ),
            'get_work_time' => new Example(
                value: ['action' => 'get_work_time', 'card' => '', 'start' => '2026-11-01', 'end' => '2026-11-30'],
                summary: 'get_work_time: ishlagan vaqt',
            ),
        ];
    }

    private function responseSchema(): ObjectType
    {
        $deviceError = $this->object([
            'status' => (new BooleanType)->const(false),
            'message' => $this->string('`IP: <terminal>; ID: <xodim id>; Error: <terminal xatosi>`'),
        ])->setDescription('add_employee: terminalga yozishda xato');

        return $this->object([
            'status' => (new BooleanType)->setDescription('So‘rov bajarildimi.'),
            'alerts' => (new ArrayType)
                ->setItems((new AnyOf)->setItems([
                    $this->string('Xabar, masalan `Saved successfully` yoki `Enter name`.'),
                    $deviceError,
                ]))
                ->setDescription('Faqat xabar bo‘lganda chiqadi.'),
            'attendance' => (new ArrayType)->setItems($this->object([
                'name' => new StringType,
                'card' => new StringType,
                'note_type' => $this->string('Bugungi izoh turi: `came_late`, `left_early`, `absent` yoki `null`.')->nullable(true),
                'note' => $this->string('Izoh matni yoki `null`.')->nullable(true),
                'dt' => $this->string('Bugungi birinchi kirish, `YYYY-MM-DD HH:MM:SS`. Kelmagan bo‘lsa `null`.')->nullable(true),
                'before_9_15' => (new BooleanType)->setDescription('09:16:00 dan oldin kelgan.'),
                'between_9_15_9_30' => (new BooleanType)->setDescription('09:16:00–09:30:00 orasida kelgan.'),
                'after_9_30' => (new BooleanType)->setDescription('09:30:00 dan keyin kelgan.'),
                'absent' => (new BooleanType)->setDescription('Bugun kirish yo‘q.'),
            ]))->setDescription('Faqat **attendance** javobida.'),
            'reports' => (new ArrayType)->setItems($this->object([
                'id' => $this->string('Hodisa id si (satr).'),
                'card' => $this->string('Xodim JSHSHIR i.')->nullable(true),
                'ip' => $this->string('Terminal IP manzili.')->nullable(true),
                'type' => $this->string('`1` kirish, `2` chiqish (satr).'),
                'dt' => $this->string('`YYYY-MM-DD HH:MM:SS`'),
            ]))->setDescription('Faqat **get_reports** javobida, vaqt bo‘yicha tartiblangan.'),
            'work_time' => (new ArrayType)->setItems($this->object([
                'card' => new StringType,
                'days' => (new ObjectType)->setDescription(
                    'Kalit: sana (`YYYY-MM-DD`). Qiymat: `HH:MM:SS` (oxirgi chiqish minus birinchi kirish) '
                    .'yoki `0` (kirish yoki chiqish yo‘q). Chiqish kirishdan oldin bo‘lsa manfiy (`-01:00:00`) va jamiga qo‘shilmaydi.'
                ),
                'total' => $this->string('Jami, `HH:MM:SS`.'),
            ]))->setDescription('Faqat **get_work_time** javobida. Faqat shu oraliqda hodisasi bor xodimlar.'),
        ], ['status']);
    }

    /**
     * @return array<string, Example>
     */
    private function responseExamples(): array
    {
        return [
            'add_employee' => new Example(
                value: ['status' => true, 'alerts' => ['Saved successfully']],
                summary: 'add_employee: saqlandi va terminallarga yozildi',
            ),
            'add_employee_invalid' => new Example(
                value: ['status' => false, 'alerts' => ['Enter name', 'Select template', 'Enter start datetime', 'Enter end datetime']],
                summary: 'add_employee: maydon xatosi',
                description: 'Boshqa xatolar: `Invalid template`, `Invalid card number`, `Invalid start datetime`, '
                    .'`Invalid end datetime`, `Invalid face image`, `LEGACY_API_DEPARTMENT_ID is not configured`, '
                    .'`LEGACY_API_SCHEDULE_ID is not configured`.',
            ),
            'add_employee_device_error' => new Example(
                value: ['status' => false, 'alerts' => [
                    ['status' => false, 'message' => 'IP: 10.0.0.8; ID: 15; Error: <terminal xatosi>'],
                ]],
                summary: 'add_employee: terminalga yozilmadi',
                description: 'Xodim bazada saqlangan, lekin shu terminalga yozilmadi.',
            ),
            'delete_employee' => new Example(
                value: ['status' => true, 'alerts' => ['Deleted successfully']],
                summary: 'delete_employee: o‘chirildi',
            ),
            'delete_employee_device_error' => new Example(
                value: ['status' => false, 'alerts' => ['10.0.0.8 <terminal xatosi>']],
                summary: 'delete_employee: terminaldan o‘chirilmadi',
                description: 'Bu xodim bazada qoladi. Boshqa xodimlar o‘chirilaveradi.',
            ),
            'attendance' => new Example(
                value: ['status' => true, 'attendance' => [
                    [
                        'name' => 'Ali Valiyev',
                        'card' => '12345678901234',
                        'note_type' => 'came_late',
                        'note' => 'Shifoxonada',
                        'dt' => '2026-11-11 09:20:00',
                        'before_9_15' => false,
                        'between_9_15_9_30' => true,
                        'after_9_30' => false,
                        'absent' => false,
                    ],
                    [
                        'name' => 'Vali Aliyev',
                        'card' => '23456789012345',
                        'note_type' => null,
                        'note' => null,
                        'dt' => null,
                        'before_9_15' => false,
                        'between_9_15_9_30' => false,
                        'after_9_30' => false,
                        'absent' => true,
                    ],
                ]],
                summary: 'attendance: bugungi davomat',
            ),
            'add_note' => new Example(
                value: ['status' => true],
                summary: 'add_note: saqlandi',
            ),
            'add_note_invalid' => new Example(
                value: ['status' => false, 'alerts' => ['Invalid card number', 'Invalid note type', 'Invalid note text', 'Invalid date']],
                summary: 'add_note: maydon xatosi',
            ),
            'get_reports' => new Example(
                value: ['status' => true, 'reports' => [
                    ['id' => '1520', 'card' => '12345678901234', 'ip' => '10.0.0.8', 'type' => '1', 'dt' => '2026-11-10 09:00:00'],
                    ['id' => '1544', 'card' => '12345678901234', 'ip' => '10.0.0.9', 'type' => '2', 'dt' => '2026-11-10 18:05:00'],
                ]],
                summary: 'get_reports: hodisalar',
            ),
            'get_work_time' => new Example(
                value: ['status' => true, 'work_time' => [
                    ['card' => '12345678901234', 'days' => ['2026-11-01' => '08:45:00', '2026-11-02' => 0], 'total' => '08:45:00'],
                ]],
                summary: 'get_work_time: ishlagan vaqt',
            ),
            'period_invalid' => new Example(
                value: ['status' => false, 'alerts' => ['Enter start datetime', 'Enter end datetime']],
                summary: 'get_reports / get_work_time: oraliq berilmagan',
                description: 'Sana noto‘g‘ri bo‘lsa `Invalid start datetime` yoki `Invalid end datetime`.',
            ),
            'unknown_action' => new Example(
                value: ['status' => false],
                summary: 'Bo‘sh tana, buzuq JSON yoki noma’lum action',
            ),
        ];
    }

    private function string(string $description): StringType
    {
        $type = new StringType;
        $type->setDescription($description);

        return $type;
    }

    /**
     * @param  array<string, Type>  $properties
     * @param  list<string>  $required
     */
    private function object(array $properties, array $required = []): ObjectType
    {
        $object = new ObjectType;

        foreach ($properties as $name => $type) {
            $object->addProperty($name, $type);
        }

        if ($required !== []) {
            $object->setRequired($required);
        }

        return $object;
    }
}
