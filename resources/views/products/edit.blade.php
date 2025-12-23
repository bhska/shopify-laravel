@extends('layouts.app')

@section('content')
<div class="mb-6">
    <a href="{{ route('products.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back to Products
    </a>
</div>

@if ($errors->any())
    <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4">
        <div class="flex">
            <div class="shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">There were errors with your submission</h3>
                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

<div class="md:grid md:grid-cols-3 md:gap-6">
    <div class="md:col-span-1">
        <div class="px-4 sm:px-0">
            <h3 class="text-lg font-medium leading-6 text-gray-900">Edit Product</h3>
            <p class="mt-1 text-sm text-gray-600">
                Updating this product will sync changes to Shopify.
            </p>
            @if($product->shopify_product_id)
                <p class="mt-4 text-xs text-gray-500">
                    Linked to Shopify ID: {{ $product->shopify_product_id }}
                </p>
            @endif
        </div>
    </div>
    <div class="mt-5 md:col-span-2 md:mt-0">
        <form action="{{ route('products.update', $product) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="shadow sm:overflow-hidden sm:rounded-md">
                <div class="space-y-6 bg-white px-4 py-5 sm:p-6">
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6 sm:col-span-4">
                            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input type="text" name="title" id="title" value="{{ old('title', $product->title) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2 @error('title') border-red-500 @enderror" required>
                            @error('title')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-6">
                            <label for="body_html" class="block text-sm font-medium text-gray-700">Description (HTML)</label>
                            <div class="mt-1">
                                <textarea id="body_html" name="body_html" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2 @error('body_html') border-red-500 @enderror">{{ old('body_html', $product->body_html) }}</textarea>
                            </div>
                            @error('body_html')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="vendor" class="block text-sm font-medium text-gray-700">Vendor</label>
                            <input type="text" name="vendor" id="vendor" value="{{ old('vendor', $product->vendor) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="product_type" class="block text-sm font-medium text-gray-700">Product Type</label>
                            <input type="text" name="product_type" id="product_type" value="{{ old('product_type', $product->product_type) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 bg-white py-2 px-3 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm border">
                                <option value="active" {{ old('status', $product->status) === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="draft" {{ old('status', $product->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="archived" {{ old('status', $product->status) === 'archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 flex justify-between sm:px-6">
                    <a href="{{ route('products.show', $product) }}" class="inline-flex justify-center rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Update Product
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="hidden sm:block" aria-hidden="true">
    <div class="py-5">
        <div class="border-t border-gray-200"></div>
    </div>
</div>

<div class="md:grid md:grid-cols-3 md:gap-6">
    <div class="md:col-span-1">
        <div class="px-4 sm:px-0">
            <h3 class="text-lg font-medium leading-6 text-gray-900">Product Variants</h3>
            <p class="mt-1 text-sm text-gray-600">
                Current variants for this product.
            </p>
        </div>
    </div>
    <div class="mt-5 md:col-span-2 md:mt-0">
        <div class="shadow sm:overflow-hidden sm:rounded-md">
            <div class="bg-white">
                @if($product->variants->count() > 0)
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold text-gray-900 sm:pl-6">Option 1</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-gray-900">Option 2</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-gray-900">Price</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-gray-900">SKU</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-gray-900">Inventory</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-gray-900">Synced</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($product->variants as $variant)
                                <tr>
                                    <td class="whitespace-nowrap py-3 pl-4 pr-3 text-sm text-gray-900 sm:pl-6">{{ $variant->option1 ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">{{ $variant->option2 ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">${{ number_format($variant->price, 2) }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">{{ $variant->sku ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">{{ $variant->inventory_quantity ?? 0 }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-sm">
                                        @if($variant->shopify_variant_id)
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                                <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8">
                                                    <circle cx="4" cy="4" r="3" />
                                                </svg>
                                                Synced
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">
                                                <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-yellow-400" fill="currentColor" viewBox="0 0 8 8">
                                                    <circle cx="4" cy="4" r="3" />
                                                </svg>
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="px-4 py-5 text-center text-sm text-gray-500">
                        No variants for this product.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="hidden sm:block" aria-hidden="true">
    <div class="py-5">
        <div class="border-t border-gray-200"></div>
    </div>
</div>

<div class="md:grid md:grid-cols-3 md:gap-6">
    <div class="md:col-span-1">
        <div class="px-4 sm:px-0">
            <h3 class="text-lg font-medium leading-6 text-red-600">Danger Zone</h3>
            <p class="mt-1 text-sm text-gray-600">
                Irreversible actions.
            </p>
        </div>
    </div>
    <div class="mt-5 md:col-span-2 md:mt-0">
        <div class="shadow sm:overflow-hidden sm:rounded-md border border-red-200">
            <div class="bg-white px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">Delete this product</h4>
                        <p class="mt-1 text-sm text-gray-500">Once deleted, the product will be removed from both local database and Shopify.</p>
                    </div>
                    <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('Are you sure? This will delete the product locally and on Shopify. This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-red-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            Delete Product
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
