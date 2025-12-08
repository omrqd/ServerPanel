/**
 * Server Panel - Installer JavaScript
 * Handles step navigation, API calls, and real-time progress
 */

// Configuration
const CONFIG = {
    apiEndpoint: 'api/install.php',
    steps: [
        { id: 'welcome', title: 'Welcome', icon: '👋' },
        { id: 'security', title: 'Security', icon: '🔒' },
        { id: 'php', title: 'PHP', icon: '🐘' },
        { id: 'nodejs', title: 'Node.js', icon: '⬢' },
        { id: 'nginx', title: 'Nginx', icon: '🌐' },
        { id: 'mysql', title: 'MySQL', icon: '🗃️' },
        { id: 'redis', title: 'Redis', icon: '⚡' },
        { id: 'github', title: 'GitHub', icon: '🐙' },
        { id: 'testing', title: 'Testing', icon: '✅' },
        { id: 'complete', title: 'Complete', icon: '🎉' }
    ]
};

// State
let state = {
    currentStep: 0,
    config: {
        domain: '',
        sshPort: 22,
        enableSwap: true,
        swapSize: 2,
        mysqlRootPassword: '',
        enableFail2ban: true,
        enableUfw: true,
        enableGithub: true,
        githubRepo: '',
        githubBranch: 'main'
    },
    completed: {},
    testResults: {},
    githubData: {
        sshPublicKey: '',
        deployUser: 'deploy',
        workflowYaml: ''
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    renderProgressSteps();
    renderCurrentStep();
    setupEventListeners();
});

// Render progress steps in header
function renderProgressSteps() {
    const container = document.getElementById('progress-steps');
    container.innerHTML = CONFIG.steps.map((step, index) => `
        <div class="flex items-center ${index < CONFIG.steps.length - 1 ? 'flex-1' : ''}">
            <div class="flex flex-col items-center">
                <div class="step-circle ${index < state.currentStep ? 'completed' : index === state.currentStep ? 'active' : 'inactive'}">
                    ${index < state.currentStep ? '✓' : step.icon}
                </div>
                <span class="text-xs mt-2 ${index === state.currentStep ? 'text-white' : 'text-gray-500'} hidden md:block">
                    ${step.title}
                </span>
            </div>
            ${index < CONFIG.steps.length - 1 ? `<div class="step-line ${index < state.currentStep ? 'active' : ''} hidden md:block"></div>` : ''}
        </div>
    `).join('');
}

// Render current step content
function renderCurrentStep() {
    const container = document.getElementById('step-content');
    const step = CONFIG.steps[state.currentStep];

    container.innerHTML = getStepContent(step.id);
    container.classList.add('animate-slide-up');

    // Update navigation buttons
    document.getElementById('prev-btn').disabled = state.currentStep === 0;
    document.getElementById('next-btn').textContent =
        state.currentStep === CONFIG.steps.length - 1 ? 'Finish Setup' : 'Continue →';
}

// Get HTML content for each step
function getStepContent(stepId) {
    const templates = {
        welcome: getWelcomeContent(),
        security: getSecurityContent(),
        php: getPHPContent(),
        nodejs: getNodeJSContent(),
        nginx: getNginxContent(),
        mysql: getMySQLContent(),
        redis: getRedisContent(),
        github: getGitHubContent(),
        testing: getTestingContent(),
        complete: getCompleteContent()
    };
    return templates[stepId] || '<p>Unknown step</p>';
}

