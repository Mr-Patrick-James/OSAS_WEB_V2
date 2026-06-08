# Push notifications — eosas.duckdns.org (32.236.14.195)

## Requirements

| Item | Value |
|------|--------|
| Site URL | `https://eosas.duckdns.org` |
| Server IP | `32.236.14.195` |
| HTTPS | Required (phones block push on HTTP) |
| PHP | 8.1+ with `composer` |
| DB | `osas` / password from `config/db_connect.local.php` |

DuckDNS: point **eosas** → **32.236.14.195**. Open EC2 security group ports **80** and **443**.

---

## 1. Deploy code on the server

```bash
cd /var/www/html   # or your web root
git pull
```

---

## 2. Database

```bash
cp config/db_connect.aws.example.php config/db_connect.local.php
# Edit if MySQL user/password differs
```

---

## 3. Composer + push tables

```bash
composer install --no-dev --optimize-autoloader
php scripts/setup_push.php
php scripts/fix_push_schema.php
php scripts/verify_push_setup.php
```

---

## 4. VAPID keys (one-time)

On your PC:

```bash
npx web-push generate-vapid-keys
```

On the server create **`app/config/push_config.local.php`** (chmod 600):

```php
<?php
return [
    'enabled' => true,
    'vapid' => [
        'subject'    => 'https://eosas.duckdns.org',
        'publicKey'  => 'YOUR_PUBLIC_KEY',
        'privateKey' => 'YOUR_PRIVATE_KEY',
    ],
];
```

**Important:** `subject` must be `https://eosas.duckdns.org` (your new domain). If you change domain again, update this and users must re-enable notifications.

---

## 5. Verify API

```bash
curl -s https://eosas.duckdns.org/api/push.php?action=vapid-key
```

Expected:

```json
{"status":"success","message":"VAPID public key","data":{"publicKey":"..."}}
```

If you see `"Push not configured"`, `push_config.local.php` is missing or keys are empty.

---

## 6. Test on a phone

1. Open **`https://eosas.duckdns.org`** in Chrome (Android) or Safari (iOS 16.4+).
2. **Install** the app (Add to Home screen).
3. Open from the home screen → tap **Enable notifications** → **Allow**.
4. As admin, create an **announcement** → all subscribers should get a push.
5. Student login → enable alerts → create a **violation** for that student → violation push.

---

## New domain = new subscriptions

Changing from `e-osas.duckdns.org` to `eosas.duckdns.org` is a **different origin**. Students must:

- Install the PWA from the **new** URL
- Turn on notifications again

Old `push_subscriptions` rows still work only for the old domain’s browser endpoints.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Push not configured | Add `app/config/push_config.local.php` with keys |
| `endpoint_hash` SQL error | Run `php scripts/fix_push_schema.php` |
| No notification on phone | HTTPS, installed PWA, permission Allowed |
| iOS no push | iOS 16.4+, installed to home screen, not only Safari tab |
| 503 on subscribe | `composer install`, check `vendor/minishlink/web-push` |

---

## Quick install script (on server)

```bash
bash scripts/aws_install_push.sh
```

Then still add `app/config/push_config.local.php` manually.
