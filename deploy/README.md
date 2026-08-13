# Portfolio deploy — sobirjonswe.uz

> Buyruqlardagi `$SERVER_IP` — serveringiz IP manzili. Bu repo ochiq bo‘lgani
> uchun manzil bu yerda saqlanmaydi. Ishlatishdan oldin eksport qiling:
>
> ```bash
> export SERVER_IP=1.2.3.4
> ```

Ikkita repo bitta serverga qo'yiladi:

| Domen | Repo | Nima |
|---|---|---|
| `sobirjonswe.uz`, `www.sobirjonswe.uz` | portfolio-frontend | React 19 + Vite 8, statik build + prerender qilingan HTML shell'lar |
| `api.sobirjonswe.uz` | portfolio-backend | Laravel 13 + PHP 8.4-FPM + PostgreSQL |

> **Tartib muhim.** Frontend build API'dan post va loyihalarni o'qib, har biri uchun
> alohida `<title>`/OG teglari bilan HTML shell yasaydi. Shuning uchun backend,
> nginx va SSL **frontend build'idan oldin** tayyor bo'lishi kerak.

---

## 0. DNS

Domen provayderida A-yozuvlarni server IP'siga yo'naltiring. **SSL olishdan oldin shart.**

| Type | Name | Value |
|---|---|---|
| A | `@` | `$SERVER_IP` |
| A | `www` | `$SERVER_IP` |
| A | `api` | `$SERVER_IP` |

Tekshirish: `dig +short sobirjonswe.uz api.sobirjonswe.uz`

---

## 1. Serverni tayyorlash (bir marta, root sifatida)

```bash
scp 01-server-setup.sh root@$SERVER_IP:/tmp/
ssh root@$SERVER_IP 'sudo bash /tmp/01-server-setup.sh'
```

O'rnatadi: nginx, PHP 8.4 + FPM, Composer, Node.js 22, PostgreSQL, certbot, UFW.
Yaratadi: `deploy` foydalanuvchisi, `/var/www/*`, `portfolio` bazasi,
Laravel scheduler uchun cron (`page-views:prune` kunlik ishlashi uchun).

> **Skript oxirida baza parolini chiqaradi — saqlab qo'ying.**

---

## 2. Backend `.env`

```bash
ssh deploy@$SERVER_IP
nano /var/www/portfolio-backend/.env      # backend.env.production dan nusxalang
chmod 600 /var/www/portfolio-backend/.env
```

`DB_PASSWORD` ni 1-qadamdagi parol bilan to'ldiring. `APP_KEY` bo'sh qolsin —
deploy skripti generatsiya qiladi.

> ⚠️ `APP_KEY` keyinchalik **o'zgartirmang**: analitikadagi IP hashlari shu kalit
> bilan bog'langan, almashtirsangiz eski yozuvlar bilan mos kelmay qoladi.

---

## 3. Backend deploy

```bash
scp 02-deploy-backend.sh deploy@$SERVER_IP:~/
ssh deploy@$SERVER_IP 'bash ~/02-deploy-backend.sh'
```

---

## 4. Nginx

```bash
scp nginx-frontend.conf root@$SERVER_IP:/etc/nginx/sites-available/sobirjonswe.uz
scp nginx-api.conf      root@$SERVER_IP:/etc/nginx/sites-available/api.sobirjonswe.uz

ssh root@$SERVER_IP '
  ln -sf /etc/nginx/sites-available/sobirjonswe.uz     /etc/nginx/sites-enabled/
  ln -sf /etc/nginx/sites-available/api.sobirjonswe.uz /etc/nginx/sites-enabled/
  rm -f /etc/nginx/sites-enabled/default
  nginx -t && systemctl reload nginx
'
```

---

## 5. SSL (Let's Encrypt)

```bash
ssh root@$SERVER_IP '
  certbot --nginx -d sobirjonswe.uz -d www.sobirjonswe.uz --redirect --agree-tos -m sobirjon.swe@gmail.com --no-eff-email
  certbot --nginx -d api.sobirjonswe.uz --redirect --agree-tos -m sobirjon.swe@gmail.com --no-eff-email
'
```

