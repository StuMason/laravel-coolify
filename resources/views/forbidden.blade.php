<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Forbidden</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full">
    <div class="flex min-h-full flex-col bg-white pt-16 pb-12 dark:bg-gray-900">
        <main class="mx-auto flex w-full max-w-7xl flex-grow flex-col justify-center px-6 lg:px-8">
            <div class="py-16">
                <div class="text-center">
                    <p class="text-base font-semibold text-green-600">403</p>
                    <h1 class="mt-2 text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl">Access denied</h1>
                    <p class="mt-2 text-base text-gray-500 dark:text-gray-400">You are not authorized to access the Coolify dashboard.</p>
                    <div class="mt-6">
                        <a href="{{ url('/') }}" class="text-base font-medium text-green-600 hover:text-green-500">
                            Go back home
                            <span aria-hidden="true"> &rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
