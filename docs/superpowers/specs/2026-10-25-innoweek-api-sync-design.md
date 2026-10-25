# Innoweek → Biocontrol: yangi ishtirokchilarni API orqali tortish

## Muammo

Tadbir kuni odamlar joyida ro‘yxatdan o‘tadi: Innoweek ularga karta (`numbers.full_number`) beradi va odam
darhol turniketdan o‘tadi. Biocontrol bu kartani bilmasa, o‘tish "noma’lum karta" bo‘lib qoladi va jonli
statistikaga tushmaydi. SQL dump (`innoweek:import`) bir martalik — tadbir davomida yangilanib turmaydi.

## Yechim

1. **Innoweek** bitta faqat-o‘qish endpoint chiqaradi (token bilan) — o‘zgargan ishtirokchilarni
   kursor bo‘yicha sahifalab beradi. Kontrakt va tayyor Laravel kod: [docs/innoweek-api.md](../../innoweek-api.md).
2. **Biocontrol** `innoweek:sync` har ~20 soniyada so‘raydi va mehmonlarni xuddi importdagi qoidalar bilan
   yozadi (`InnoweekImportService::upsertParticipants`): karta bo‘yicha upsert, guruh = toifa (`profession`),
   davlat nomdan ISO kodga, shu kartali oddiy xodim o‘zgartirilmaydi.
3. **Real vaqt (webhook):** Innoweek karta berishi bilan (`BiocontrolNotifier`, `afterResponse`) ishtirokchini
   `POST /api/webhooks/innoweek/participants` ga yuboradi (o‘sha token, o‘sha JSON shakli, `{"data": [...]}`) —
   `InnoweekWebhookController` → `InnoweekSyncService::upsertItems()`. So‘rash (2-band) zaxira bo‘lib qoladi.
4. **Turniket** noma’lum kartali o‘tishlarni eslab qoladi (`turnstile.pull.unknown`, eng ko‘pi 5000) va karta
   keyin paydo bo‘lsa (sync olib kelsa) ularni avtomatik qayta yozadi — joyida ro‘yxatdan o‘tib darhol kirgan
   odam ham hisobga tushadi.

## API kontrakti (qisqa)

`GET {INNOWEEK_API_URL}/api/biocontrol/participants?cursor=&limit=500`, `Authorization: Bearer <token>`.

```json
{
  "data": [{
    "id": 12345, "card": "0003700001",
    "first_name": "Ali", "last_name": "Valiyev", "middle_name": null,
    "position": "Talaba", "gender": 1,
    "country": {"name_uz": "O'zbekiston", "name_en": "Uzbekistan"},
    "profession": "Ishtirokchi", "year": 2026, "status": "active",
    "registered_at": "2026-10-26T09:15:02+05:00"
  }],
  "next_cursor": "MjAyNi0xMC0yNiAwOToxNTowMnwxMjM0NQ==",
  "has_more": false
}
```

- O‘zgarish vaqti = `GREATEST(users.updated_at, numbers.updated_at)` — profil tahriri ham, kartaning
  biriktirilishi ham ilinadi. Kursor — shaffof `base64("changed_at|user_id")`, keyset (vaqt teng bo‘lsa id).
- Faqat oxirgi `archive_years` dagi kartalar; kartasiz foydalanuvchilar qaytmaydi.
- `cursor` bo‘sh — boshidan (hammasi). Javobda yangi narsa bo‘lmasa `next_cursor` o‘zgarmaydi.

## Biocontrol tomoni

- `config/innoweek.php`: `INNOWEEK_API_URL`, `INNOWEEK_API_TOKEN`, `INNOWEEK_SYNC_ENABLED`,
  `INNOWEEK_SYNC_INTERVAL` (soniya, standart 20), `INNOWEEK_DEPARTMENT` (mehmonlar bo‘limi; importga ham standart).
- `innoweek:sync {--for=0} {--full}` — kursor `Cache` da (`innoweek.sync.cursor`); `--full` boshidan.
  Scheduler: `INNOWEEK_SYNC_ENABLED=true` bo‘lsa har daqiqa `innoweek:sync --for=55` (`withoutOverlapping(2)`).
  API javob bermasa — log, shu daqiqa to‘xtaydi, keyingi daqiqada qayta.
- Dump importi o‘sha upsert yo‘lidan foydalanadi — ikkala manba bir xil natija beradi.
