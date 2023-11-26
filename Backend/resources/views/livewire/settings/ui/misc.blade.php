<div class=" w-full md:w-10/12 lg:w-6/12">
    <x-form noClass="true" action="saveMiscSettings">
        <x-details.item title="{{ __('Call') }}">
            <x-checkbox title="{{ __('Customer - Vendor') }}"
                description="{{ __('Allow customer to call vendor and vice versa') }}" name="canCustomerVendorCall"
                :defer="true" />
            <x-checkbox title="{{ __('Customer - Driver') }}"
                description="{{ __('Allow customer to call driver and vice versa') }}" name="canCustomerDriverCall"
                :defer="true" />
            <x-checkbox title="{{ __('Driver - Vendor') }}"
                description="{{ __('Allow driver to call vendor and vice versa') }}" name="canDriverVendorCall"
                :defer="true" />
        </x-details.item>
        <hr class="my-4" />
        <x-details.item title="{{ __('Chat') }}">
            <p class="mt-4 text-sm font-semibold">{{ __('Vendor Chat') }}</p>
            <div class='grid grid-cols-1 gap-4 md:grid-cols-2'>
                <x-checkbox title="{{ __('Enable') }}"
                    description="{{ __('Allow chat between vendor and customer/driver') }}" name="canVendorChat"
                    :defer="true" />
                <x-checkbox title="{{ __('Enable') }}"
                    description="{{ __('Allow image sharing in chat between vendor and customer/driver') }}"
                    name="canVendorChatSupportMedia" :defer="true" />

            </div>
            <p class="mt-4 text-sm font-semibold">{{ __('Customer Chat') }}</p>
            <div class='grid grid-cols-1 gap-4 md:grid-cols-2'>
                <x-checkbox title="{{ __('Enable') }}"
                    description="{{ __('Allow chat between customer and vendor/driver') }}" name="canCustomerChat"
                    :defer="true" />
                <x-checkbox title="{{ __('Enable') }}"
                    description="{{ __('Allow image sharing in chat between customer and vendor/driver') }}"
                    name="canCustomerChatSupportMedia" :defer="true" />
            </div>
            <p class="mt-4 text-sm font-semibold">{{ __('Driver Chat') }}</p>
            <div class='grid grid-cols-1 gap-4 md:grid-cols-2'>
                <x-checkbox title="{{ __('Enable') }}"
                    description="{{ __('Allow chat between driver and customer/vendor') }}" name="canDriverChat"
                    :defer="true" />
                <x-checkbox title="{{ __('Enable') }}"
                    description="{{ __('Allow image sharing in chat between driver and customer/vendor') }}"
                    name="canDriverChatSupportMedia" :defer="true" />
            </div>
        </x-details.item>

        <x-buttons.primary title="{{ __('Save') }}" />
    </x-form>
</div>
