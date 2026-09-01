# Priyanthi Multi Stores Ordering Platform

Laravel/MySQL ordering platform for bedsheet sets, collections, inventory, and admin order handling.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Default seeded admin:

- Email: `admin@bedsheets.ptree.lk`
- Password: `ChangeMeNow!2026`

Change these with `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env` before production seeding.

## Production notes

- CyberPanel/LiteSpeed document root must point to Laravel's `public` directory.
- Keep `.env`, `.git`, `storage`, `vendor`, and application code outside the public document root.
- Run `php artisan storage:link` for uploaded product images.
- Configure SMTP in `.env` for new-order email alerts.
- Use `php artisan migrate --seed --force` after deployment.