// Welcome Step
function getWelcomeContent() {
    return `
        <div class="glass-card p-8">
            <div class="text-center mb-8">
                <div class="w-20 h-20 mx-auto mb-6 rounded-2xl bg-gradient-to-br from-primary-400 to-purple-500 flex items-center justify-center">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold gradient-text mb-3">Welcome to Server Panel</h2>
                <p class="text-gray-400 max-w-xl mx-auto">
                    Let's configure your production server. This wizard will install and secure 
                    everything needed for high-traffic PHP + React applications.
                </p>
            </div>
            
            <div class="max-w-md mx-auto space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        Primary Domain Name
                    </label>
                    <input type="text" 
                           id="domain-input" 
                           class="input-field" 
                           placeholder="example.com"
                           value="${state.config.domain}">
                    <p class="text-xs text-gray-500 mt-2">
                        This should be pointed to this server's IP address
                    </p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
                <div class="feature-card text-center">
                    <div class="feature-icon bg-green-500/20 mx-auto">
                        <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold mt-3">Secure</h3>
                    <p class="text-sm text-gray-500 mt-1">UFW, Fail2ban, SSL</p>
                </div>
                <div class="feature-card text-center">
                    <div class="feature-icon bg-blue-500/20 mx-auto">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold mt-3">Fast</h3>
                    <p class="text-sm text-gray-500 mt-1">Optimized for 100K+ traffic</p>
                </div>
                <div class="feature-card text-center">
                    <div class="feature-icon bg-purple-500/20 mx-auto">
                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold mt-3">Complete</h3>
                    <p class="text-sm text-gray-500 mt-1">PHP, Node.js, MySQL, Redis</p>
                </div>
            </div>
        </div>
    `;
}

// Security Step
function getSecurityContent() {
    return `
        <div class="glass-card p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-green-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Production Security Hardening</h2>
                    <p class="text-gray-400">Enterprise-grade security for 100K+ traffic</p>
                </div>
            </div>
            
            <div class="space-y-4">
                <!-- UFW -->
                <div class="flex items-center justify-between p-4 bg-white/5 rounded-xl">
                    <div>
                        <h3 class="font-semibold">🔥 UFW Firewall</h3>
                        <p class="text-sm text-gray-500">Ports 22/80/443 only + SSH rate limiting</p>
                    </div>
                    <div class="toggle-switch ${state.config.enableUfw ? 'active' : ''}" onclick="toggleConfig('enableUfw')"></div>
                </div>
                
                <!-- Fail2ban -->
                <div class="flex items-center justify-between p-4 bg-white/5 rounded-xl">
                    <div>
                        <h3 class="font-semibold">🛡️ Fail2ban (Advanced)</h3>
                        <p class="text-sm text-gray-500">SSH, DDoS, bot, and brute-force protection</p>
                    </div>
                    <div class="toggle-switch ${state.config.enableFail2ban ? 'active' : ''}" onclick="toggleConfig('enableFail2ban')"></div>
                </div>
                
                <!-- SSH Port -->
                <div class="p-4 bg-white/5 rounded-xl">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold">🔑 SSH Port</h3>
                        <input type="number" 
                               id="ssh-port-input" 
                               class="input-field w-24 text-center" 
                               value="${state.config.sshPort}"
                               min="1" 
                               max="65535">
                    </div>
                    <p class="text-xs text-gray-500">SSH hardened: MaxAuthTries=3, no X11, no TCP forwarding</p>
                </div>
                
                <!-- Additional Security (always enabled) -->
                <div class="p-4 bg-green-500/10 border border-green-500/20 rounded-xl">
                    <h3 class="font-semibold mb-2 text-green-300">✅ Also Included (Always On):</h3>
                    <ul class="text-sm text-gray-400 space-y-1 grid grid-cols-2 gap-1">
                        <li>🔒 Kernel security (sysctl)</li>
                        <li>📡 TCP/IP optimizations</li>
                        <li>🔄 Auto security updates</li>
                        <li>📁 65535 file limits</li>
                        <li>🚫 Version info hidden</li>
                        <li>⚡ SYN flood protection</li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-6">
                <button class="btn-primary w-full" onclick="installComponent('security')">
                    <span id="security-btn-text">🔐 Install Production Security</span>
                </button>
                <div id="security-output" class="console-output mt-4 hidden"></div>
            </div>
        </div>
    `;
}

// PHP Step
function getPHPContent() {
    return `
        <div class="glass-card p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-indigo-500/20 flex items-center justify-center text-2xl">
                    🐘
                </div>
                <div>
                    <h2 class="text-2xl font-bold">PHP Configuration</h2>
                    <p class="text-gray-400">Verify and install required extensions</p>
                </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                ${['cli', 'fpm', 'mysql', 'curl', 'gd', 'mbstring', 'xml', 'zip', 'bcmath', 'intl', 'redis', 'opcache'].map(ext => `
                    <div class="p-3 bg-white/5 rounded-lg text-center" id="php-ext-${ext}">
                        <span class="text-sm">${ext}</span>
                        <span class="status-badge pending ml-2">pending</span>
                    </div>
                `).join('')}
            </div>
            
            <div class="p-4 bg-white/5 rounded-xl mb-6">
                <h3 class="font-semibold mb-2">PHP-FPM Configuration</h3>
                <p class="text-sm text-gray-500">
                    Will be optimized for production with proper pool settings, 
                    memory limits, and OPcache configuration.
                </p>
            </div>
            
            <button class="btn-primary w-full" onclick="installComponent('php')">
                <span id="php-btn-text">Verify & Install PHP Extensions</span>
            </button>
            <div id="php-output" class="console-output mt-4 hidden"></div>
        </div>
    `;
}

