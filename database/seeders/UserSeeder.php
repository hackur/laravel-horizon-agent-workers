<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create personal team for admin
        $team = Team::forceCreate([
            'user_id' => $admin->id,
            'name' => explode(' ', $admin->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]);

        $admin->ownedTeams()->save($team);
        $admin->update(['current_team_id' => $team->id]);

        // Create test user
        $testUser = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create personal team for test user
        $testTeam = Team::forceCreate([
            'user_id' => $testUser->id,
            'name' => explode(' ', $testUser->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]);

        $testUser->ownedTeams()->save($testTeam);
        $testUser->update(['current_team_id' => $testTeam->id]);

        $this->command->info('✓ Created admin user (admin@example.com / password)');
        $this->command->info('✓ Created test user (test@example.com / password)');
    }
}
