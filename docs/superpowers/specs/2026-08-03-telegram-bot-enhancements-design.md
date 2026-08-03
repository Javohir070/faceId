# Telegram bot — status, digest, prefs, role filter

## Maqsad

Ulangan `super-admin` userlarga: `/status`, `/devices`, `/expiry`, `/settings`; qurilma online xabar; kunlik diggest; xabar preferenslari.

## Qamrov

- Buyruqlar: `/status`, `/devices`, `/expiry`, `/settings` (+ `/start`, `/unlink`)
- Qurilma online o‘tishida xabar (false→true)
- Kunlik diggest 08:30: kecha kechikkan / kelmagan / oflayn
- Prefs: `all|device|expiry|none` (`users.telegram_notify_prefs`)
- Qabul qiluvchilar: faqat `super-admin` + `telegram_chat_id`
- Kechikish: kunning 1-kirishi > `schedule.enter_time` (grace 0)
- Kelmagan: o‘sha kuni `TYPE_ENTRY` yo‘q xodimlar (schedule bog‘langan)

## Chegaralar

- Oddiy `user` roliga Telegram alert yo‘q
- Inline keyboard minimal (`/settings` matn javoblari bilan)