// Node.js Step
function getNodeJSContent() {
    return `
        <div class="glass-card p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-green-500/20 flex items-center justify-center text-2xl">
                    ⬢
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Node.js Installation</h2>
                    <p class="text-gray-400">Install Node.js 20.x LTS for React builds</p>
                </div>
            </div>
            
            <div class="space-y-4 mb-6">
                <div class="p-4 bg-white/5 rounded-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold">Node.js 20.x LTS</h3>
                            <p class="text-sm text-gray-500">Latest stable version</p>
                        </div>
                        <span class="status-badge pending" id="nodejs-status">pending</span>
                    </div>
                </div>
                
                <div class="p-4 bg-white/5 rounded-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold">npm Package Manager</h3>
                            <p class="text-sm text-gray-500">Included with Node.js</p>
                        </div>
                        <span class="status-badge pending" id="npm-status">pending</span>
                    </div>
                </div>
            </div>
            
            <button class="btn-primary w-full" onclick="installComponent('nodejs')">
                <span id="nodejs-btn-text">Install Node.js</span>
            </button>
            <div id="nodejs-output" class="console-output mt-4 hidden"></div>
        </div>
    `;
}

// Nginx Step
function getNginxContent() {
    return `
        <div class="glass-card p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center text-2xl">
                    🌐
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Nginx Web Server</h2>
                    <p class="text-gray-400">Configure high-performance web server</p>
                </div>
            </div>
            
            <div class="space-y-4 mb-6">
                <div class="p-4 bg-white/5 rounded-xl">
                    <h3 class="font-semibold mb-2">🔧 Server Configuration:</h3>
                    <ul class="text-sm text-gray-400 space-y-1">
                        <li>✓ Gzip compression</li>
                        <li>✓ Security headers (XSS, HSTS, etc.)</li>
                        <li>✓ PHP-FPM integration</li>
                        <li>✓ Static file caching</li>
                        <li>✓ Rate limiting</li>
                        <li>✓ Domain: <span class="text-primary-400">${state.config.domain || 'Not set'}</span></li>
                    </ul>
                </div>
                
                <div class="p-4 bg-white/5 rounded-xl">
                    <h3 class="font-semibold mb-2">📁 Directory Permissions:</h3>
                    <p class="text-sm text-gray-400 mb-2">Creates writable directories for your app:</p>
                    <ul class="text-sm text-gray-500 space-y-1 grid grid-cols-2 gap-1">
                        <li>📂 /storage</li>
                        <li>📂 /storage/logs</li>
                        <li>📂 /storage/app</li>
                        <li>📂 /cache</li>
                        <li>📂 /public/uploads</li>
                        <li>📂 /public/images</li>
                        <li>📂 /bootstrap/cache</li>
                        <li>📂 /tmp</li>
                    </ul>
                    <p class="text-xs text-gray-500 mt-2">
                        Permissions: Directories 775, Files 664 • Owner: www-data
                    </p>
                </div>
            </div>
            
            <button class="btn-primary w-full" onclick="installComponent('nginx')">
                <span id="nginx-btn-text">Install & Configure Nginx</span>
            </button>
            <div id="nginx-output" class="console-output mt-4 hidden"></div>
        </div>
    `;
}

