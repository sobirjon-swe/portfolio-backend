# Portfolio Website — To'liq Reja (sobirjonswe.uz)

> Maqsad: shaxsiy portfolio + mijoz jalb qiluvchi "Xizmatlar" bo'limi.
> Dizayn yo'nalishi: **majd-portfolio** (minimalist, animatsion, dark) × **jora.uz** (mahalliy, o'zbekona, professional).
> 3 til: **EN / UZ / RU**. Loyihalar backend'dan dinamik. Lead'lar → Telegram bot + Excel export.

---

## 1. Umumiy arxitektura

```
┌──────────────────────┐        REST /api/v1        ┌──────────────────────────┐
│  portfolio-frontend  │  ───────────────────────►  │   portfolio-backend      │
│  React + Vite        │  ◄───────────────────────  │   Laravel (layered)      │
│  react-i18next       │        JSON (i18n)         │   Controller→Service→Repo│
│  Maia, dark+blue     │                            │   PostgreSQL             │
└──────────────────────┘                            └───────────┬──────────────┘
                                                                │
                                                    ┌───────────┴──────────────┐
                                                    │  Telegram Bot (lead xabar)│
                                                    │  Excel export (leads)     │
                                                    └───────────────────────────┘
```

Mavjud backend juda yaxshi holatda (Controller → Service → Repository → Model, Sanctum auth, Resource'lar). Reja **mavjud kodni buzmasdan** kengaytiradi.

---

## 2. Sayt tuzilishi (bir sahifali, scroll)

Tartib mijoz jalb qilishga optimallashtirilgan — **Xizmatlar yuqorida**:

| # | Bo'lim | Referens | Manba |
|---|--------|----------|-------|
| 1 | **Hero** — ism, pozitsiya, CTA | majd + jora | statik + i18n |
| 2 | **Xizmatlar** — 3 karta + "Loyiha boshlash" | jora | statik + i18n |
| 3 | **Loyihalar** — case-study kartalar | newtonyuan / naimur | **backend** `GET /projects` |
| 4 | **Tajriba** — timeline | elliottprogrammer | **backend** `GET /experiences` |
| 5 | **Ko'nikmalar / Texnologiyalar** | majd | **backend** `GET /skills`, `/technologies` |
| 6 | **Coding stats** — LeetCode + GitHub | haffee | **backend** `GET /leetcode` |
| 7 | **Men haqimda** | jora | statik + i18n |
| 8 | **Kontakt** — forma → lead | — | **backend** `POST /messages` |
| — | Footer — ijtimoiy tarmoqlar | — | **backend** `GET /social-links` |

---

## 3. i18n strategiyasi (eng muhim qism)

Ikki qatlam:

### 3.1 UI matnlari (tugma, yorliq, sarlavha) — frontend
- `react-i18next` + `i18next-browser-languagedetector`
- Fayllar: `src/locales/{en,uz,ru}/common.json`
- Til almashtirgich header'da (EN | UZ | RU), tanlov `localStorage`da saqlanadi.

### 3.2 Kontent (loyiha, tajriba, ko'nikma matnlari) — backend
**Tavsiya: `spatie/laravel-translatable`** (JSON ustunlar, battle-tested).

Migratsiyada tarjima qilinadigan matn ustunlari `json` bo'ladi:
```php
// projects jadvali (yangi yoki migration bilan o'zgartirish)
$table->json('title');        // {"en":"...","uz":"...","ru":"..."}
$table->json('description');  // {"en":"...","uz":"...","ru":"..."}
// slug, url'lar — bir xil, tarjimasiz qoladi
```

Model:
```php
use Spatie\Translatable\HasTranslations;

class Project extends Model {
    use HasTranslations;
    public array $translatable = ['title', 'description'];
}
```

API tilni tanlaydi: `Accept-Language: uz` header yoki `?lang=uz` query.
Middleware `SetLocale` kelgan tilni `app()->setLocale()` bilan o'rnatadi → Resource avtomatik to'g'ri tilni qaytaradi.

**Tarjima kerak bo'lgan modellar:** Project, Experience, Skill, Technology (name), Post.
**Tarjimasiz:** slug, url, email, sana, rasm yo'llari.

---

## 4. Telegram bot integratsiyasi (lead xabari)

Yangi `POST /messages` kelganda admin'ga darhol Telegram xabari.

**Tavsiya: `laravel-notification-channels/telegram`** paketi.

Oqim:
```
Visitor forma → POST /messages → MessageService::create()
      → Message saqlanadi (DB)
      → NewLeadNotification (Telegram channel) → admin chatga xabar
```

`.env`:
```
TELEGRAM_BOT_TOKEN=...        # @BotFather'dan
TELEGRAM_ADMIN_CHAT_ID=...    # sening chat_id'ing
```

Xabar namunasi:
```
🔔 Yangi loyiha so'rovi!
👤 Ism: {name}
📧 Email: {email}
💰 Budjet: {budget}
📝 Xabar: {body}
```

Notification `ShouldQueue` bo'ladi (forma javobi sekinlashmasligi uchun).
`MessageService`ni buzmaymiz — faqat `create()` ichiga notification qo'shamiz.

---

## 5. Excel export (lead'lar ro'yxati)

**Tavsiya: `maatwebsite/excel`** paketi.

Yangi admin endpoint:
```php
Route::get('messages/export', [MessageController::class, 'export']); // auth:sanctum
```
`MessagesExport` klassi → `messages` jadvalini `.xlsx` qilib qaytaradi
(ustunlar: Ism, Email, Budjet, Xabar, O'qilgan, Sana).

---

## 6. Backend — bajariladigan ishlar (bosqichma-bosqich)

### Bosqich B1 — i18n asos
- [ ] `spatie/laravel-translatable` o'rnatish
- [ ] `SetLocale` middleware (`Accept-Language` / `?lang`)
- [ ] Migratsiyalar: `projects`, `experiences`, `skills`, `technologies`, `posts` matn ustunlarini `json`ga
- [ ] Modellarga `HasTranslations`
- [ ] Store/Update Request'lar — har til uchun validatsiya (`title.en`, `title.uz`, `title.ru`)
- [ ] Seeder'lar — 3 tilda namuna ma'lumot

### Bosqich B2 — Telegram
- [ ] `laravel-notification-channels/telegram` o'rnatish
- [ ] `NewLeadNotification` (ShouldQueue)
- [ ] `MessageService::create()`ga ulash
- [ ] `.env.example`ga TELEGRAM_* qo'shish

### Bosqich B3 — Excel
- [ ] `maatwebsite/excel` o'rnatish
- [ ] `MessagesExport` + `export()` endpoint
- [ ] `is_read` boshqaruvi (o'qilgan deb belgilash `PATCH /messages/{id}`)

### Bosqich B4 — Testlar (ECC: 80%+)
- [ ] Feature: message store → Telegram fake notification yuborilishi
- [ ] Feature: har uch tilda `GET /projects` to'g'ri til qaytarishi
- [ ] Feature: export .xlsx qaytarishi
- [ ] Honeypot (`website`) to'ldirilsa — rad etilishi

---

## 7. Frontend — bajariladigan ishlar

### Bosqich F1 — asos
- [ ] Vite + React + TS loyihasi (Maia preset, dark+blue)
- [ ] `react-i18next` sozlash, EN/UZ/RU locale fayllari
- [ ] API client (axios), `Accept-Language` avtomatik
- [ ] Dizayn tokenlari (ranglar, shrift, spacing) — 8-bo'limga qarang
- [ ] Layout: Header (til almashtirgich) + Footer

### Bosqich F2 — bo'limlar
- [ ] Hero (animatsion kirish)
- [ ] Xizmatlar (3 karta + CTA)
- [ ] Loyihalar (backend, hover case-study kartalar)
- [ ] Tajriba (timeline)
- [ ] Ko'nikmalar / Texnologiyalar
- [ ] Coding stats (LeetCode widget)
- [ ] Men haqimda
- [ ] Kontakt forma (honeypot bilan, POST /messages)

### Bosqich F3 — sayqal
- [ ] Scroll animatsiyalari (framer-motion, fade-up)
- [ ] SEO meta + Open Graph (3 tilda)
- [ ] Responsive (mobil-birinchi)
- [ ] Lighthouse 90+ (performance, a11y)

---

## 8. Dizayn tizimi (qisqacha — vizual versiya Artifact'da)

**Ranglar (dark + blue):**
| Token | Qiymat | Ishlatilishi |
|-------|--------|--------------|
| `bg` | `#0A0B0F` | asosiy fon |
| `surface` | `#12141A` | kartalar |
| `border` | `#1E2129` | chegaralar |
| `text` | `#E6E8EC` | asosiy matn |
| `muted` | `#8A909B` | ikkilamchi matn |
| `accent` | `#3B82F6` | asosiy ko'k (CTA, urg'u) |
| `accent-2` | `#60A5FA` | gradient/hover |

**Tipografika:** Sarlavha — `Space Grotesk` / `Geist`; matn — `Inter`. Katta hero sarlavhalar (clamp 2.5–5rem), keng harf oralig'i.

**Animatsiya:** scroll'da `fade-up`, silliq `ease-out`, hover'da kartalarning ko'tarilishi. Ortiqcha emas — majd kabi vazmin.

> To'liq vizual dizayn tizimi (ranglar, mockup'lar, komponentlar) alohida Artifact sahifasida ko'rsatilgan.

---

## 9. Tavsiya etilgan navbat

```
B1 (i18n) → F1 (frontend asos) → F2+B2/B3 parallel → F3 sayqal → B4 test → deploy
```

Boshlash nuqtasi: **B1 (i18n)** — chunki u butun ma'lumot modelini belgilaydi, keyin frontend shu shaklga quriladi.
