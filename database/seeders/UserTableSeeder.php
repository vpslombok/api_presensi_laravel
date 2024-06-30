<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = array(
            [
                'name' => 'Bayu Pratama',
                'email' => 'bayu@gmail.com',
                'nik' => '21075011',
                'password' => bcrypt('12345678'),
                'foto' => '/img/user.jpg',
                'level' => 1
            ],
            [
                'name' => 'Kasir',
                'email' => 'kasir@gmail.com',
                'nik' => '21075012',
                'password' => bcrypt('12345678'),
                'foto' => '/img/user.jpg',
                'level' => 2
            ]
        );

        array_map(function (array $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }, $users);
    }
}
