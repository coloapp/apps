<?php

namespace App\Http\Livewire;

use App\Models\Menu;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Tag;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProductLivewire extends BaseLivewireComponent
{

    //
    public $model = Product::class;

    //
    public $name;
    public $description;
    public $price;
    public $sku;
    public $barcode;
    public $discount_price = 0;
    public $capacity;
    public $unit;
    public $package_count;
    public $available_qty;
    public $vendorID;
    public $vendor;
    public $plus_option;
    public $digital;
    public $deliverable = 1;
    public $isActive = 1;
    public $in_order = 1;
    public $age_restricted = 0;

    //
    public $menusIDs = [];
    public $categoriesIDs;
    public $subCategoriesIDs = [];
    public $photos = [];
    public $digitalFile;
    //
    public $showAssignSubcategories = false;
    public $subCategories = [];
    public $categorySearchClause = [];

    //tags
    public $tagList = [];
    public $selectedTagIds = [];
    public $selectedTags;
    //menu
    public $selectedMenuIds = [];
    public $selectedMenus;
    // subcategories
    public $selectedSubcategoryIds = [];
    public $selectedSubcategories;



    protected $rules = [
        "name" => "required|string",
        "price" => "required|numeric",
        "vendorID" => "required|exists:vendors,id",
        "photos" => "nullable|array",
    ];


    protected $messages = [
        "vendorID.exists" => "Invalid vendor selected",
    ];




    public function getListeners()
    {
        return $this->listeners + [
            'setOutOfStock' => 'setOutOfStock',
            'tag_idUpdated' => "tagSelected",
            'menu_idUpdated' => "menuSelected",
            'subcategory_idUpdated' => "subcategorySelected",
        ];
    }

    public function render()
    {

        return view('livewire.products', [
            "vendors" => [],
            "menus" => Menu::active()->where('vendor_id', $this->vendorID)->get(),
            "categories" => [],
            "subcategories" => [],
        ]);
    }

    //for tag select
    public function tagSelected($value)
    {
        //
        if ($value == null || !$value) {
            return;
        }
        //
        $this->selectedTagIds ??= [];
        //if is not array
        if (!is_array($this->selectedTagIds)) {
            $this->selectedTagIds = [];
        }
        $tagId = $value['value'];
        //add to array if not already added
        if (!in_array($tagId, $this->selectedTagIds)) {
            $this->selectedTagIds[] = $tagId;
        }
        //
        $this->selectedTags = Tag::whereIn('id', $this->selectedTagIds)->get();
        //emit to clear selection
        $this->emitUp('tag_idUpdated', null);
    }
    public function removeSelectedTag($id)
    {
        $this->selectedTags = $this->selectedTags->reject(function ($element) use ($id) {
            return $element->id == $id;
        });

        //
        $this->selectedTagIds = $this->selectedTags->pluck('id') ?? [];
    }

    //for menu select
    public function menuSelected($value)
    {
        //
        if ($value == null || !$value) {
            return;
        }
        //
        $this->selectedMenuIds ??= [];
        //if is not array
        if (!is_array($this->selectedMenuIds)) {
            $this->selectedMenuIds = [];
        }
        $tagId = $value['value'];
        //add to array if not already added
        if (!in_array($tagId, $this->selectedMenuIds)) {
            $this->selectedMenuIds[] = $tagId;
        }
        //
        $this->selectedMenus = Menu::whereIn('id', $this->selectedMenuIds)->get();
        //emit to clear selection
        $this->emitUp('menu_idUpdated', null);
    }

    public function removeSelectedMenu($id)
    {
        $this->selectedMenus = $this->selectedMenus->reject(function ($element) use ($id) {
            return $element->id == $id;
        });

        //
        $this->selectedMenuIds = $this->selectedMenus->pluck('id') ?? [];
    }

    //category selected
    public function autocompleteCategorySelected($item)
    {
        parent::autocompleteCategorySelected($item);
        $this->loadSubcategoriesSelector();
    }
    public function loadSubcategoriesSelector()
    {
        //
        $this->emit('category_idUpdated', [
            'value' => json_encode($this->categoriesIDs),
            "name" => "category_id",
        ]);
    }

    public function removeSelectedCategory($id)
    {
        parent::removeSelectedCategory($id);
        //get all the subcategories of the removed category from the selected subcategories
        $toBeRemovedSubcategories = $this->selectedSubcategories->filter(function ($subcategory) use ($id) {
            return $subcategory->category_id == $id;
        });
        //remove the subcategories
        foreach ($toBeRemovedSubcategories as $subcategory) {
            $this->removeSelectedSubcategory($subcategory->id);
        }
    }


    //for subcategory select
    public function subcategorySelected($value)
    {
        //
        if ($value == null || !$value) {
            return;
        }
        //
        $this->selectedSubcategoryIds ??= [];
        //if is not array
        if (!is_array($this->selectedSubcategoryIds)) {
            $this->selectedSubcategoryIds = [];
        }
        $tagId = $value['value'];
        //add to array if not already added
        if (!in_array($tagId, $this->selectedSubcategoryIds)) {
            $this->selectedSubcategoryIds[] = $tagId;
        }
        //
        $this->selectedSubcategories = Subcategory::whereIn('id', $this->selectedSubcategoryIds)->get();
        $this->subCategoriesIDs = $this->selectedSubcategoryIds;
        //emit to clear selection
        $this->emitUp('subcategory_idUpdated', null);
    }

    public function removeSelectedSubcategory($id)
    {
        $this->selectedSubcategories = $this->selectedSubcategories->reject(function ($element) use ($id) {
            return $element->id == $id;
        });

        //
        $this->selectedSubcategoryIds = $this->selectedSubcategories->pluck('id') ?? [];
        $this->subCategoriesIDs = $this->selectedSubcategoryIds;
    }


    //
    public function showCreateModal()
    {
        $this->reset();
        $this->showCreate = true;
        $this->emit('preselectedVendorEmit', \Auth::user()->vendor->name ?? "");
        $this->emit('loadSummerNote', "newContent", "");
    }

    public function save()
    {

        $this->validatePhotos();
        if (empty($this->vendorID)) {
            $this->vendorID = \Auth::user()->vendor_id;
        }
        //validate
        $this->validate();

        try {


            DB::beginTransaction();
            $model = new Product();
            $model->name = $this->name;
            $model->sku = $this->sku ?? null;
            $model->barcode = $this->barcode ?? null;
            $model->description = $this->description;
            $model->price = $this->price;
            $model->discount_price = $this->discount_price;
            $model->capacity = $this->capacity;
            $model->unit = $this->unit;
            $model->package_count = $this->package_count;
            $model->available_qty = !empty($this->available_qty) ? $this->available_qty : null;
            $model->vendor_id = $this->vendorID ?? \Auth::user()->vendor_id;
            $model->featured = false;
            $model->plus_option = $this->plus_option ?? false;
            $model->digital = $this->digital ?? false;
            $model->deliverable = $this->digital ? false : $this->deliverable;
            $model->is_active = $this->isActive;
            $model->in_order = $this->in_order;
            $model->age_restricted = $this->age_restricted;
            $model->save();

            if ($this->photos) {

                $model->clearMediaCollection();
                foreach ($this->photos as $photo) {
                    $model->addMedia($photo)
                        ->usingFileName(genFileName($photo))
                        ->toMediaCollection();
                }
                $this->photos = null;
            }

            if ($this->digitalFile && $this->digital) {

                $model->clearDigitalFiles();
                $model->saveDigitalFile($this->digitalFile);
                $this->digitalFile = null;
            }

            //
            $model->categories()->attach($this->categoriesIDs);
            $model->sub_categories()->attach($this->subCategoriesIDs);
            $model->tags()->sync($this->selectedTags);
            $model->menus()->sync($this->selectedMenus);

            DB::commit();

            $this->dismissModal();
            $this->reset();
            $this->showSuccessAlert(__("Product") . " " . __('created successfully!'));
            $this->emit('refreshTable');
        } catch (Exception $error) {
            DB::rollback();
            $this->showErrorAlert($error->getMessage() ?? __("Product") . " " . __('creation failed!'));
        }
    }

    // Updating model
    public function initiateEdit($id)
    {
        $this->selectedModel = $this->model::find($id);
        $this->name = $this->selectedModel->name;
        $this->sku = $this->selectedModel->sku;
        $this->barcode = $this->selectedModel->barcode;
        $this->description = $this->selectedModel->description;
        $this->price = $this->selectedModel->price;
        $this->discount_price = $this->selectedModel->discount_price;
        $this->capacity = $this->selectedModel->capacity;
        $this->unit = $this->selectedModel->unit;
        $this->package_count = $this->selectedModel->package_count;
        $this->available_qty = $this->selectedModel->available_qty;
        $this->vendorID = $this->selectedModel->vendor_id;
        $this->vendor = $this->selectedModel->vendor;
        $this->plus_option = $this->selectedModel->plus_option ?? true;
        $this->digital = $this->selectedModel->digital;
        $this->deliverable = $this->selectedModel->deliverable;
        $this->isActive = $this->selectedModel->is_active;
        $this->in_order = $this->selectedModel->in_order;
        $this->age_restricted = $this->selectedModel->age_restricted;

        $this->vendorID = $this->selectedModel->vendor_id;
        $this->emit('preselectedVendorEmit', $this->selectedModel->vendor->name ?? "");
        // categories
        $this->categoriesIDs = $this->selectedModel->categories()->pluck('category_id');
        $this->selectedCategories = Category::whereIn('id', $this->categoriesIDs)->get();
        $this->loadSubcategoriesSelector();
        //subcategories
        $this->subCategoriesIDs = $this->selectedModel->sub_categories()->pluck('id');
        $this->selectedSubcategoryIds = $this->subCategoriesIDs;
        $this->selectedSubcategories = Subcategory::whereIn('id', $this->subCategoriesIDs)->get();
        //tags
        $this->selectedTagIds = $this->selectedModel->tags->pluck('id');
        $this->selectedTags = Tag::whereIn('id', $this->selectedTagIds)->get();
        //menus
        $this->selectedMenuIds = $this->selectedModel->menus->pluck('id');
        $this->selectedMenus = Menu::whereIn('id', $this->selectedMenuIds)->get();
        //clear filepond
        $this->emit('filePondClear');
        //load photos and emit event to show them in filepond
        // $mPhotos = $this->selectedModel->getMedia();
        // foreach ($mPhotos as $photo) {
        //     $this->emit('filepond-add-file', "#editProductInput", $photo->getUrl());
        // }
        $this->photos = [];
        //load summernote with selected product description
        $this->emit('loadSummerNote', "editContent", $this->description);
        //
        $this->emit('showEditModal');
    }

    public function update()
    {
        //validate
        $this->validate(
            [
                "name" => "required|string",
                "price" => "required|numeric",
                "vendorID" => "required|exists:vendors,id",
            ]
        );

        $this->validatePhotos();

        try {

            DB::beginTransaction();
            $model = $this->selectedModel;
            $model->name = $this->name;
            $model->sku = $this->sku ?? null;
            $model->barcode = $this->barcode ?? null;
            $model->description = $this->description;
            $model->price = $this->price;
            $model->discount_price = $this->discount_price;
            $model->capacity = $this->capacity;
            $model->unit = $this->unit;
            $model->package_count = $this->package_count;
            $model->available_qty = $this->available_qty; //!empty($this->available_qty) ? $this->available_qty : null;
            $model->vendor_id = $this->vendorID;
            $model->plus_option = $this->plus_option ?? true;
            $model->digital = $this->digital;
            $model->deliverable = $this->digital ? false : $this->deliverable;
            $model->is_active = $this->isActive;
            $model->in_order = $this->in_order;
            $model->age_restricted = $this->age_restricted;
            $model->save();

            if ($this->photos) {

                $model->clearMediaCollection();
                foreach ($this->photos as $photo) {
                    $model->addMedia($photo)
                        ->usingFileName(genFileName($photo))
                        ->toMediaCollection();
                }
                $this->photos = null;
            }

            if ($this->digitalFile && $this->digital) {

                $model->clearDigitalFiles();
                $model->saveDigitalFile($this->digitalFile);
                // collect($this->digitalFiles)->each(
                //     function ($digitalFile)use ($model) {
                //         $model->saveDigitalFile($digitalFile);
                //     }
                // );
                $this->digitalFile = null;
            }

            //
            $model->categories()->sync($this->categoriesIDs);
            $model->sub_categories()->sync($this->subCategoriesIDs);
            $model->tags()->sync($this->selectedTags);
            $model->menus()->sync($this->selectedMenus);

            DB::commit();

            $this->dismissModal();
            $this->reset();
            $this->showSuccessAlert(__("Product") . " " . __('updated successfully!'));
            $this->emit('refreshTable');
        } catch (Exception $error) {
            DB::rollback();
            $this->showErrorAlert($error->getMessage() ?? __("Product") . " " . __('updated failed!'));
        }
    }

    public function validatePhotos()
    {
        //check the length of the selected photos
        $maxPhotoCount = (int) setting('filelimit.max_product_images', 3);
        if ($this->photos != null && count($this->photos) > $maxPhotoCount) {
            $errorMsg = __("You can only select") . " " . $maxPhotoCount . " " . __("photos");
            $this->addError('photos', $errorMsg);
            return;
        }
    }

    //
    public function textAreaChange($data)
    {
        $this->description = $data;
    }

    public function vendorChange($data)
    {
        $this->vendorID = $data;
        $this->vendor = Vendor::find($this->vendorID);
        $vendor = $this->vendor;
        if (!empty($vendor) && !empty($vendor->vendor_type_id)) {
            $this->categorySearchClause = ['vendor_type_id' => $vendor->vendor_type_id];
            $this->emit('categoryQueryClasueUpdate', $this->categorySearchClause);
        }
    }

    public function autocompleteVendorSelected($vendor)
    {
        $this->vendorID = $vendor["id"];
        $this->vendor = Vendor::find($this->vendorID);
        $this->emit('vendor_idUpdated', [
            'value' => $this->vendorID,
            "name" => "vendor_id",
        ]);
        //
        $this->emit('vendor_type_idUpdated', [
            'value' => $this->vendor->vendor_type->id ?? "",
            "name" => "vendor_type_id",
        ]);
    }


    //
    public function photoSelected($photos)
    {
        $this->photos = $photos;
    }


    public function getVendors()
    {
        $vendors = [];
        if (User::find(Auth::id())->hasRole('admin')) {
            $this->vendorID = Vendor::active()->first()->id ?? null;
            $vendors = Vendor::active()->get();
        } else {
            $this->vendorID = Auth::user()->vendor_id;
            $vendors = Vendor::where('id', $this->vendorID)->get();
        }
        return $vendors;
    }

    public function getCategories()
    {
        $selectedVendor = Vendor::find($this->vendorID);
        return Category::where('vendor_type_id', $selectedVendor->vendor_type_id ?? "")->get();
    }

    public function setOutOfStock($id)
    {
        try {

            DB::beginTransaction();
            $product = Product::find($id);
            $product->available_qty = 0;
            $product->save();
            DB::commit();

            $this->dismissModal();
            $this->reset();
            $this->showSuccessAlert(__("Product") . " " . __('updated successfully!'));
            $this->emit('refreshTable');
        } catch (Exception $error) {
            DB::rollback();
            $this->showErrorAlert($error->getMessage() ?? __("Product") . " " . __('updated failed!'));
        }
    }
}
