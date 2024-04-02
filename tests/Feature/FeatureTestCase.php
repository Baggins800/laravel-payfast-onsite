<?php

namespace Tests\Feature;

use FintechSystems\Payfast\PayfastServiceProvider;
use FintechSystems\Payfast\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase;

abstract class FeatureTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();

        $this->artisan('migrate')->run();
    }

    protected function createBillable($description = 'eugene', array $options = []): User
    {
        $user = $this->createUser($description);

        $user->createAsCustomer($options);

        return $user;
    }

    protected function createUser($description = 'eugene', array $options = []): User
    {
        return User::create(array_merge([
            'email' => "{$description}@payfast-test.com",
            'name' => 'Eugene van der Merwe',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ], $options));
    }

    protected function getPackageProviders($app)
    {
        return [
            PayfastServiceProvider::class,
        ];
    }
}
