@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-6 text-center">
                <h1 class="text-xl font-semibold tracking-tight text-gray-900">J4G Inventory</h1>
                <p class="mt-1 text-[13px] text-gray-500">Sign in to manage printing inventory</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <x-ui.label for="email">Email</x-ui.label>
                    <x-ui.input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus />
                </div>
                <div>
                    <x-ui.label for="password">Password</x-ui.label>
                    <x-ui.input id="password" type="password" name="password" required />
                </div>
                <label class="flex items-center gap-2 text-[13px] text-gray-600">
                    <input type="checkbox" name="remember" class="rounded border-gray-300">
                    Remember me
                </label>
                <x-ui.button type="submit" class="w-full justify-center">Sign In</x-ui.button>
            </form>
        </div>
    </div>
@endsection
