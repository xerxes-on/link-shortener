<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Profile Information Section -->
        <x-filament::section>
            <x-slot name="heading">
                Profile Information
            </x-slot>

            <x-slot name="description">
                Update your account profile information and email address.
            </x-slot>

            <form wire:submit="updateProfile" class="space-y-6">
                {{ $this->profileForm }}

                <div class="flex justify-end">
                    <x-filament::button type="submit">
                        Update Profile
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <!-- Update Password Section -->
        <x-filament::section>
            <x-slot name="heading">
                Update Password
            </x-slot>

            <x-slot name="description">
                Ensure your account is using a long, random password to stay secure.
            </x-slot>

            <form wire:submit="updatePassword" class="space-y-6">
                {{ $this->passwordForm }}

                <div class="flex justify-end">
                    <x-filament::button type="submit">
                        Update Password
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>
