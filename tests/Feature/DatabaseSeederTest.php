<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_card_library_without_a_test_user_outside_local(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(48, Card::count());
        $this->assertSame(0, User::count());
    }

    public function test_it_seeds_the_test_user_in_local(): void
    {
        $this->app['env'] = 'local';

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(48, Card::count());
        $this->assertTrue(User::where('email', 'admin@wesselswebworks.nl')->exists());
    }
}
