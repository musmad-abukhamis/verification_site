<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteUserToAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:promote {email? : The email of the user to promote}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote a user to admin role';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        if (!$email) {
            // If no email provided, promote the first user
            $user = User::first();
            if (!$user) {
                $this->error('No users found in the database.');
                return 1;
            }
        } else {
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->error("User with email '{$email}' not found.");
                return 1;
            }
        }

        if ($user->is_admin) {
            $this->warn("User '{$user->name}' ({$user->email}) is already an admin.");
            return 0;
        }

        $user->update(['is_admin' => true]);

        $this->info("✓ User '{$user->name}' ({$user->email}) has been promoted to admin.");

        return 0;
    }
}