// MySQL Step
function getMySQLContent() {
    return `
        <div class="glass-card p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-orange-500/20 flex items-center justify-center text-2xl">
                    🗃️
                </div>
                <div>
                    <h2 class="text-2xl font-bold">MySQL Database</h2>
                    <p class="text-gray-400">Install and configure MySQL 8.0</p>
                </div>
            </div>
            
            <div class="space-y-6 mb-6">
                <div class="p-4 bg-white/5 rounded-xl">
                    <label class="block text-sm font-medium mb-2">MySQL Root Password</label>
                    <input type="password" 
                           id="mysql-password" 
                           class="input-field" 
                           placeholder="Enter a strong password"
                           value="${state.config.mysqlRootPassword}">
                    <p class="text-xs text-gray-500 mt-2">
                        Min 8 characters, include numbers and special chars
                    </p>
                </div>
                
                <div class="flex items-center justify-between p-4 bg-white/5 rounded-xl">
                    <div>
                        <h3 class="font-semibold">Enable Swap File</h3>
                        <p class="text-sm text-gray-500">Recommended for low-memory VPS (prevents MySQL crashes)</p>
                    </div>
                    <div class="toggle-switch ${state.config.enableSwap ? 'active' : ''}" onclick="toggleConfig('enableSwap')"></div>
                </div>
                
                <div class="p-4 bg-white/5 rounded-xl ${!state.config.enableSwap ? 'opacity-50' : ''}">
                    <label class="block text-sm font-medium mb-2">Swap Size (GB)</label>
                    <input type="number" 
                           id="swap-size" 
                           class="input-field w-32" 
                           value="${state.config.swapSize}"
                           min="1" 
                           max="8"
                           ${!state.config.enableSwap ? 'disabled' : ''}>
                    <p class="text-xs text-gray-500 mt-2">
                        2GB recommended for 1GB RAM VPS
                    </p>
                </div>
            </div>
            
            <div class="p-4 bg-yellow-500/10 border border-yellow-500/20 rounded-xl mb-6">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-yellow-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <p class="text-sm text-yellow-300">
                            Save your MySQL password! It will also be shown at the end of setup.
                        </p>
                    </div>
                </div>
            </div>
            
            <button class="btn-primary w-full" onclick="installComponent('mysql')">
                <span id="mysql-btn-text">Install MySQL</span>
            </button>
            <div id="mysql-output" class="console-output mt-4 hidden"></div>
        </div>
    `;
}

// Redis Step
function getRedisContent() {
    return `
        <div class="glass-card p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-red-500/20 flex items-center justify-center text-2xl">
                    ⚡
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Redis Cache</h2>
                    <p class="text-gray-400">Install Redis for session and data caching</p>
                </div>
            </div>
            
            <div class="p-4 bg-white/5 rounded-xl mb-6">
                <h3 class="font-semibold mb-2">Configuration:</h3>
                <ul class="text-sm text-gray-400 space-y-1">
                    <li>✓ Memory limit: 256MB (configurable)</li>
                    <li>✓ Maxmemory policy: allkeys-lru</li>
                    <li>✓ Bind to localhost only</li>
                    <li>✓ Persistence disabled (cache only)</li>
                </ul>
            </div>
            
            <button class="btn-primary w-full" onclick="installComponent('redis')">
                <span id="redis-btn-text">Install Redis</span>
            </button>
            <div id="redis-output" class="console-output mt-4 hidden"></div>
        </div>
    `;
}