Tekshiring: `curl https://api.sobirjonswe.uz/api/v1/posts` JSON qaytarishi kerak.

---

## 6. Admin foydalanuvchi

⚠️ **`php artisan db:seed` ni productionda ishlatmang** — u `test@example.com`
foydalanuvchisini standart factory paroli bilan yaratadi.

```bash
ssh deploy@$SERVER_IP
cd /var/www/portfolio-backend
php artisan tinker
```

```php
\App\Models\User::create([
    'name'     => 'Sobirjon',
    'email'    => 'sobirjon.swe@gmail.com',
    'password' => \Illuminate\Support\Facades\Hash::make('KUCHLI-PAROL-BU-YERGA'),
]);
```

---

## 7. Frontend deploy

```bash
scp 03-deploy-frontend.sh deploy@$SERVER_IP:~/
ssh deploy@$SERVER_IP 'bash ~/03-deploy-frontend.sh'
```

Skript API'ga ulanishni tekshiradi va prerender qanchalik to'liq bo'lganini yozadi.

---

## 8. GitHub Actions avto-deploy

### 8.1 Deploy kaliti

```bash
ssh-keygen -t ed25519 -f ~/.ssh/portfolio_deploy -C "portfolio-github-actions" -N ""
ssh-copy-id -i ~/.ssh/portfolio_deploy.pub deploy@$SERVER_IP
```

### 8.2 Secrets

Har ikkala repoda (Settings → Secrets and variables → Actions):

| Secret | Qiymat |
|---|---|
| `VPS_HOST` | server IP |
| `VPS_USER` | `deploy` |
| `VPS_PORT` | SSH port (odatda `22`) |
| `VPS_SSH_KEY` | `~/.ssh/portfolio_deploy` faylining **to'liq mazmuni** |

### 8.3 Workflow fayllari

- `workflow-backend.yml`  → `portfolio-backend/.github/workflows/deploy.yml`
- `workflow-frontend.yml` → `portfolio-frontend/.github/workflows/deploy.yml`

---

## Tekshirish

```bash
curl -I https://sobirjonswe.uz                        # 200, text/html
curl    https://api.sobirjonswe.uz/up                 # health check
curl    https://api.sobirjonswe.uz/api/v1/projects    # JSON
curl -s https://sobirjonswe.uz/sitemap.xml | head     # barcha sahifalar
curl -s https://sobirjonswe.uz/robots.txt

# Blog post o'z preview'ini oldimi?
curl -s https://sobirjonswe.uz/blog/BIRON-SLUG/ | grep -E 'og:title|og:description'
```

Ulashishni tekshirish: postni Telegram'ga tashlang — sarlavha va tavsif shu
postniki bo'lishi kerak, umumiy sayt matni emas.

## Yangi post chiqargandan keyin

Prerender build vaqtida ishlaydi, shuning uchun yangi post o'z link preview'ini
va sitemap yozuvini olishi uchun frontend qayta build qilinishi kerak:

```bash
ssh deploy@$SERVER_IP 'bash ~/03-deploy-frontend.sh'
```

yoki GitHub'da frontend repo → Actions → **Deploy frontend** → *Run workflow*.

## Muammolarni tuzatish

| Belgi | Sabab |
|---|---|
| API'da 500 | `tail -f /var/www/portfolio-backend/storage/logs/laravel.log` |
| CORS xatoligi | `.env` dagi `FRONTEND_URLS` — protokol bilan, oxirida `/` bo'lmasin. Keyin `php artisan config:cache` |
| Frontend eski API'ga uryapti | `.env.production.local` noto'g'ri → tuzating va qayta build |
| Postlar link preview'siz | Build paytida API javob bermagan → `03-deploy-frontend.sh` ni qayta ishlating |
| SPA route'da 404 | nginx'da `try_files $uri $uri/ /index.html` yo'q |
| `502 Bad Gateway` | php-fpm socket yo'li mos emas: `ls /run/php/` |
| storage yozilmayapti | `sudo chgrp -R www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache` |
| LeetCode kartochkasi bo'sh | `.env` da `LEETCODE_USERNAME` to'ldirilganmi, keyin `php artisan config:cache` |
