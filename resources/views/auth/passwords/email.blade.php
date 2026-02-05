@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <div class="flex justify-center">
        <div class="w-full max-w-md">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                    <h1 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Reset Password') }}</h1>
                </div>

                <div class="p-6">
                    @if (session('status'))
                        <div class="bg-green-100 dark:bg-green-900 border border-green-200 dark:border-green-700 text-green-800 dark:text-green-200 px-4 py-3 rounded mb-4" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf

                        <div class="mb-6">
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ __('Email Address') }}
                            </label>

                            <input id="email"
                                   type="email"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 dark:border-red-400 @enderror"
                                   name="email"
                                   value="{{ old('email') }}"
                                   required
                                   autocomplete="email"
                                   autofocus>

                            @error('email')
                                <p class="text-red-500 dark:text-red-400 text-sm mt-1" role="alert">
                                    <strong>{{ $message }}</strong>
                                </p>
                            @enderror
                        </div>

                        @if(config('captcha.enabled'))
                            <div class="mb-6">
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
                                    <p class="text-red-500 dark:text-red-400 text-sm mt-1" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </p>
                                @enderror
                            </div>
                        @endif

                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-md transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                                {{ __('Send Password Reset Link') }}
                            </button>
                        </div>
                    </form>

                </div>
            </div>
            <div class="mt-3 text-sm text-center text-gray-400 underline"><a href="/">Back to Loops</a></div>
        </div>
    </div>
</div>
@endsection
