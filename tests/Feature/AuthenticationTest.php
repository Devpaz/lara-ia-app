<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('guests can view the authentication pages', function () {
    $this->get(route('login'))->assertSee('Welcome back');
    $this->get(route('register'))->assertSee('Create your account');
});

test('authenticated users are redirected away from authentication pages', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('login'))->assertRedirect(route('chat'));
    $this->get(route('register'))->assertRedirect(route('chat'));
});

test('a user can register with the allowed email domain', function () {
    Livewire::test('pages::auth.register')
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ADA@EXAMPLE.COM')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertRedirectToRoute('chat');

    $user = User::where('email', 'ada@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Ada Lovelace');
    expect(Hash::check('password123', $user->password))->toBeTrue();
});

test('registration requires all fields', function () {
    Livewire::test('pages::auth.register')
        ->call('register')
        ->assertHasErrors([
            'name' => ['required'],
            'email' => ['required'],
            'password' => ['required'],
        ]);

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('registration validates the name length', function () {
    Livewire::test('pages::auth.register')
        ->set('name', str_repeat('a', 256))
        ->set('email', 'ada@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['name' => ['max']]);
});

test('registration validates email format and allowed domain', function (string $email, string $rule) {
    Livewire::test('pages::auth.register')
        ->set('name', 'Ada Lovelace')
        ->set('email', $email)
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['email' => [$rule]])
        ->assertSet('name', 'Ada Lovelace')
        ->assertSet('email', strtolower($email));
})->with([
    'invalid format' => ['not-an-email', 'email'],
    'different domain' => ['ada@other.test', 'ends_with'],
]);

test('registration requires a unique email address', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    Livewire::test('pages::auth.register')
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['email' => ['unique']]);
});

test('registration requires a sufficiently long confirmed password', function (string $password, string $confirmation, string $rule) {
    Livewire::test('pages::auth.register')
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('password', $password)
        ->set('password_confirmation', $confirmation)
        ->call('register')
        ->assertHasErrors(['password' => [$rule]]);
})->with([
    'minimum length' => ['short', 'short', 'min'],
    'confirmation' => ['password123', 'different-password', 'confirmed'],
]);

test('a user can log in', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    Livewire::test('pages::auth.login')
        ->set('form.email', 'ADA@EXAMPLE.COM')
        ->set('form.password', 'password123')
        ->call('login')
        ->assertRedirectToRoute('chat');

    $this->assertAuthenticatedAs($user);
});

test('invalid credentials leave the user unauthenticated and preserve the email', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => 'password123',
    ]);

    Livewire::test('pages::auth.login')
        ->set('form.email', 'ada@example.com')
        ->set('form.password', 'incorrect-password')
        ->call('login')
        ->assertHasErrors(['form.email'])
        ->assertSet('form.email', 'ada@example.com');

    $this->assertGuest();
});

test('repeated failed login attempts are rate limited', function () {
    Event::fake([Lockout::class]);

    foreach (range(1, 6) as $attempt) {
        Livewire::test('pages::auth.login')
            ->set('form.email', 'ada@example.com')
            ->set('form.password', 'incorrect-password')
            ->call('login')
            ->assertHasErrors(['form.email']);
    }

    Event::assertDispatched(Lockout::class);
    $this->assertGuest();
});

test('chat endpoints require authentication', function () {
    $this->get(route('chat'))->assertRedirect(route('login'));
    $this->postJson(route('chat.stream'), ['message' => 'Hello'])->assertUnauthorized();
});
