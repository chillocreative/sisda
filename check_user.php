<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Check if user exists
$user = User::where('telephone', '0123456789')->first();

if ($user) {
    echo "User found: {$user->name}\n";
    echo "Telephone: {$user->telephone}\n";
    echo "Email: {$user->email}\n";
} else {
    echo "User not found. Creating user...\n";
    
    $user = User::create([
        'name' => 'Test User',
        'telephone' => '0123456789',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
    ]);
    
    echo "User created successfully!\n";
    echo "Name: {$user->name}\n";
    echo "Telephone: {$user->telephone}\n";
    echo "Password: password\n";
}
