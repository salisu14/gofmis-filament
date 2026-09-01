<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionDemoObserverCommand extends Command
{
    protected $signature = 'gof:provision-demo-observer
                            {--email=demo@gofmis.org : Email address for the demo observer account}
                            {--name="Demo Observer" : Full name for the demo observer user}
                            {--password= : Optional explicit password for the account}';

    protected $description = 'Idempotently provision or update the secure system-wide read-only Demo Observer account.';

    public function handle(): int
    {
        $this->info('Ensuring roles and permissions are seeded...');
        $this->call(RolesAndPermissionsSeeder::class);

        $email = (string) $this->option('email');
        $name = (string) $this->option('name');
        $password = (string) ($this->option('password') ?: env('DEMO_OBSERVER_PASSWORD', Str::random(16)));

        $user = User::where('is_protected_system_account', true)
            ->orWhereHas('roles', fn ($q) => $q->where('name', 'demo_observer'))
            ->first();

        if (! $user) {
            $existingEmailUser = User::where('email', $email)->first();
            if ($existingEmailUser && ! $existingEmailUser->hasRole('demo_observer')) {
                $this->error("Cannot provision demo observer: An account with email {$email} already exists with a different role ({$existingEmailUser->roles->pluck('name')->implode(', ')}).");

                return self::FAILURE;
            }
            $user = $existingEmailUser ?? new User(['email' => $email]);
        }

        $isNew = ! $user->exists;

        $user->name = $name;
        $user->email = $email;
        $user->is_protected_system_account = true;
        $user->is_active = true;
        $user->status = \App\Enums\UserStatus::ACTIVE;
        if ($isNew || $this->option('password')) {
            $user->password = Hash::make($password);
        }
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        // Ensure role assignment
        if (! $user->hasRole('demo_observer')) {
            $user->syncRoles(['demo_observer']);
        }

        $this->info("Demo Observer account successfully provisioned: {$user->email}");
        if ($isNew || $this->option('password')) {
            $this->warn("Password set to: {$password}");
        } else {
            $this->info('Existing user password retained.');
        }

        return self::SUCCESS;
    }
}
