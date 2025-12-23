<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(protected ShopifyService $shopifyService) {}

    public function index(Request $request)
    {
        $forceSync = $request->get('sync', false);
        $syncResult = null;

        // Check if we need to sync from Shopify
        if ($forceSync || Product::count() === 0) {
            try {
                $syncResult = $this->shopifyService->importProductsFromShopify();

                $message = 'Sync completed! ';
                if ($syncResult['imported'] > 0) {
                    $message .= "Imported {$syncResult['imported']} new products. ";
                }
                if ($syncResult['updated'] > 0) {
                    $message .= "Updated {$syncResult['updated']} existing products. ";
                }

                if ($request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'sync_result' => $syncResult,
                    ]);
                }

                session()->flash('success', $message);
            } catch (\Exception $e) {
                $errorMessage = 'Failed to sync from Shopify: '.$e->getMessage();

                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                    ]);
                }

                session()->flash('error', $errorMessage);
            }
        }

        $products = Product::with(['variants', 'images'])->latest()->paginate(10);

        if ($request->ajax()) {
            return response()->json([
                'html' => view('products.partials.product-list', compact('products'))->render(),
                'sync_result' => $syncResult,
            ]);
        }

        return view('products.index', compact('products', 'syncResult'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(StoreProductRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $product = Product::create($request->validated());

                if ($request->has('variants')) {
                    foreach ($request->variants as $variantData) {
                        $product->variants()->create($variantData);
                    }
                }

                try {
                    $shopifyProduct = $this->shopifyService->syncProduct($product);
                    $shopifyId = $this->extractIdFromGid($shopifyProduct['id']);
                    $product->update(['shopify_product_id' => $shopifyId]);

                    // Update variants IDs
                    if (isset($shopifyProduct['variants']['edges']) && ! empty($shopifyProduct['variants']['edges'])) {
                        $shopifyVariants = collect($shopifyProduct['variants']['edges']);
                        foreach ($product->variants as $localVariant) {
                            $match = $shopifyVariants->first(function ($edge) use ($localVariant) {
                                $sv = $edge['node'];

                                // Match by SKU first, then by title if SKU is not available
                                return (! empty($sv['sku']) && $sv['sku'] == $localVariant->sku)
                                    || $sv['title'] == $localVariant->title;
                            });

                            if ($match) {
                                $variantId = $this->extractIdFromGid($match['node']['id']);
                                $localVariant->update(['shopify_variant_id' => $variantId]);
                            }
                        }
                    }

                } catch (\Exception $e) {
                    // In a real app, we might want to catch this and redirect with error
                    // but for now let's bubble up or just flash
                    throw $e;
                }
            });

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product created and synced to Shopify successfully!',
                ], 200);
            }

            return redirect()->route('products.index')->with('success', 'Product created and synced to Shopify!');
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create product: '.$e->getMessage(),
                ], 500);
            }

            return back()->withInput()->with('error', 'Failed to create product: '.$e->getMessage());
        }
    }

    public function show(Product $product)
    {
        $product->load(['variants', 'images']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $product->load('variants');

        return view('products.edit', compact('product'));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($request->validated());
            $this->shopifyService->syncProduct($product);
        });

        return redirect()->route('products.index')->with('success', 'Product updated!');
    }

    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            $shopifyId = $product->shopify_product_id;
            $product->delete();
            if ($shopifyId) {
                $this->shopifyService->deleteProduct($shopifyId);
            }
        });

        return redirect()->route('products.index')->with('success', 'Product deleted!');
    }

    public function uploadImage(Request $request, Product $product)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        if (! $product->shopify_product_id) {
            return back()->with('error', 'Product must be synced to Shopify first before uploading images.');
        }

        $file = $request->file('image');

        try {
            $shopifyImage = $this->shopifyService->uploadProductImage($product, $file);
            $imageId = $this->extractIdFromGid($shopifyImage['id']);

            $product->images()->create([
                'shopify_image_id' => $imageId,
                'path' => $shopifyImage['url'],
            ]);

            return back()->with('success', 'Image uploaded to Shopify successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to upload image to Shopify: '.$e->getMessage());
        }
    }

    /**
     * Sync products from Shopify to local database.
     */
    public function syncFromShopify(Request $request)
    {
        try {
            $syncResult = $this->shopifyService->importProductsFromShopify();

            $message = 'Sync completed! ';
            if ($syncResult['imported'] > 0) {
                $message .= "Imported {$syncResult['imported']} new products. ";
            }
            if ($syncResult['updated'] > 0) {
                $message .= "Updated {$syncResult['updated']} existing products. ";
            }

            if ($syncResult['total'] === 0) {
                $message = 'No products found in Shopify to sync.';
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'sync_result' => $syncResult,
                ]);
            }

            return redirect()->route('products.index')->with('success', $message);
        } catch (\Exception $e) {
            $errorMessage = 'Failed to sync from Shopify: '.$e->getMessage();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ]);
            }

            return redirect()->route('products.index')->with('error', $errorMessage);
        }
    }

    /**
     * Extract numeric ID from Shopify GID.
     * Example: "gid://shopify/Product/123456" -> 123456
     */
    protected function extractIdFromGid(string $gid): int
    {
        $parts = explode('/', $gid);

        return (int) end($parts);
    }
}
