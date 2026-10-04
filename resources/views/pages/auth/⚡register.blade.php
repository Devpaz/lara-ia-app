<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:'.User::class,
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function register(): void
    {
        $this->name = trim($this->name);
        $this->email = Str::lower(trim($this->email));

        $validated = $this->validate();
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        event(new Registered($user));
        Auth::login($user);
        Session::regenerate();

        $this->redirectRoute('chat', navigate: true);
    }
};
?>

<div class="flex min-h-screen items-center justify-center bg-zinc-950 px-4 py-12 text-zinc-100">
    <div class="w-full max-w-md rounded-2xl border border-zinc-800 bg-zinc-900 p-8 shadow-2xl shadow-black/30">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 text-lg font-bold text-white">
                L
            </div>
            <h1 class="text-2xl font-semibold text-white">Create your account</h1>
            <p class="mt-2 text-sm text-zinc-400">Register with a valid email address.</p>
        </div>

        <form wire:submit="register" class="flex flex-col gap-5">
            <div>
                <label for="name" class="mb-2 block text-sm font-medium text-zinc-200">Name</label>
                <input wire:model.blur="name" id="name" name="name" type="text" required autofocus autocomplete="name"
                    class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
                    placeholder="Your name">
                @error('name')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-zinc-200">Email</label>
                <input wire:model.blur="email" id="email" name="email" type="email" required autocomplete="username"
                    class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
                    placeholder="email@example.com">
                @error('email')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-zinc-200">Password</label>
                <input wire:model="password" id="password" name="password" type="password" required autocomplete="new-password"
                    class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
                    placeholder="At least 8 characters">
                @error('password')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-medium text-zinc-200">Confirm password</label>
                <input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation"
                    type="password" required autocomplete="new-password"
                    class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
                    placeholder="Repeat your password">
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="register"
                class="cursor-pointer rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-violet-500 disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="register">Create account</span>
                <span wire:loading wire:target="register">Creating account...</span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-zinc-400">
            Already registered?
            <a href="{{ route('login') }}" wire:navigate class="font-medium text-violet-400 hover:text-violet-300">Sign in</a>
        </p>
    </div>
</div>
