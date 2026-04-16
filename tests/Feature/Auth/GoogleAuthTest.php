<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

function mockGoogleUser(string $email, string $id = 'google-123', ?string $avatar = 'https://example.test/avatar.png'): void
{
    $googleUser = Mockery::mock()->shouldIgnoreMissing();
    $googleUser->shouldReceive('getEmail')->andReturn($email);
    $googleUser->shouldReceive('getId')->andReturn($id);
    $googleUser->shouldReceive('getAvatar')->andReturn($avatar);
    $googleUser->shouldReceive('getName')->andReturn('Test User');

    $driver = Mockery::mock()->shouldIgnoreMissing();
    $driver->shouldReceive('stateless')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn($driver);
}

describe('google authentication', function () {
    beforeEach(function () {
        collect(['superAdmin', 'collegeAdmin', 'deptAdmin', 'faculty'])
            ->each(fn (string $role) => Role::findOrCreate($role, 'web'));
    });

    it('google callback logs in eligible user and redirects to dashboard', function () {
        $user = User::factory()->create([
            'email' => 'admin@cvsu.edu.ph',
            'name' => 'Admin User',
        ]);
        $user->assignRole('superAdmin');

        mockGoogleUser('admin@cvsu.edu.ph');

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user->fresh());
    });

    it('google callback rejects unauthorized domains', function () {
        mockGoogleUser('user@yahoo.com');

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Please use an authorized Google account to continue.',
        ]);
        $this->assertGuest();
    });

    it('google callback rejects users not provisioned in the system', function () {
        mockGoogleUser('missing@cvsu.edu.ph');

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Your account must be added by an administrator before you can sign in.',
        ]);
        $this->assertGuest();
    });

    it('google callback rejects ineligible users', function () {
        $user = User::factory()->create([
            'email' => 'faculty@cvsu.edu.ph',
        ]);
        $user->assignRole('faculty');

        mockGoogleUser('faculty@cvsu.edu.ph');

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Your account is inactive. Please contact the administrator.',
        ]);
        $this->assertGuest();
    });

    it('logout clears authenticated session and redirects to login', function () {
        $user = User::factory()->create();
        $user->assignRole('superAdmin');

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    });
});
