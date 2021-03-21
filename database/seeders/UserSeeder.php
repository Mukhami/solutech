<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $check_for_test_user = User::where('email', '=', 'test@user.com')->first();
        if (!$check_for_test_user){
            User::create([
                'name'=>'TestUser',
                'email'=>'test@user.com',
                'email_verified_at'=>now(),
                'password'=>Hash::make('secretpassword'),
                'remember_token'=>null,
                'created_at'=>now(),
                'updated_at'=>now()
            ]);
        }
    }
}