// GitHub Step
function getGitHubContent() {
    return `
        <div class="glass-card p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-gray-500/20 flex items-center justify-center text-2xl">
                    🐙
                </div>
                <div>
                    <h2 class="text-2xl font-bold">GitHub Deployment</h2>
                    <p class="text-gray-400">Setup automated deployments from GitHub</p>
                </div>
            </div>
            
            <div class="space-y-6">
                <!-- Enable/Disable -->
                <div class="flex items-center justify-between p-4 bg-white/5 rounded-xl">
                    <div>
                        <h3 class="font-semibold">Enable GitHub Actions Deployment</h3>
                        <p class="text-sm text-gray-500">Create deploy user and SSH keys for CI/CD</p>
                    </div>
                    <div class="toggle-switch ${state.config.enableGithub ? 'active' : ''}" onclick="toggleConfig('enableGithub'); renderCurrentStep();"></div>
                </div>
                
                ${state.config.enableGithub ? `
                <!-- Repository URL -->
                <div class="p-4 bg-white/5 rounded-xl">
                    <label class="block text-sm font-medium mb-2">GitHub Repository</label>
                    <input type="text" 
                           id="github-repo" 
                           class="input-field" 
                           placeholder="username/repository"
                           value="${state.config.githubRepo}">
                    <p class="text-xs text-gray-500 mt-2">e.g., myuser/my-php-app</p>
                </div>
                
                <!-- Branch -->
                <div class="p-4 bg-white/5 rounded-xl">
                    <label class="block text-sm font-medium mb-2">Deploy Branch</label>
                    <input type="text" 
                           id="github-branch" 
                           class="input-field w-48" 
                           placeholder="main"
                           value="${state.config.githubBranch}">
                </div>
                
                <!-- SSH Key Display -->
                <div class="p-4 bg-white/5 rounded-xl" id="github-ssh-section" style="display: ${state.githubData.sshPublicKey ? 'block' : 'none'}">
                    <h3 class="font-semibold mb-3">🔑 Deploy SSH Key</h3>
                    <p class="text-sm text-gray-400 mb-3">Add this key to your GitHub repository's Deploy Keys:</p>
                    <div class="bg-black/30 rounded-lg p-3 font-mono text-xs break-all" id="ssh-key-display">
                        ${state.githubData.sshPublicKey || 'Key will appear here after setup'}
                    </div>
                    <button class="btn-secondary mt-3" onclick="copyToClipboard(state.githubData.sshPublicKey)">
                        📋 Copy SSH Key
                    </button>
                </div>
                
                <!-- Workflow YAML -->
                <div class="p-4 bg-white/5 rounded-xl" id="github-workflow-section" style="display: ${state.githubData.workflowYaml ? 'block' : 'none'}">
                    <h3 class="font-semibold mb-3">📄 GitHub Actions Workflow</h3>
                    <p class="text-sm text-gray-400 mb-3">Save this as <code>.github/workflows/deploy.yml</code> in your repo:</p>
                    <div class="bg-black/30 rounded-lg p-3 font-mono text-xs max-h-48 overflow-y-auto" id="workflow-yaml-display">
                        <pre>${state.githubData.workflowYaml || 'Workflow will appear here after setup'}</pre>
                    </div>
                    <button class="btn-secondary mt-3" onclick="copyToClipboard(state.githubData.workflowYaml)">
                        📋 Copy Workflow YAML
                    </button>
                </div>
                ` : `
                <div class="p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                    <p class="text-sm text-blue-300">
                        GitHub deployment is disabled. Enable it to set up automated deployments.
                    </p>
                </div>
                `}
            </div>
            
            <button class="btn-primary w-full mt-6" onclick="setupGitHub()" ${!state.config.enableGithub ? 'disabled' : ''}>
                <span id="github-btn-text">🚀 Setup GitHub Deployment</span>
            </button>
            <div id="github-output" class="console-output mt-4 hidden"></div>
        </div>
    `;
}

// Testing Step
function getTestingContent() {
    return `
        <div class="glass-card p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center text-2xl">
                    ✅
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Component Testing</h2>
                    <p class="text-gray-400">Verify all installations are working</p>
                </div>
            </div>
            
            <div class="space-y-4">
                ${[
            { id: 'nginx', name: 'Nginx', desc: 'Web server responding' },
            { id: 'php', name: 'PHP-FPM', desc: 'PHP processing' },
            { id: 'mysql', name: 'MySQL', desc: 'Database connection' },
            { id: 'redis', name: 'Redis', desc: 'Cache connection' },
            { id: 'nodejs', name: 'Node.js', desc: 'Node runtime' }
        ].map(test => `
                    <div class="flex items-center justify-between p-4 bg-white/5 rounded-xl" id="test-row-${test.id}">
                        <div>
                            <h3 class="font-semibold">${test.name}</h3>
                            <p class="text-sm text-gray-500">${test.desc}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="status-badge pending" id="test-${test.id}-status">pending</span>
                            <button class="btn-test" onclick="testComponent('${test.id}')">Test</button>
                            <button class="btn-repair hidden" id="repair-${test.id}" onclick="repairComponent('${test.id}')">🔧 Repair</button>
                        </div>
                    </div>
                `).join('')}
            </div>
            
            <div class="flex gap-4 mt-6">
                <button class="btn-success flex-1" onclick="testAllComponents()">
                    Test All Components
                </button>
                <button class="btn-secondary flex-1" onclick="repairAllFailed()">
                    🔧 Repair All Failed
                </button>
            </div>
            
            <div id="repair-output" class="console-output mt-4 hidden"></div>
        </div>
    `;
}

