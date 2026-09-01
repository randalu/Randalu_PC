<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:prune-event-logs')->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:create {email?} {--name=} {--password=} {--role=super_admin}', function (): void {
    $email = $this->argument('email') ?: env('ADMIN_EMAIL', 'admin@randalu-pc.lk');
    $name = $this->option('name') ?: env('ADMIN_NAME', 'Randalu PC Admin');
    $password = $this->option('password') ?: env('ADMIN_PASSWORD');
    $role = $this->option('role');

    if (! in_array($role, array_keys(User::ROLES), true)) {
        $this->error("Unknown role \"{$role}\". Valid roles: ".implode(', ', array_keys(User::ROLES)).'.');

        return;
    }

    if (blank($password)) {
        if (app()->environment('production')) {
            $this->error('A password is required in production. Use --password="..."');

            return;
        }

        $password = 'ChangeMeNow!2026';
    }

    $admin = User::query()->updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'role' => $role,
            'password' => Hash::make($password),
        ],
    );

    $this->info("Admin \"{$admin->name}\" <{$admin->email}> is ready with role \"{$admin->role}\".");
})->purpose('Create or update an admin user');
