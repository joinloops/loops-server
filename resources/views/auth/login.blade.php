@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto px-4">
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('Login') }}</h2>
        </div>

        <div class="px-6 py-6">
            <form method="POST" action="{{ route('login') }}" class="space-y-6">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('Email Address') }}
                    </label>
                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autocomplete="email"
                           autofocus
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror">

                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('Password') }}
                    </label>
                    <input id="password"
                           type="password"
                           name="password"
                           required
                           autocomplete="current-password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('password') border-red-500 @enderror">

                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center">
                    <input id="remember"
                           type="checkbox"
                           name="remember"
                           {{ old('remember') ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-700">
                        {{ __('Remember Me') }}
                    </label>
                </div>

                @if(config('captcha.enabled'))
                    <div>
                        <input type="hidden" name="captcha_type" value="{{ config('captcha.driver') }}">
                        @if(config('captcha.driver') === 'turnstile')
                            <div class="cf-turnstile" data-sitekey="{{ config('captcha.siteKey') }}" data-callback="onCaptchaSuccess"></div>
                            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                        @elseif(config('captcha.driver') === 'hcaptcha')
                            <div class="h-captcha" data-sitekey="{{ config('captcha.siteKey') }}" data-callback="onCaptchaSuccess"></div>
                            <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
                        @endif
                        <input type="hidden" name="captcha_token" id="captcha_token" value="">
                        <script>
                            function onCaptchaSuccess(token) {
                                document.getElementById('captcha_token').value = token;
                            }
                        </script>
                        @error('captcha_token')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-150 ease-in-out">
                        {{ __('Login') }}
                    </button>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                           class="text-sm text-blue-600 hover:text-blue-800 hover:underline">
                            {{ __('Forgot Your Password?') }}
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