// Complete Step
function getCompleteContent() {
    return `
        <div class="glass-card p-8">
            <div class="text-center mb-8">
                <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 flex items-center justify-center">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                
                <h2 class="text-3xl font-bold gradient-text mb-3">Almost Done!</h2>
                <p class="text-gray-400 max-w-xl mx-auto">
                    Configure SSL and finalize your server setup.
                </p>
            </div>
            
            <!-- SSL Configuration -->
            <div class="bg-white/5 rounded-xl p-6 text-left mb-6">
                <h3 class="font-semibold mb-4">🔒 SSL Certificate Mode</h3>
                <div class="space-y-3">
                    <label class="flex items-center p-3 bg-white/5 rounded-lg cursor-pointer hover:bg-white/10 transition-all">
                        <input type="radio" name="sslMode" value="cloudflare" class="mr-3" ${state.config.sslMode === 'cloudflare' ? 'checked' : ''} onchange="state.config.sslMode = 'cloudflare'">
                        <div>
                            <span class="font-medium">☁️ Cloudflare SSL</span>
                            <p class="text-sm text-gray-500">Use Cloudflare's SSL proxy (recommended)</p>
                        </div>
                    </label>
                    <label class="flex items-center p-3 bg-white/5 rounded-lg cursor-pointer hover:bg-white/10 transition-all">
                        <input type="radio" name="sslMode" value="letsencrypt" class="mr-3" ${state.config.sslMode === 'letsencrypt' || !state.config.sslMode ? 'checked' : ''} onchange="state.config.sslMode = 'letsencrypt'">
                        <div>
                            <span class="font-medium">🔐 Let's Encrypt</span>
                            <p class="text-sm text-gray-500">Free SSL certificate (requires domain pointing to server)</p>
                        </div>
                    </label>
                    <label class="flex items-center p-3 bg-white/5 rounded-lg cursor-pointer hover:bg-white/10 transition-all">
                        <input type="radio" name="sslMode" value="none" class="mr-3" ${state.config.sslMode === 'none' ? 'checked' : ''} onchange="state.config.sslMode = 'none'">
                        <div>
                            <span class="font-medium">⏭️ Skip SSL</span>
                            <p class="text-sm text-gray-500">Configure SSL manually later</p>
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Credentials -->
            <div class="bg-white/5 rounded-xl p-6 text-left mb-6">
                <h3 class="font-semibold mb-4">📋 Credentials Summary</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-gray-500">Domain</label>
                        <div class="credential-box">
                            <span>${state.config.domain}</span>
                            <button class="copy-btn" onclick="copyToClipboard('${state.config.domain}')">Copy</button>
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">MySQL Root Password</label>
                        <div class="credential-box">
                            <span>••••••••</span>
                            <button class="copy-btn" onclick="copyToClipboard('${state.config.mysqlRootPassword}')">Copy</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-4 bg-red-500/10 border border-red-500/20 rounded-xl mb-6">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div class="text-left">
                        <p class="text-sm text-red-300">
                            <strong>Important:</strong> Save your credentials! The installer will be deleted after completion.
                        </p>
                    </div>
                </div>
            </div>
            
            <button class="btn-primary w-full" onclick="finalizeInstallation()">
                🚀 Complete Setup & Remove Installer
            </button>
        </div>
    `;
}

// Event Listeners
function setupEventListeners() {
    document.getElementById('prev-btn').addEventListener('click', prevStep);
    document.getElementById('next-btn').addEventListener('click', nextStep);
}

// Navigation
function prevStep() {
    if (state.currentStep > 0) {
        state.currentStep--;
        renderProgressSteps();
        renderCurrentStep();
    }
}

function nextStep() {
    if (state.currentStep === 0) {
        const domain = document.getElementById('domain-input')?.value;
        if (!domain) {
            showToast('Please enter your domain name', 'error');
            return;
        }
        state.config.domain = domain;
    }

    if (state.currentStep < CONFIG.steps.length - 1) {
        state.currentStep++;
        renderProgressSteps();
        renderCurrentStep();
    }
}

