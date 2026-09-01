# Randalu PC Ordering Platform

Laravel/MySQL ordering platform for computer hardware & parts: catalog, collections, inventory, and admin order handling.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Default seeded admin:

- Email: `admin@randalu-pc.lk`
- Password: `ChangeMeNow!2026`

Change these with `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env` before production seeding.

## Production notes

- CyberPanel/LiteSpeed document root must point to Laravel's `public` directory.
- Keep `.env`, `.git`, `storage`, `vendor`, and application code outside the public document root.
- Run `php artisan storage:link` for uploaded product images.
- Configure SMTP in `.env` for new-order email alerts.
- Use `php artisan migrate --seed --force` after deployment.

## SMS & customer accounts

- SMS is sent through **SMSlenz** (`https://smslenz.lk/api`). Set `SMSLENZ_USER_ID`,
  `SMSLENZ_API_KEY`, and `SMSLENZ_SENDER_ID` in `.env`. Use `SMSlenzDEMO` as the
  sender ID for testing. Toggle `sms_enabled` (1/0) in admin Settings.
- Customers sign in / register on the storefront with a phone number + SMS OTP
  (no password). Admins can check the SMS credit balance from Settings.

