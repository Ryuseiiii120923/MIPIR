<?php
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.guest')] class extends Component {};
?>

<div> {{-- single root element --}}

    {{-- QR Scanner Modal --}}
    <div id="static-modal-login" data-modal-backdrop="static" tabindex="-1" aria-hidden="true"
        class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-2xl max-h-full">
            <div class="relative bg-white rounded-lg shadow-sm">
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-900">Scan ID</h3>
                    <button type="button"
                        class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center"
                        id="scanner-id-close">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>
                <div class="max-w-lg mx-auto">
                    <video class="w-full rounded-lg border border-gray-300" id="videologin"></video>
                </div>
            </div>
        </div>
    </div>

    {{-- Login Form --}}
    <section class="bg-gray-50">
        <div class="flex flex-col items-center justify-center px-6 py-8 mx-auto md:h-screen lg:py-0">

            <a href="/" class="flex items-center mb-6 text-2xl font-semibold text-gray-900">
                <img class="w-[200px] h-auto mr-2" src="{{ asset('images/fuji_logo.png') }}" alt="logo">
            </a>

            <div class="w-full bg-white rounded-lg shadow md:mt-0 sm:max-w-md xl:p-0">
                <div class="p-6 space-y-4 md:space-y-6 sm:p-8">

                    <h1 class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl">
                        Sign in to your account
                    </h1>

                    <form class="space-y-4 md:space-y-6" action="{{ route('login.post') }}" method="POST">
                        @csrf

                        {{-- User ID --}}
                        <div>
                            <label for="userid" class="block mb-2 text-sm font-medium text-gray-900">User ID</label>
                            <p id="userid-error" class="hidden text-xs text-blue-500 mt-1 mb-1">
                                UserId must be exactly 4 digits
                            </p>
                            <input type="text" name="userid" id="userid"
                                maxlength="4" inputmode="numeric"
                                class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5"
                                placeholder="xxxx"
                                value="{{ old('userid') }}"
                                required />
                            @error('userid')
                                <p class="text-xs text-red-500 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div>
                            <label for="password" class="block mb-2 text-sm font-medium text-gray-900">Password</label>
                            <input type="password" name="password" id="password"
                                placeholder="••••••••"
                                class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5"
                                required />
                            @error('password')
                                <p class="text-xs text-red-500 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Errors --}}
                        @if ($errors->any())
                            <ul class="px-4 py-2 bg-red-100 mt-2 rounded">
                                @foreach ($errors->all() as $error)
                                    <li class="my-1 text-red-500">{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <button type="submit" id="login"
                            class="cursor-pointer w-full text-white hover:bg-blue-700 bg-blue-600 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                            Sign in
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div> {{-- end single root --}}