<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class CategoryProductsPanel extends Component
{
    public int $categoryId;
    public Category $category;

    // Track which subcategory ids are expanded
    public array $expandedSubcategories = [];

    // Track product editing state: productId => true
    public array $editingProduct = [];

    // Temporary holder for editing fields
    public array $productDraft = [];

    public function mount($categoryId)
    {
        $this->categoryId = (int) $categoryId;
        $this->loadCategory();
    }

    public function loadCategory(): void
    {
        $this->category = Category::with(['children', 'products'])->findOrFail($this->categoryId);
    }

    public function toggleSubcategory(int $subId): void
    {
        if (in_array($subId, $this->expandedSubcategories, true)) {
            $this->expandedSubcategories = array_filter($this->expandedSubcategories, fn($id) => $id !== $subId);
        } else {
            $this->expandedSubcategories[] = $subId;
        }
    }

    public function startEditProduct(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $this->editingProduct[$productId] = true;
        $this->productDraft[$productId] = [
            'name' => $product->name,
            'description' => $product->description,
            'stock' => $product->stock,
        ];
    }

    public function cancelEditProduct(int $productId): void
    {
        unset($this->editingProduct[$productId]);
        unset($this->productDraft[$productId]);
    }

    public function updateProduct(int $productId): void
    {
        $data = $this->productDraft[$productId] ?? null;
        if (!$data) {
            $this->dispatchBrowserEvent('notify', ['type' => 'danger', 'message' => 'No hay datos para actualizar.']);
            return;
        }

        $product = Product::findOrFail($productId);
        $product->update([
            'name' => $data['name'] ?? $product->name,
            'description' => $data['description'] ?? $product->description,
            'stock' => $data['stock'] ?? $product->stock,
        ]);

        unset($this->editingProduct[$productId]);
        unset($this->productDraft[$productId]);

        $this->loadCategory();
        $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Producto actualizado']);
    }

    public function deleteProduct(int $productId): void
    {
        $product = Product::find($productId);
        if (!$product) {
            $this->dispatchBrowserEvent('notify', ['type' => 'danger', 'message' => 'Producto no encontrado']);
            return;
        }

        $product->delete();
        $this->loadCategory();
        $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Producto eliminado']);
    }

    public function render()
    {
        return view('livewire.category-products-panel', [
            'category' => $this->category,
            'expandedSubcategories' => $this->expandedSubcategories,
            'editingProduct' => $this->editingProduct,
            'productDraft' => $this->productDraft,
        ]);
    }
}
