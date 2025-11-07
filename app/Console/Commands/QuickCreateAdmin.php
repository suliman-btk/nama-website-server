<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class QuickCreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:quick {name} {email} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quickly create an admin user with all parameters';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->argument('password');

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists!");
            return 1;
        }

        // Create admin user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);

        $this->info("✅ Admin user created successfully!");
        $this->line("📝 Name: {$user->name}");
        $this->line("📧 Email: {$user->email}");
        $this->line("🔑 Password: {$password}");
        $this->line("👑 Admin: Yes");

        return 0;
    }
}



