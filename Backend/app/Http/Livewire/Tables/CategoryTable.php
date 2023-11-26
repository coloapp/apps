<?php

namespace App\Http\Livewire\Tables;

use App\Models\Category;
use Rappasoft\LaravelLivewireTables\Views\Column;

class CategoryTable extends OrderingBaseDataTableComponent
{

    public $model = Category::class;
    public $header_view = 'components.buttons.new';

    public function query()
    {
        return Category::with('vendor_type')->withCount('sub_categories');
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), "id")->searchable()->sortable(),
            $this->xsImageColumn(),
            Column::make(__('Name'), 'name')->searchable()->sortable(),
            Column::make(__('Vendor Type'), 'vendor_type.name'),
            Column::make(__('No Subcategories'), 'sub_categories_count')->sortable(),
            $this->activeColumn(),
            Column::make(__('Created At'), 'formatted_date'),
            $this->actionsColumn(),
        ];
    }
}
