<?php

use App\Dashboard\Models\InspectorDb;
use App\Domain\Worker\InspectorID;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.guest')] class extends Component {
    public string $userId = '';
    public bool $showPasswordModal = false;
    public string $newPassword = '';
    public string $newPassword_confirmation = '';
    public ?string $status = null;

    public function mount(): void
    {
        $this->userId = (string) old('userid', '');
    }

    public function checkUserId(): void
    {
        $this->status = null;
        $this->resetValidation('userId');

        Log::info('User logged in', [
            'inspector_id' => $this->userId,
        ]);
        if (blank($this->userId)) {
            return;
        }

        $inspector = InspectorDb::where('EmployeeID', $this->userId)->first();
        if ($inspector) {
            Log::info('User logged in', [
                'inspector_id' => $this->userId,
                'has_password' => !blank($inspector->Password),
            ]);
            // Registered inspector with no password yet -> open the creator
            if (blank($inspector->Password)) {
                $this->showPasswordModal = true;
            }
        }
    }

    public function createPassword(): void
    {
        $this->validate([
            'newPassword' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'newPassword.required'  => 'Password is required.',
            'newPassword.min'       => 'Password must be at least 6 characters.',
            'newPassword.confirmed' => 'Passwords do not match.',
        ]);

        try {
            // whereNull makes it atomic: it only updates if still no password
            $updated = InspectorDb::where('EmployeeID', $this->userId)
                ->whereNull('Password')
                ->update(['Password' => $this->newPassword]);

            if ($updated === 0) {
                $this->addError('newPassword', 'This account already has a password.');
                return;
            }

            $this->closePasswordModal();
            $this->status = 'Password created. You can now sign in.';
        } catch (\Throwable $e) {
            Log::error('Password creation failed', [
                'inspector_id' => $this->userId,
                'error'        => $e->getMessage(),
            ]);

            $this->addError('newPassword', 'Failed to create password. Please try again.');
        }
    }

    public function closePasswordModal(): void
    {
        $this->reset(['showPasswordModal', 'newPassword', 'newPassword_confirmation']);
        $this->resetValidation();
    }
};
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
                        @if ($status)
                        <p class="px-4 py-2 text-sm text-green-700 bg-green-100 rounded">{{ $status }}</p>
                        @endif
                        <div>
                            <label for="userid" class="block mb-2 text-sm font-medium text-gray-900">User ID</label>
                            <p id="userid-error" class="hidden text-xs text-blue-500 mt-1 mb-1">
                                UserId must be exactly 6 digits
                            </p>
                            <input type="text" name="userid" id="userid"
                                maxlength="6" inputmode="numeric"
                                class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5"
                                placeholder="xxxxxx"
                                wire:model="userId"
                                wire:blur="checkUserId"
                                required />
                            @error('userId')
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

                    {{-- Password Creator Modal --}}
                    @if ($showPasswordModal)
                    <div wire:key="password-modal"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50">
                        <div class="w-full max-w-md bg-white rounded-lg shadow">

                            <div class="flex items-center justify-between p-4 md:p-5 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-900">Create your password</h3>
                                <button type="button"
                                    wire:click="closePasswordModal"
                                    class="text-gray-400 hover:bg-gray-200 hover:text-gray-900 rounded-lg w-8 h-8 inline-flex justify-center items-center">
                                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                                    </svg>
                                    <span class="sr-only">Close modal</span>
                                </button>
                            </div>

                            <form wire:submit="createPassword" class="p-4 md:p-5 space-y-4">
                                <p class="text-sm text-gray-500">
                                    ID <span class="font-medium text-gray-900">{{ $userId }}</span> has no password yet.
                                    Create one to continue.
                                </p>

                                <div>
                                    <label for="new_password" class="block mb-2 text-sm font-medium text-gray-900">New Password</label>
                                    <input type="password" id="new_password"
                                        wire:model="newPassword"
                                        autofocus
                                        placeholder="••••••••"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg block w-full p-2.5 focus:ring-blue-600 focus:border-blue-600">
                                    @error('newPassword')
                                    <p class="mt-1 text-xs text-red-500 font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="new_password_confirmation" class="block mb-2 text-sm font-medium text-gray-900">Confirm Password</label>
                                    <input type="password" id="new_password_confirmation"
                                        wire:model="newPassword_confirmation"
                                        placeholder="••••••••"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 rounded-lg block w-full p-2.5 focus:ring-blue-600 focus:border-blue-600">
                                </div>

                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button"
                                        wire:click="closePasswordModal"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="createPassword"
                                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                        <span wire:loading.remove wire:target="createPassword">Save Password</span>
                                        <span wire:loading wire:target="createPassword">Saving...</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div> {{-- end single root --}}