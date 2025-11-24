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
        $admin = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Admin User',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create personal team for admin
        $team = Team::firstOrCreate([
            'user_id' => $admin->id,
            'personal_team' => true,
        ], [
            'user_id' => $admin->id,
            'name' => explode(' ', $admin->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]);

        $admin->ownedTeams()->save($team);
        $admin->update(['current_team_id' => $team->id]);
        $team->users()->syncWithoutDetaching([$admin->id => ['role' => 'admin']]);

        // Create test user
        $testUser = User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create personal team for test user
        $testTeam = Team::firstOrCreate([
            'user_id' => $testUser->id,
            'personal_team' => true,
        ], [
            'user_id' => $testUser->id,
            'name' => explode(' ', $testUser->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]);

        $testUser->ownedTeams()->save($testTeam);
        $testUser->update(['current_team_id' => $testTeam->id]);
        $team->users()->syncWithoutDetaching([$testUser->id => ['role' => 'editor']]);

        $this->command->info('✓ Created admin user (admin@example.com / password)');
        $this->command->info('✓ Created test user (test@example.com / password)');
    }
}