// Toggle config options
function toggleConfig(key) {
    state.config[key] = !state.config[key];
    renderCurrentStep();
}

// Install component
async function installComponent(component) {
    const btnText = document.getElementById(`${component}-btn-text`);
    const output = document.getElementById(`${component}-output`);

    btnText.innerHTML = '<span class="spinner"></span> Installing...';
    output.classList.remove('hidden');
    output.innerHTML = '<div class="console-line info">Starting installation...</div>';

    try {
        // Get config values
        if (component === 'security') {
            state.config.sshPort = parseInt(document.getElementById('ssh-port-input')?.value) || 22;
        } else if (component === 'mysql') {
            state.config.mysqlRootPassword = document.getElementById('mysql-password')?.value;
            state.config.swapSize = parseInt(document.getElementById('swap-size')?.value) || 2;
        }

        const response = await fetch(CONFIG.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'install',
                component: component,
                config: state.config
            })
        });

        const result = await response.json();

        if (result.success) {
            output.innerHTML += `<div class="console-line success">✓ ${result.message}</div>`;
            btnText.textContent = '✓ Installed';
            state.completed[component] = true;
            showToast(`${component} installed successfully!`, 'success');

            // Update status badges based on component
            updateComponentBadges(component);
        } else {
            output.innerHTML += `<div class="console-line error">✗ ${result.message}</div>`;
            btnText.textContent = 'Retry Installation';
            showToast(`Error: ${result.message}`, 'error');
        }

        if (result.output) {
            result.output.forEach(line => {
                output.innerHTML += `<div class="console-line">${line}</div>`;
            });
        }
    } catch (error) {
        output.innerHTML += `<div class="console-line error">Error: ${error.message}</div>`;
        btnText.textContent = 'Retry Installation';
        showToast('Installation failed', 'error');
    }
}

// Update status badges after installation
function updateComponentBadges(component) {
    if (component === 'php') {
        // Update all PHP extension badges to success
        const extensions = ['cli', 'fpm', 'mysql', 'curl', 'gd', 'mbstring', 'xml', 'zip', 'bcmath', 'intl', 'redis', 'opcache'];
        extensions.forEach(ext => {
            const badge = document.querySelector(`#php-ext-${ext} .status-badge`);
            if (badge) {
                badge.className = 'status-badge success ml-2';
                badge.textContent = 'installed';
            }
        });
    } else if (component === 'nodejs') {
        // Update Node.js and npm badges
        const nodeStatus = document.getElementById('nodejs-status');
        const npmStatus = document.getElementById('npm-status');
        if (nodeStatus) {
            nodeStatus.className = 'status-badge success';
            nodeStatus.textContent = 'installed';
        }
        if (npmStatus) {
            npmStatus.className = 'status-badge success';
            npmStatus.textContent = 'installed';
        }
    }
}

// Test component
async function testComponent(component) {
    const statusEl = document.getElementById(`test-${component}-status`);
    const repairBtn = document.getElementById(`repair-${component}`);

    statusEl.className = 'status-badge running';
    statusEl.textContent = 'testing...';

    try {
        const response = await fetch(CONFIG.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'test',
                component: component,
                config: state.config
            })
        });

        const result = await response.json();

        if (result.success) {
            statusEl.className = 'status-badge success';
            statusEl.textContent = 'passed';
            state.testResults[component] = true;
            if (repairBtn) repairBtn.classList.add('hidden');
        } else {
            statusEl.className = 'status-badge error';
            statusEl.textContent = 'failed';
            state.testResults[component] = false;
            // Show repair button on failure
            if (repairBtn) repairBtn.classList.remove('hidden');
        }
    } catch (error) {
        statusEl.className = 'status-badge error';
        statusEl.textContent = 'error';
        if (repairBtn) repairBtn.classList.remove('hidden');
    }
}

// Test all components
async function testAllComponents() {
    const components = ['nginx', 'php', 'mysql', 'redis', 'nodejs'];
    for (const component of components) {
        await testComponent(component);
        await new Promise(r => setTimeout(r, 500));
    }
}

