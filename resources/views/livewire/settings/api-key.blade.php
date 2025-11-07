<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Your API Key')" :subheading="__('Do not share with anyone.')">
        <div class="mt-6 space-y-6">
            {{ auth()->user()->public_api_key }}
        </div>
    </x-settings.layout>
</section>
