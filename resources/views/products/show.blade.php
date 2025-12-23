@extends('layouts.app')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('products.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back to Products
    </a>
    <div class="flex gap-2">
        <a href="{{ route('products.edit', $product) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit
        </a>
    </div>
</div>

<div class="overflow-hidden bg-white shadow sm:rounded-lg mb-6">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium leading-6 text-gray-900">{{ $product->title }}</h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Product ID: {{ $product->id }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $product->status === 'active' ? 'bg-green-100 text-green-800' : ($product->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                    {{ ucfirst($product->status) }}
                </span>
                @if($product->shopify_product_id)
                    <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">
                        <svg class="mr-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        Synced to Shopify
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-orange-100 px-3 py-1 text-sm font-medium text-orange-800">
                        <svg class="mr-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                        </svg>
                        Not Synced
                    </span>
                @endif
            </div>
        </div>
    </div>
    <div class="px-4 py-5 sm:p-0">
        <dl class="sm:divide-y sm:divide-gray-200">
            <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">Vendor</dt>
                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{ $product->vendor ?: '-' }}</dd>
            </div>
            <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">Product Type</dt>
                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{ $product->product_type ?: '-' }}</dd>
            </div>
            <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">Shopify Product ID</dt>
                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">
                    @if($product->shopify_product_id)
                        <code class="bg-gray-100 px-2 py-1 rounded text-xs">{{ $product->shopify_product_id }}</code>
                    @else
                        <span class="text-gray-400">-</span>
                    @endif
                </dd>
            </div>
            <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">Description</dt>
                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0 prose prose-sm max-w-none">
                    @if($product->body_html)
                        {!! $product->body_html !!}
                    @else
                        <span class="text-gray-400">No description</span>
                    @endif
                </dd>
            </div>
            <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">Created</dt>
                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{ $product->created_at->format('M d, Y H:i') }}</dd>
            </div>
            <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{{ $product->updated_at->format('M d, Y H:i') }}</dd>
            </div>
        </dl>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="overflow-hidden bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg font-medium leading-6 text-gray-900">Product Images</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">{{ $product->images->count() }} image(s)</p>
        </div>
        <div class="px-4 py-5 sm:p-6">
            @if($product->images->count() > 0)
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach($product->images as $image)
                        <div class="relative group aspect-square">
                            <img src="{{ $image->url }}" alt="" class="h-full w-full object-cover rounded-lg border border-gray-200">
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 text-center py-4">No images yet.</p>
            @endif
            
            @if($product->shopify_product_id)
                <form action="{{ route('web.products.upload-image', $product) }}" method="POST" enctype="multipart/form-data" class="mt-6 pt-6 border-t border-gray-200">
                    @csrf
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload New Image to Shopify</label>
                    <div class="flex items-center gap-3">
                        <input type="file" name="image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required>
                        <button type="submit" class="shrink-0 inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Upload
                        </button>
                    </div>
                    @error('image')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </form>
            @else
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-sm text-yellow-600 text-center py-2">
                        <svg class="inline-block h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        Product must be synced to Shopify before uploading images.
                    </p>
                </div>
            @endif
        </div>
    </div>

    <div class="overflow-hidden bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg font-medium leading-6 text-gray-900">Variants</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">{{ $product->variants->count() }} variant(s)</p>
        </div>
        @if($product->variants->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold text-gray-900 sm:pl-6">Option 1</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-gray-900">Option 2</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-gray-900">Price</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-gray-900">SKU</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-gray-900">Inventory</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-semibold text-gray-900">Status</th>
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
                                            Synced
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">
                                            Pending
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-4 py-8 text-center">
                <p class="text-sm text-gray-500">No variants for this product.</p>
            </div>
        @endif
    </div>
</div>
@endsection
