<div class="space-y-2">
    <div class="flex items-center gap-2">
        <button wire:click="$toggle('showMain')" class="filament-button filament-button-size-sm">Ver subcategorías</button>
        <div class="text-sm text-gray-600">{{ $category->children->count() }} subcategorías · {{ $category->products->count() }} productos</div>
    </div>

    <div wire:loading.delay class="text-sm text-gray-500">Cargando...</div>

    <div>
        @foreach($category->children as $sub)
            <div class="border rounded px-2 py-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="font-medium">{{ $sub->name }}</div>
                        <div class="text-sm text-gray-600">{{ $sub->products->count() }} productos</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="toggleSubcategory({{ $sub->id }})" class="filament-button filament-button-size-sm">Ver</button>
                    </div>
                </div>

                @if(in_array($sub->id, $expandedSubcategories))
                    <div class="mt-2">
                        @if($sub->products->isEmpty())
                            <div class="text-sm text-gray-600">No hay productos en esta subcategoría.</div>
                        @else
                            <table class="w-full" style="border-collapse: collapse">
                                <thead>
                                    <tr class="text-left text-sm text-gray-600"><th>Nombre</th><th>Stock</th><th>Ingredientes / Descripción</th><th>Acciones</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($sub->products as $p)
                                        <tr class="align-top border-t">
                                            <td class="py-2">@if(isset($editingProduct[$p->id]))
                                                    <input wire:model.defer="productDraft.{{ $p->id }}.name" class="border rounded px-2 py-1 w-full" />
                                                @else
                                                    {{ $p->name }}
                                                @endif
                                            </td>
                                            <td class="py-2">@if(isset($editingProduct[$p->id]))
                                                    <input wire:model.defer="productDraft.{{ $p->id }}.stock" type="number" class="border rounded px-2 py-1 w-24" />
                                                @else
                                                    {{ $p->stock ?? '-' }}
                                                @endif
                                            </td>
                                            <td class="py-2">@if(isset($editingProduct[$p->id]))
                                                    <textarea wire:model.defer="productDraft.{{ $p->id }}.description" class="border rounded px-2 py-1 w-full" rows="2"></textarea>
                                                @else
                                                    {{ Str::limit($p->description, 200) }}
                                                @endif
                                            </td>
                                            <td class="py-2">
                                                @if(isset($editingProduct[$p->id]))
                                                    <button wire:click="updateProduct({{ $p->id }})" class="filament-button filament-button-size-sm">Guardar</button>
                                                    <button wire:click="cancelEditProduct({{ $p->id }})" class="filament-button filament-button-size-sm">Cancelar</button>
                                                @else
                                                    <a href="{{ \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $p->id]) }}" class="filament-link">Editar</a>
                                                    <button onclick="if(confirm('Eliminar producto?')) { @this.deleteProduct({{ $p->id }}) }" class="filament-button filament-button-size-sm text-danger">Eliminar</button>
                                                    <button wire:click="startEditProduct({{ $p->id }})" class="filament-button filament-button-size-sm">Editar inline</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