// Repair a single component
async function repairComponent(component) {
    const statusEl = document.getElementById(`test-${component}-status`);
    const output = document.getElementById('repair-output');

    statusEl.className = 'status-badge running';
    statusEl.textContent = 'repairing...';
    output.classList.remove('hidden');
    output.innerHTML += `<div class="console-line info">Repairing ${component}...</div>`;

    try {
        const response = await fetch(CONFIG.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'repair',
                component: component,
                config: state.config
            })
        });

        const result = await response.json();

        if (result.success) {
            output.innerHTML += `<div class="console-line success">✓ ${result.message}</div>`;
            showToast(`${component} repaired!`, 'success');

            // Show repair output
            if (result.output) {
                result.output.forEach(line => {
                    output.innerHTML += `<div class="console-line">${line}</div>`;
                });
            }

            // Re-test the component
            await new Promise(r => setTimeout(r, 1000));
            await testComponent(component);
        } else {
            output.innerHTML += `<div class="console-line error">✗ ${result.message}</div>`;
            showToast(`Repair failed: ${result.message}`, 'error');
        }
    } catch (error) {
        output.innerHTML += `<div class="console-line error">Error: ${error.message}</div>`;
    }
}

// Repair all failed components
async function repairAllFailed() {
    const components = ['nginx', 'php', 'mysql', 'redis'];
    for (const component of components) {
        if (state.testResults[component] === false) {
            await repairComponent(component);
            await new Promise(r => setTimeout(r, 500));
        }
    }
}

// Setup GitHub deployment
async function setupGitHub() {
    const btnText = document.getElementById('github-btn-text');
    const output = document.getElementById('github-output');

    // Get input values
    state.config.githubRepo = document.getElementById('github-repo')?.value || '';
    state.config.githubBranch = document.getElementById('github-branch')?.value || 'main';

    if (!state.config.githubRepo) {
        showToast('Please enter your GitHub repository', 'error');
        return;
    }

    btnText.innerHTML = '<span class="spinner"></span> Setting up...';
    output.classList.remove('hidden');
    output.innerHTML = '<div class="console-line info">Configuring GitHub deployment...</div>';

    try {
        const response = await fetch(CONFIG.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'install',
                component: 'github',
                config: state.config
            })
        });

        const result = await response.json();

        if (result.success) {
            output.innerHTML += `<div class="console-line success">✓ ${result.message}</div>`;
            btnText.textContent = '✓ Configured';
            state.completed.github = true;
            showToast('GitHub deployment configured!', 'success');

            // Store the generated data
            if (result.sshPublicKey) {
                state.githubData.sshPublicKey = result.sshPublicKey;
            }
            if (result.workflowYaml) {
                state.githubData.workflowYaml = result.workflowYaml;
            }

            // Re-render to show the keys
            renderCurrentStep();

            // Show output
            if (result.output) {
                const outputEl = document.getElementById('github-output');
                outputEl.classList.remove('hidden');
                result.output.forEach(line => {
                    outputEl.innerHTML += `<div class="console-line">${line}</div>`;
                });
            }
        } else {
            output.innerHTML += `<div class="console-line error">✗ ${result.message}</div>`;
            btnText.textContent = 'Retry Setup';
            showToast(`Error: ${result.message}`, 'error');
        }
    } catch (error) {
        output.innerHTML += `<div class="console-line error">Error: ${error.message}</div>`;
        btnText.textContent = 'Retry Setup';
        showToast('GitHub setup failed', 'error');
    }
}

// Finalize installation
async function finalizeInstallation() {
    showLoading('Finalizing setup and removing installer...');

    try {
        const response = await fetch(CONFIG.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'finalize',
                config: state.config
            })
        });

        const result = await response.json();

        hideLoading();

        if (result.success) {
            showToast('Setup complete! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = `https://${state.config.domain}`;
            }, 2000);
        } else {
            showToast(`Error: ${result.message}`, 'error');
        }
    } catch (error) {
        hideLoading();
        showToast('Finalization complete. You may close this tab.', 'info');
    }
}

// Utility functions
function showLoading(text = 'Processing...') {
    const overlay = document.getElementById('loading-overlay');
    const loadingText = document.getElementById('loading-text');
    loadingText.textContent = text;
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    overlay.classList.add('hidden');
    overlay.classList.remove('flex');
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    const icon = type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ';
    toast.innerHTML = `<span class="text-lg">${icon}</span><span>${message}</span>`;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard!', 'success');
    });
}
