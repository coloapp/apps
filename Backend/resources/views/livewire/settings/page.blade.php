<div wire:init="setupEditors">


    <x-form action="savePageSettings" :noClass="true">
        <div class="w-full md:w-8/12 lg:w-6/12">
            <div>

                <x-input.summernote name="driverDocumentInstructions"
                    title="{{ __('Driver Verification Document Instructions') }}" id="driverDocumentInstructionsEdit" />
            </div>
            <x-input title="{{ __('Max Driver Selectable Documents') }}" name="driverDocumentCount" type="number" />
            <hr class="my-12" />
            {{--  --}}
            <div>
                <x-input.summernote name="vendorDocumentInstructions"
                    title="{{ __('Vendor Verification Document Instructions') }}" id="vendorDocumentInstructionsEdit" />
            </div>
            <x-input title="{{ __('Max Vendor Selectable Documents') }}" name="vendorDocumentCount" type="number" />
            <x-buttons.primary title="{{ __('Save Changes') }}" />
        </div>
    </x-form>


</div>
