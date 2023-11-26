<?php

namespace App\Http\Livewire\Select;

use Illuminate\Support\Collection;
use App\Models\Menu;

class VendorMenuSelect extends BaseLivewireSelect
{

    public function options($searchTerm = null): Collection
    {
        $vendorId = $this->getDependingValue('vendor_id') ?? \Auth::user()->vendor_id ?? "";
        return Menu::where('vendor_id', $vendorId)
            ->limit(10)
            ->get()
            ->map(function ($model) {
                return [
                    'value' => $model->id,
                    'description' => $model->name,
                ];
            });
    }


    public function selectedOption($value)
    {
        if ($value != null) {
            $this->selectValue(null);
            $this->searchTerm = null;
        }
        return [
            'value' =>  "",
            'description' => "",
        ];
    }
}
