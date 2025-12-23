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
            <h3 class="text-lg font-medium leading-6 text-gray-900">Create Product</h3>
            <p class="mt-1 text-sm text-gray-600">
                This information will be saved locally and immediately synced to Shopify.
            </p>
        </div>
    </div>
    <div class="mt-5 md:col-span-2 md:mt-0">
        <form action="{{ route('products.store') }}" method="POST">
            @csrf
            <div class="shadow sm:overflow-hidden sm:rounded-md">
                <div class="space-y-6 bg-white px-4 py-5 sm:p-6">
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6 sm:col-span-4">
                            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input type="text" name="title" id="title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2" required>
                        </div>

                        <div class="col-span-6">
                            <label for="body_html" class="block text-sm font-medium text-gray-700">Description (HTML)</label>
                            <div class="mt-1">
                                <textarea id="body_html" name="body_html" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2"></textarea>
                            </div>
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="vendor" class="block text-sm font-medium text-gray-700">Vendor</label>
                            <input type="text" name="vendor" id="vendor" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="product_type" class="block text-sm font-medium text-gray-700">Product Type</label>
                            <input type="text" name="product_type" id="product_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-2">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 bg-white py-2 px-3 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm border">
                                <option value="active">Active</option>
                                <option value="draft">Draft</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Variants</h3>
                        <div id="variants-container" class="space-y-4">
                            <!-- Variant Rows will be added here -->
                            <div class="variant-row grid grid-cols-12 gap-4 items-end border p-4 rounded bg-gray-50">
                                <div class="col-span-3">
                                    <label class="block text-xs font-medium text-gray-500">Option 1 (Size)</label>
                                    <input type="text" name="variants[0][option1]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-1">
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-xs font-medium text-gray-500">Option 2 (Color)</label>
                                    <input type="text" name="variants[0][option2]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-1">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-500">Price</label>
                                    <input type="number" step="0.01" name="variants[0][price]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-1" required>
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-500">SKU</label>
                                    <input type="text" name="variants[0][sku]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-1">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-500">Inventory</label>
                                    <input type="number" name="variants[0][inventory_quantity]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-1">
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="addVariant()" class="mt-4 inline-flex items-center rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Add Another Variant
                        </button>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                    <button type="submit" id="submit-btn" class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        Save & Sync
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex flex-col items-center">
            <svg class="animate-spin h-10 w-10 text-indigo-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Creating Product...</h3>
            <p class="text-sm text-gray-500 text-center">Please wait while we create the product and sync it to Shopify.</p>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="success-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex flex-col items-center">
            <div class="rounded-full bg-green-100 p-3 mb-4">
                <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Success!</h3>
            <p class="text-sm text-gray-500 text-center mb-4">Product created and synced to Shopify successfully.</p>
            <a href="{{ route('products.index') }}" class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Back to Products
            </a>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div id="error-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex flex-col items-center">
            <div class="rounded-full bg-red-100 p-3 mb-4">
                <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Error!</h3>
            <p id="error-message" class="text-sm text-gray-500 text-center mb-4">Something went wrong.</p>
            <button onclick="closeErrorModal()" class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    let variantCount = 1;
    function addVariant() {
        const container = document.getElementById('variants-container');
        const template = `
            <div class="variant-row grid grid-cols-12 gap-4 items-end border p-4 rounded bg-gray-50 mt-4 relative">
                 <button type="button" onclick="this.parentElement.remove()" class="absolute top-1 right-1 text-red-500 hover:text-red-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="col-span-3">
                    <label class="block text-xs font-medium text-gray-500">Option 1 (Size)</label>
                    <input type="text" name="variants[${variantCount}][option1]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-1">
                </div>
                <div class="col-span-3">
                    <label class="block text-xs font-medium text-gray-500">Option 2 (Color)</label>
                    <input type="text" name="variants[${variantCount}][option2]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-1">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-500">Price</label>
                    <input type="number" step="0.01" name="variants[${variantCount}][price]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-1" required>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-500">SKU</label>
                    <input type="text" name="variants[${variantCount}][sku]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-1">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-500">Inventory</label>
                    <input type="number" name="variants[${variantCount}][inventory_quantity]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border p-1">
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', template);
        variantCount++;
    }

    // Async form submission
    document.querySelector('form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = document.getElementById('submit-btn');
        const loadingOverlay = document.getElementById('loading-overlay');
        const successModal = document.getElementById('success-modal');
        const errorModal = document.getElementById('error-modal');
        const errorMessage = document.getElementById('error-message');

        // Show loading
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        loadingOverlay.classList.remove('hidden');
        loadingOverlay.classList.add('flex');

        // Get form data
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => {
            if (key === '_token') return;
            if (key.includes('[')) {
                const keys = key.split(/\[|\]/).filter(k => k);
                if (!data[keys[0]]) data[keys[0]] = [];
                const index = parseInt(keys[1]);
                if (!data[keys[0]][index]) data[keys[0]][index] = {};
                data[keys[0]][index][keys[2]] = value;
            } else {
                data[key] = value;
            }
        });

        try {
            const response = await fetch('{{ route('products.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]').value
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                // Hide loading, show success
                loadingOverlay.classList.add('hidden');
                loadingOverlay.classList.remove('flex');
                successModal.classList.remove('hidden');
                successModal.classList.add('flex');
            } else {
                throw new Error(result.message || 'Failed to create product');
            }
        } catch (error) {
            // Hide loading, show error
            loadingOverlay.classList.add('hidden');
            loadingOverlay.classList.remove('flex');
            errorMessage.textContent = error.message || 'Something went wrong while creating the product.';
            errorModal.classList.remove('hidden');
            errorModal.classList.add('flex');

            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    });

    function closeErrorModal() {
        const errorModal = document.getElementById('error-modal');
        errorModal.classList.add('hidden');
        errorModal.classList.remove('flex');
    }
</script>
@endsection
