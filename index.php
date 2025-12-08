<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Panel - Production Server Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        'primary': {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        'dark': {
                            800: '#1e1e2e',
                            900: '#11111b',
                            950: '#0a0a0f',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="font-inter bg-dark-950 text-white min-h-screen">
    <!-- Background Effects -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-primary-500/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 w-64 h-64 bg-cyan-500/10 rounded-full blur-2xl"></div>
    </div>

    <!-- Main Container -->
    <div class="relative z-10 min-h-screen flex flex-col">
        <!-- Header -->
        <header class="border-b border-white/10 bg-dark-900/50 backdrop-blur-xl">
            <div class="max-w-7xl mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <h1
                                class="text-xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">
                                Server Panel</h1>
                            <p class="text-xs text-gray-500">Production VPS Setup</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span
                            class="px-3 py-1 rounded-full bg-green-500/20 text-green-400 text-xs font-medium border border-green-500/30">
                            <span class="inline-block w-2 h-2 rounded-full bg-green-400 mr-2 animate-pulse"></span>
                            System Online
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Progress Steps -->
        <div class="border-b border-white/10 bg-dark-900/30 backdrop-blur-sm">
            <div class="max-w-7xl mx-auto px-6 py-6">
                <div class="flex items-center justify-between" id="progress-steps">
                    <!-- Steps will be populated by JS -->
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1 py-8">
            <div class="max-w-4xl mx-auto px-6">
                <!-- Step Content Container -->
                <div id="step-content" class="space-y-6">
                    <!-- Content loaded dynamically -->
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-white/10 bg-dark-900/30 backdrop-blur-sm">
            <div class="max-w-7xl mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">Server Panel v1.0 - Production Ready</p>
                    <div class="flex items-center gap-4">
                        <button id="prev-btn"
                            class="px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-gray-400 hover:bg-white/10 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                            ← Previous
                        </button>
                        <button id="next-btn"
                            class="px-6 py-2 rounded-lg bg-gradient-to-r from-primary-500 to-primary-600 text-white font-medium hover:from-primary-400 hover:to-primary-500 transition-all shadow-lg shadow-primary-500/25">
                            Continue →
                        </button>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay"
        class="fixed inset-0 bg-dark-950/90 backdrop-blur-sm z-50 hidden items-center justify-center">
        <div class="text-center">
            <div
                class="w-16 h-16 border-4 border-primary-500/30 border-t-primary-500 rounded-full animate-spin mb-4 mx-auto">
            </div>
            <p class="text-gray-400" id="loading-text">Processing...</p>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script src="assets/js/installer.js"></script>
</body>

</html>