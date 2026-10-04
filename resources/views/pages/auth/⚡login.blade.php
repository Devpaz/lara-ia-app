<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->form->email = Str::lower(trim($this->form->email));

        $this->validate();
        $this->form->authenticate();

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
            <h1 class="text-2xl font-semibold text-white">Welcome back</h1>
            <p class="mt-2 text-sm text-zinc-400">Sign in to continue to LaraChat.</p>
        </div>

        <form wire:submit="login" class="flex flex-col gap-5">
            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-zinc-200">Email</label>
                <input wire:model.blur="form.email" id="email" name="email" type="email" required autofocus
                    autocomplete="username"
                    class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
                    placeholder="you@example.com">
                @error('form.email')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-zinc-200">Password</label>
                <input wire:model="form.password" id="password" name="password" type="password" required
                    autocomplete="current-password"
                    class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-4 py-3 text-sm text-white outline-none transition placeholder:text-zinc-600 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
                    placeholder="Your password">
                @error('form.password')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-zinc-400">
                <input wire:model="form.remember" type="checkbox"
                    class="rounded border-zinc-700 bg-zinc-950 text-violet-600 focus:ring-violet-500/30">
                Remember me
            </label>

            <button type="submit" wire:loading.attr="disabled" wire:target="login"
                class="cursor-pointer rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-violet-500 disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login">Signing in...</span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-zinc-400">
            Need an account?
            <a href="{{ route('register') }}" wire:navigate class="font-medium text-violet-400 hover:text-violet-300">Register</a>
        </p>
    </div>
</div>
