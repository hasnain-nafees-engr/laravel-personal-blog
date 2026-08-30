<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| HOW PEST MAPS TO PHPUNIT (worth knowing for the interview):
|
|   Pest is not a different test runner - it IS PHPUnit underneath. Every
|   `test('...', function () {})` closure is compiled into a method on a
|   PHPUnit TestCase class. So:
|
|     test('a guest cannot delete', fn () => ...)   ->  public function test_a_guest_cannot_delete()
|     beforeEach()                                  ->  protected function setUp()
|     expect($x)->toBe(1)                           ->  $this->assertSame(1, $x)
|     $this->get('/')                               ->  identical - it is the same TestCase
|
|   That means every Laravel testing helper (actingAs, assertDatabaseHas,
|   Mail::fake) works unchanged, and `php artisan test` runs both styles.
|   Pest is chosen here purely for readability.
|
| The line below binds Laravel's TestCase and the RefreshDatabase trait to
| every file under tests/Feature. RefreshDatabase wraps each test in a
| database transaction and rolls it back afterwards, so tests never see each
| other's data.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests boot the framework but get NO database.
//
// why boot it at all: helpers like config() and app() need a container. The
// alternative - passing every setting in as an argument - would distort the
// code just to suit the tests. Leaving RefreshDatabase off keeps them fast
// and makes it obvious that a unit test touching the database is misplaced.
pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeSlug', function () {
    return $this->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
| Shared shortcuts so individual tests stay about the behaviour under test.
*/

function admin(): User
{
    return User::factory()->admin()->create();
}

function author(): User
{
    return User::factory()->create();
}

/**
 * The two hidden fields the public comment form posts.
 *
 * @return array<string, string>
 */
function honeypotFields(): array
{
    return [
        'website' => '',
        // Encrypted timestamp, back-dated so the "typed too fast" check passes.
        'started_at' => Crypt::encryptString(
            (string) now()->subMinute()->timestamp,
        ),
    ];
}
