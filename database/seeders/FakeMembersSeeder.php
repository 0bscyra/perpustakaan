<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\MemberCardService;
use Illuminate\Database\Seeder;

class FakeMembersSeeder extends Seeder
{
    /**
     * Create fake member accounts with member cards without touching existing accounts.
     */
    public function run(MemberCardService $memberCards): void
    {
        User::factory()
            ->count(100)
            ->create()
            ->each(function (User $user) use ($memberCards): void {
                $memberCards->issue($user);
            });
    }
}
