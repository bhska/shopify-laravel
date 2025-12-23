<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <x-rich-text::styles theme="richtextlaravel" data-turbo-track="false" />

    <style>
        /* Custom Trix Editor Styling - Subtle Borders */
        trix-editor {
            border: 1px solid #d1d5db !important; /* gray-300 */
            border-radius: 0.375rem !important;
        }

        trix-editor:focus {
            border-color: #4f46e5 !important; /* indigo-600 */
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1) !important;
            outline: none !important;
        }

        trix-toolbar {
            border: 1px solid #d1d5db !important; /* gray-300 */
            border-bottom: none !important;
            border-radius: 0.375rem 0.375rem 0 0 !important;
        }

        .trix-content {
            border-radius: 0 0 0.375rem 0.375rem !important;
        }

        /* Remove black border from toolbar button groups */
        .trix-button-group {
            border: none !important;
        }

        .trix-button {
            border-color: #e5e7eb !important; /* gray-200 */
        }

        .trix-button:hover {
            border-color: #d1d5db !important; /* gray-300 */
        }

        .trix-button--active {
            background-color: #e0e7ff !important; /* indigo-100 */
            border-color: #6366f1 !important; /* indigo-500 */
        }

        /* Dialog styling */
        .trix-dialog {
            border: 1px solid #d1d5db !important;
            border-radius: 0.5rem !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        }

        /* Global Border Styling for Tables and Cards - Subtle Borders */
        /* Table borders - more subtle */
        table {
            border-color: #e5e7eb !important; /* gray-200 instead of default black */
        }

        thead th {
            border-color: #e5e7eb !important; /* gray-200 */
        }

        tbody tr {
            border-color: #e5e7eb !important; /* gray-200 */
        }

        td, th {
            border-color: #e5e7eb !important; /* gray-200 */
        }

        /* Card borders */
        .shadow, .shadow-sm, .shadow-md, .shadow-lg {
            border: 1px solid #e5e7eb !important; /* subtle gray border */
        }

        sm\\:overflow-hidden, .sm\\:overflow-hidden {
            border: 1px solid #e5e7eb !important;
        }

        /* Input borders already use Tailwind's border-gray-300, keeping them consistent */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        textarea,
        select {
            border-color: #d1d5db !important; /* gray-300 */
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus {
            border-color: #4f46e5 !important; /* indigo-600 */
            ring-color: #4f46e5 !important;
        }

        /* Divider borders */
        hr, .border-t, .border-b, .border-l, .border-r {
            border-color: #e5e7eb !important; /* gray-200 */
        }

        /* Specific overrides for common UI elements */
        .border {
            border-color: #e5e7eb !important;
        }

        .border-gray-200 {
            border-color: #e5e7eb !important;
        }

        .border-gray-300 {
            border-color: #d1d5db !important;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <div class="min-h-screen">
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="shrink-0 flex items-center">
                            <a href="{{ route('products.index') }}" class="font-bold text-xl text-indigo-600">
                                Shopify Middleware
                            </a>
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <a href="{{ route('products.index') }}" class="border-indigo-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Products
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        @auth
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">
                                    Sign Out
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                Sign In
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @if (session('success'))
                    <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4">
                        <div class="flex">
                            <div class="shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700">
                                    {{ session('success') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                        <div class="flex">
                            <div class="shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">
                                    {{ session('error') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="mb-4 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                        <div class="flex">
                            <div class="shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    {{ session('warning') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                @if (session('info'))
                    <div class="mb-4 bg-blue-50 border-l-4 border-blue-400 p-4">
                        <div class="flex">
                            <div class="shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    {{ session('info') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
