<?php
/**
 * Server Panel - Installer API
 * Handles all installation and testing operations
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Include helpers
require_once __DIR__ . '/helpers.php';

// Parse request
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$component = $input['component'] ?? '';
$config = $input['config'] ?? [];

// Route request
try {
    switch ($action) {
        case 'install':
            $result = handleInstall($component, $config);
            break;
        case 'test':
            $result = handleTest($component, $config);
            break;
        case 'repair':
            $result = handleRepair($component, $config);
            break;
        case 'generate-ssh-key':
            $result = handleGenerateSSHKey($config);
            break;
        case 'get-system-info':
            $result = handleGetSystemInfo();
            break;
        case 'finalize':
            $result = handleFinalize($config);
            break;
        default:
            $result = ['success' => false, 'message' => 'Unknown action'];
    }
} catch (Exception $e) {
    $result = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($result);

/**
 * Get system information for auto-optimization
 */
function handleGetSystemInfo()
{
    // Get RAM (in MB)
    $totalRam = (int) trim(shell_exec("free -m | grep Mem | awk '{print $2}'"));
    $usedRam = (int) trim(shell_exec("free -m | grep Mem | awk '{print $3}'"));
    $freeRam = $totalRam - $usedRam;

    // Get CPU
    $cpuCores = (int) trim(shell_exec("nproc"));
    $cpuModel = trim(shell_exec("cat /proc/cpuinfo | grep 'model name' | head -1 | cut -d':' -f2"));

    // Get Disk
    $diskTotal = trim(shell_exec("df -BG / | tail -1 | awk '{print $2}' | tr -d 'G'"));
    $diskUsed = trim(shell_exec("df -BG / | tail -1 | awk '{print $3}' | tr -d 'G'"));
    $diskFree = trim(shell_exec("df -BG / | tail -1 | awk '{print $4}' | tr -d 'G'"));

    // Get OS
    $osName = trim(shell_exec("cat /etc/os-release | grep '^NAME=' | cut -d'\"' -f2"));
    $osVersion = trim(shell_exec("cat /etc/os-release | grep '^VERSION_ID=' | cut -d'\"' -f2"));

    // Get Public IP
    $publicIp = trim(shell_exec("curl -s -4 ifconfig.me 2>/dev/null"));

    // Calculate recommended settings based on specs
    $recommendations = calculateOptimalSettings($totalRam, $cpuCores);

    return [
        'success' => true,
        'system' => [
            'ram' => [
                'total' => $totalRam,
                'used' => $usedRam,
                'free' => $freeRam,
                'unit' => 'MB'
            ],
            'cpu' => [
                'cores' => $cpuCores,
                'model' => $cpuModel
            ],
            'disk' => [
                'total' => (int) $diskTotal,
                'used' => (int) $diskUsed,
                'free' => (int) $diskFree,
                'unit' => 'GB'
            ],
            'os' => [
                'name' => $osName,
                'version' => $osVersion
            ],
            'ip' => $publicIp
        ],
        'recommendations' => $recommendations
    ];
}

/**
 * Calculate optimal settings based on system specs
 */
function calculateOptimalSettings($ramMB, $cpuCores)
{
    // PHP-FPM: ~30MB per process
    $phpMaxChildren = max(5, min(500, (int) ($ramMB / 30)));
    $phpStartServers = max(2, (int) ($phpMaxChildren / 4));
    $phpMinSpare = $phpStartServers;
    $phpMaxSpare = max(5, (int) ($phpMaxChildren / 2));

    // MySQL InnoDB buffer pool: 50-70% of RAM for dedicated DB server
    // For shared server, use 25% of RAM
    $mysqlBufferPool = max(128, (int) ($ramMB * 0.25));
    $mysqlMaxConnections = max(50, min(500, $cpuCores * 50));

    // Redis: 10-20% of RAM
    $redisMaxMemory = max(64, min(4096, (int) ($ramMB * 0.15)));

    // OPcache: Based on available RAM
    $opcacheMemory = $ramMB >= 4096 ? 512 : ($ramMB >= 2048 ? 256 : 128);

    // Nginx workers: Usually equal to CPU cores
    $nginxWorkers = $cpuCores;
    $nginxWorkerConnections = $ramMB >= 2048 ? 4096 : 1024;

    // Swap recommendation
    $swapRecommended = $ramMB < 2048 ? max(1, min(4, (int) (4096 / $ramMB))) : 0;

    return [
        'php_fpm' => [
            'max_children' => $phpMaxChildren,
            'start_servers' => $phpStartServers,
            'min_spare_servers' => $phpMinSpare,
            'max_spare_servers' => $phpMaxSpare
        ],
        'mysql' => [
            'innodb_buffer_pool_size' => $mysqlBufferPool . 'M',
            'max_connections' => $mysqlMaxConnections
        ],
        'redis' => [
            'maxmemory' => $redisMaxMemory . 'mb'
        ],
        'opcache' => [
            'memory_consumption' => $opcacheMemory
        ],
        'nginx' => [
            'worker_processes' => $nginxWorkers,
            'worker_connections' => $nginxWorkerConnections
        ],
        'swap' => [
            'recommended_gb' => $swapRecommended
        ],
        'tier' => getTierName($ramMB)
    ];
}

/**
 * Get tier name based on RAM
 */
function getTierName($ramMB)
{
    if ($ramMB >= 16384)
        return 'Enterprise (16GB+)';
    if ($ramMB >= 8192)
        return 'Large (8GB)';
    if ($ramMB >= 4096)
        return 'Medium (4GB)';
    if ($ramMB >= 2048)
        return 'Small (2GB)';
    if ($ramMB >= 1024)
        return 'Micro (1GB)';
    return 'Nano (' . $ramMB . 'MB)';
}

/**
 * Handle component installation
 */
function handleInstall($component, $config)
{
    switch ($component) {
        case 'security':
            return installSecurity($config);
        case 'php':
            return installPHP($config);
        case 'nodejs':
            return installNodeJS($config);
        case 'nginx':
            return installNginx($config);
        case 'mysql':
            return installMySQL($config);
        case 'redis':
            return installRedis($config);
        case 'github':
            return installGitHub($config);
        default:
            return ['success' => false, 'message' => 'Unknown component'];
    }
}

/**
 * Generate SSH key pair for secure access
 */
function handleGenerateSSHKey($config)
{
    $output = [];
    $keyName = $config['keyName'] ?? 'server-panel-key';
    $keyComment = $config['keyComment'] ?? 'Generated by Server Panel';

    // Generate key in temporary location
    $tempDir = '/tmp/ssh-keygen-' . uniqid();
    $keyPath = "{$tempDir}/{$keyName}";

    run_command("mkdir -p {$tempDir}");
    run_command("chmod 700 {$tempDir}");

    // Generate ed25519 key (most secure and recommended)
    $output[] = 'Generating ed25519 SSH key pair...';
    run_command("ssh-keygen -t ed25519 -f {$keyPath} -N '' -C '{$keyComment}'");

    // Read the keys
    $privateKey = file_get_contents($keyPath);
    $publicKey = trim(file_get_contents("{$keyPath}.pub"));

    if (empty($privateKey) || empty($publicKey)) {
        run_command("rm -rf {$tempDir}");
        return ['success' => false, 'message' => 'Failed to generate SSH keys'];
    }

    // Add public key to root's authorized_keys
    $output[] = 'Adding public key to authorized_keys...';
    $authKeysFile = '/root/.ssh/authorized_keys';
    run_command('mkdir -p /root/.ssh');
    run_command('chmod 700 /root/.ssh');

    // Check if key already exists
    $existingKeys = file_exists($authKeysFile) ? file_get_contents($authKeysFile) : '';
    if (strpos($existingKeys, $publicKey) === false) {
        file_put_contents($authKeysFile, $existingKeys . "\n" . $publicKey . "\n");
        run_command('chmod 600 /root/.ssh/authorized_keys');
    }

    $output[] = 'SSH key added to server';
    $output[] = '';
    $output[] = '⚠️ IMPORTANT: Save the private key below!';
    $output[] = 'You will need it to connect to your server.';
    $output[] = 'This key will NOT be shown again.';

    // Clean up temp files
    run_command("rm -rf {$tempDir}");

    return [
        'success' => true,
        'message' => 'SSH key generated successfully',
        'output' => $output,
        'privateKey' => $privateKey,
        'publicKey' => $publicKey,
        'keyName' => $keyName
    ];
}

/**
 * Install security components (UFW, Fail2ban, Kernel Hardening)
 */
function installSecurity($config)
{
    $output = [];
    $sshPort = $config['sshPort'] ?? 22;

    // === UFW FIREWALL ===
    if ($config['enableUfw'] ?? true) {
        $output[] = 'Installing UFW firewall...';
        run_command('apt-get install -y ufw');

        // Configure UFW with strict defaults
        run_command('ufw default deny incoming');
        run_command('ufw default allow outgoing');
        run_command('ufw default deny forward');

        // Essential ports only
        run_command("ufw allow {$sshPort}/tcp comment 'SSH'");
        run_command("ufw allow 80/tcp comment 'HTTP'");
        run_command("ufw allow 443/tcp comment 'HTTPS'");
        run_command("ufw allow 8080/tcp comment 'Installer-temp'"); // Removed after setup

        // Enable rate limiting on SSH
        run_command("ufw limit {$sshPort}/tcp comment 'SSH-rate-limit'");

        run_command('echo "y" | ufw enable');
        $output[] = 'UFW firewall configured with rate limiting';
    }

    // === FAIL2BAN ===
    if ($config['enableFail2ban'] ?? true) {
        $output[] = 'Installing Fail2ban...';
        run_command('apt-get install -y fail2ban');

        // Create comprehensive jail.local
        $jailConfig = "[DEFAULT]
bantime = 86400
findtime = 600
maxretry = 5
backend = systemd
banaction = ufw

[sshd]
enabled = true
port = {$sshPort}
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 86400

[sshd-ddos]
enabled = true
port = {$sshPort}
filter = sshd-ddos
logpath = /var/log/auth.log
maxretry = 6
bantime = 172800

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 5

[nginx-botsearch]
enabled = true
filter = nginx-botsearch
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 2
bantime = 86400

[nginx-badbots]
enabled = true
filter = apache-badbots
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 2
bantime = 86400

[nginx-req-limit]
enabled = true
filter = nginx-limit-req
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 10
";
        file_put_contents('/etc/fail2ban/jail.local', $jailConfig);

        run_command('systemctl enable fail2ban');
        run_command('systemctl restart fail2ban');
        $output[] = 'Fail2ban configured with advanced rules';
    }

    // === SSH HARDENING ===
    $output[] = 'Hardening SSH configuration...';

    // Get user's auth method preferences
    $allowPassword = ($config['sshAllowPassword'] ?? true) ? 'yes' : 'no';
    $allowKey = ($config['sshAllowKey'] ?? true) ? 'yes' : 'no';
    $disableRootPassword = $config['sshDisableRootPassword'] ?? false;

    // Determine PermitRootLogin based on settings
    $permitRootLogin = 'yes'; // Default: allow both password and key
    if ($disableRootPassword) {
        $permitRootLogin = 'prohibit-password'; // Only key-based root login
    } elseif ($allowPassword === 'no' && $allowKey === 'yes') {
        $permitRootLogin = 'prohibit-password';
    } elseif ($allowPassword === 'yes') {
        $permitRootLogin = 'yes';
    }

    // Safety check: ensure at least one auth method is enabled
    if ($allowPassword === 'no' && $allowKey === 'no') {
        $allowPassword = 'yes'; // Fallback to password if user disabled both
        $output[] = '⚠️ Warning: Re-enabled password auth (cannot disable both methods)';
    }

    $sshConfig = "
# Server Panel - Production SSH Configuration
# Auth Methods: Password={$allowPassword}, Key={$allowKey}

Port {$sshPort}
PermitRootLogin {$permitRootLogin}
PasswordAuthentication {$allowPassword}
PubkeyAuthentication {$allowKey}
PermitEmptyPasswords no
ChallengeResponseAuthentication no
UsePAM yes
X11Forwarding no
PrintMotd no
AcceptEnv LANG LC_*
Subsystem sftp /usr/lib/openssh/sftp-server
MaxAuthTries 5
LoginGraceTime 60
ClientAliveInterval 300
ClientAliveCountMax 3
AllowAgentForwarding no
AllowTcpForwarding no
MaxStartups 10:30:60
";
    file_put_contents('/etc/ssh/sshd_config.d/99-server-panel.conf', $sshConfig);
    run_command('systemctl restart ssh 2>/dev/null || systemctl restart sshd');
    $output[] = "SSH configured: Password={$allowPassword}, Key={$allowKey}, RootLogin={$permitRootLogin}";

    // === KERNEL SECURITY (sysctl) ===
    $output[] = 'Applying kernel security settings...';
    $sysctlConfig = "# Server Panel - Kernel Security Hardening

# IP Spoofing protection
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1

# Ignore ICMP broadcast requests
net.ipv4.icmp_echo_ignore_broadcasts = 1

# Disable source packet routing
net.ipv4.conf.all.accept_source_route = 0
net.ipv6.conf.all.accept_source_route = 0

# Ignore send redirects
net.ipv4.conf.all.send_redirects = 0
net.ipv4.conf.default.send_redirects = 0

# Block SYN attacks
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_max_syn_backlog = 2048
net.ipv4.tcp_synack_retries = 2

# Log Martians (impossible addresses)
net.ipv4.conf.all.log_martians = 1

# Ignore ICMP redirects
net.ipv4.conf.all.accept_redirects = 0
net.ipv6.conf.all.accept_redirects = 0

# Ignore Directed pings
net.ipv4.icmp_ignore_bogus_error_responses = 1

# === High Traffic Optimizations ===
# Increase max connections
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 65535

# Increase TCP buffer sizes
net.ipv4.tcp_rmem = 4096 87380 16777216
net.ipv4.tcp_wmem = 4096 65536 16777216
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216

# TCP keepalive
net.ipv4.tcp_keepalive_time = 600
net.ipv4.tcp_keepalive_intvl = 60
net.ipv4.tcp_keepalive_probes = 3

# Reduce TIME_WAIT
net.ipv4.tcp_fin_timeout = 15
net.ipv4.tcp_tw_reuse = 1

# Increase file descriptors
fs.file-max = 2097152
fs.nr_open = 2097152

# Memory optimizations
vm.swappiness = 10
vm.dirty_ratio = 60
vm.dirty_background_ratio = 5
";
    file_put_contents('/etc/sysctl.d/99-server-panel-security.conf', $sysctlConfig);
    run_command('sysctl -p /etc/sysctl.d/99-server-panel-security.conf 2>/dev/null');
    $output[] = 'Kernel security and TCP optimizations applied';

    // === AUTOMATIC SECURITY UPDATES ===
    $output[] = 'Configuring automatic security updates...';
    run_command('apt-get install -y unattended-upgrades apt-listchanges');

    $autoUpgradesConfig = 'APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
';
    file_put_contents('/etc/apt/apt.conf.d/20auto-upgrades', $autoUpgradesConfig);
    run_command('systemctl enable unattended-upgrades');
    $output[] = 'Automatic security updates enabled';

    // === HIDE SERVER VERSION ===
    $output[] = 'Hiding server version information...';
    // This will be applied when Nginx is configured

    // Disable unused services
    run_command('systemctl disable cups 2>/dev/null || true');
    run_command('systemctl disable avahi-daemon 2>/dev/null || true');
    $output[] = 'Disabled unnecessary services';

    // Set secure file limits
    $limitsConfig = "
# Server Panel - File limits for high traffic
* soft nofile 65535
* hard nofile 65535
root soft nofile 65535
root hard nofile 65535
www-data soft nofile 65535
www-data hard nofile 65535
";
    file_put_contents('/etc/security/limits.d/99-server-panel.conf', $limitsConfig);
    $output[] = 'File descriptor limits increased for high traffic';

    return [
        'success' => true,
        'message' => 'Security hardening complete (production-grade)',
        'output' => $output
    ];
}

/**
 * Install/verify PHP extensions
 */
function installPHP($config)
{
    $output = [];
    $required = ['cli', 'fpm', 'mysql', 'curl', 'gd', 'mbstring', 'xml', 'zip', 'bcmath', 'intl', 'redis', 'opcache'];

    $output[] = 'Checking PHP installation...';

    // Check PHP version
    $phpVersion = trim(run_command('php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2'));
    $output[] = "PHP version: {$phpVersion}";

    // Install missing extensions
    $toInstall = [];
    foreach ($required as $ext) {
        $check = run_command("php -m | grep -i {$ext}");
        if (empty(trim($check))) {
            $toInstall[] = "php{$phpVersion}-{$ext}";
        }
    }

    if (!empty($toInstall)) {
        $packages = implode(' ', $toInstall);
        $output[] = "Installing: {$packages}";
        run_command("apt-get install -y {$packages}");
    }

    // Optimize PHP-FPM
    // === PHP-FPM FOR HIGH TRAFFIC ===
    $output[] = 'Optimizing PHP-FPM for high traffic...';

    // Calculate optimal settings based on available memory
    $totalRam = (int) trim(run_command("free -m | grep Mem | awk '{print $2}'"));
    $maxChildren = max(25, min(200, (int) ($totalRam / 30))); // ~30MB per process
    $startServers = max(5, (int) ($maxChildren / 4));
    $minSpare = $startServers;
    $maxSpare = max(10, (int) ($maxChildren / 2));

    $fpmConfig = "; Server Panel - High Traffic PHP-FPM Pool
[www]
user = www-data
group = www-data
listen = /run/php/php{$phpVersion}-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
listen.backlog = 65535

; Process Management - Optimized for 100K+ traffic
pm = dynamic
pm.max_children = {$maxChildren}
pm.start_servers = {$startServers}
pm.min_spare_servers = {$minSpare}
pm.max_spare_servers = {$maxSpare}
pm.max_requests = 1000
pm.process_idle_timeout = 10s

; Request handling
request_terminate_timeout = 300
request_slowlog_timeout = 10s
slowlog = /var/log/php-fpm-slow.log
catch_workers_output = yes
decorate_workers_output = no

; Security
php_admin_flag[expose_php] = off
php_admin_value[open_basedir] = /var/www/:/tmp/:/usr/share/php/
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,curl_multi_exec,parse_ini_file,show_source
php_admin_value[session.cookie_httponly] = 1
php_admin_value[session.cookie_secure] = 1
php_admin_value[session.use_strict_mode] = 1

; Resource limits
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
php_admin_value[post_max_size] = 100M
php_admin_value[upload_max_filesize] = 100M
php_admin_value[max_input_vars] = 5000

; Error handling
php_admin_flag[display_errors] = off
php_admin_flag[log_errors] = on
php_admin_value[error_log] = /var/log/php-fpm-error.log
";
    file_put_contents("/etc/php/{$phpVersion}/fpm/pool.d/www.conf", $fpmConfig);
    $output[] = "PHP-FPM optimized (max_children={$maxChildren} based on {$totalRam}MB RAM)";

    // === OPCACHE FOR PRODUCTION ===
    $opcacheConfig = "; Server Panel - Production OPcache Configuration
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=50000
opcache.max_wasted_percentage=10
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
opcache.file_cache=/tmp/opcache
opcache.file_cache_only=0
opcache.file_cache_consistency_checks=1

; JIT (PHP 8.0+)
opcache.jit=1255
opcache.jit_buffer_size=128M
";
    file_put_contents("/etc/php/{$phpVersion}/fpm/conf.d/99-opcache-production.ini", $opcacheConfig);
    run_command("mkdir -p /tmp/opcache && chmod 755 /tmp/opcache");
    $output[] = 'OPcache optimized (256MB cache, JIT enabled)';

    // === ADDITIONAL PHP SECURITY ===
    $phpSecConfig = "; Server Panel - PHP Security
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php-errors.log
allow_url_fopen = Off
allow_url_include = Off
";
    file_put_contents("/etc/php/{$phpVersion}/fpm/conf.d/99-security.ini", $phpSecConfig);

    run_command("systemctl restart php{$phpVersion}-fpm");
    $output[] = 'PHP-FPM restarted with production settings';

    return [
        'success' => true,
        'message' => 'PHP configuration complete',
        'output' => $output
    ];
}

/**
 * Install Node.js
 */
function installNodeJS($config)
{
    $output = [];

    $output[] = 'Setting up Node.js repository...';

    // Install NodeSource repository for Node.js 20.x
    run_command('curl -fsSL https://deb.nodesource.com/setup_20.x | bash -');

    $output[] = 'Installing Node.js 20.x LTS...';
    run_command('apt-get install -y nodejs');

    // Verify installation
    $nodeVersion = trim(run_command('node --version'));
    $npmVersion = trim(run_command('npm --version'));

    $output[] = "Node.js version: {$nodeVersion}";
    $output[] = "npm version: {$npmVersion}";

    // Install common global packages
    $output[] = 'Installing global npm packages...';
    run_command('npm install -g pm2');

    return [
        'success' => true,
        'message' => "Node.js {$nodeVersion} installed successfully",
        'output' => $output
    ];
}

/**
 * Install and configure Nginx
 */
function installNginx($config)
{
    $output = [];
    $domain = $config['domain'] ?? 'localhost';

    $output[] = 'Installing Nginx...';
    run_command('apt-get install -y nginx');

    // Create web root
    $webRoot = "/var/www/{$domain}";
    run_command("mkdir -p {$webRoot}/public");

    // Get PHP version
    $phpVersion = trim(run_command('php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2'));

    // Add rate limiting to main nginx.conf (must be in http block)
    $output[] = 'Configuring rate limiting...';
    $rateLimitConfig = "
# Rate limiting zones - added by Server Panel
limit_req_zone \$binary_remote_addr zone=api:10m rate=10r/s;
limit_req_zone \$binary_remote_addr zone=general:10m rate=20r/s;
";
    // Add to conf.d so it's included in http block
    file_put_contents('/etc/nginx/conf.d/rate-limit.conf', $rateLimitConfig);

    // Create Nginx site config (without limit_req_zone - that's in conf.d now)
    $output[] = 'Creating Nginx configuration...';
    $nginxConfig = "# {$domain} - Production Configuration
server {
    listen 80;
    listen [::]:80;
    server_name {$domain} www.{$domain};
    root {$webRoot}/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options \"SAMEORIGIN\" always;
    add_header X-Content-Type-Options \"nosniff\" always;
    add_header X-XSS-Protection \"1; mode=block\" always;
    add_header Referrer-Policy \"strict-origin-when-cross-origin\" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/xml+rss application/atom+xml image/svg+xml;

    # Main location
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP handling
    location ~ \\.php\$ {
        fastcgi_pass unix:/run/php/php{$phpVersion}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Static files caching
    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)\$ {
        expires 1y;
        add_header Cache-Control \"public, immutable\";
    }

    # Deny hidden files
    location ~ /\\. {
        deny all;
    }

    # API rate limiting (uses zone defined in conf.d/rate-limit.conf)
    location /api {
        limit_req zone=api burst=20 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    access_log /var/log/nginx/{$domain}.access.log;
    error_log /var/log/nginx/{$domain}.error.log;
}
";
    file_put_contents("/etc/nginx/sites-available/{$domain}", $nginxConfig);

    // Enable site
    run_command("ln -sf /etc/nginx/sites-available/{$domain} /etc/nginx/sites-enabled/");
    run_command('rm -f /etc/nginx/sites-enabled/default');

    // Create default page
    $defaultPage = "<!DOCTYPE html>
<html>
<head>
    <title>Welcome to {$domain}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .container { text-align: center; }
        h1 { font-size: 3rem; background: linear-gradient(135deg, #38bdf8, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        p { color: #94a3b8; }
    </style>
</head>
<body>
    <div class=\"container\">
        <h1>🚀 {$domain}</h1>
        <p>Your server is ready for deployment!</p>
    </div>
</body>
</html>";
    file_put_contents("{$webRoot}/public/index.html", $defaultPage);

    // PHP info for testing
    file_put_contents("{$webRoot}/public/info.php", "<?php phpinfo(); ?>");

    // === DIRECTORY PERMISSIONS SETUP ===
    $output[] = 'Setting up directory permissions...';

    // Create essential writable directories for web applications
    $writableDirs = [
        "{$webRoot}/storage",
        "{$webRoot}/storage/app",
        "{$webRoot}/storage/app/public",
        "{$webRoot}/storage/framework",
        "{$webRoot}/storage/framework/cache",
        "{$webRoot}/storage/framework/sessions",
        "{$webRoot}/storage/framework/views",
        "{$webRoot}/storage/logs",
        "{$webRoot}/bootstrap/cache",
        "{$webRoot}/public/uploads",
        "{$webRoot}/public/images",
        "{$webRoot}/cache",
        "{$webRoot}/tmp"
    ];

    foreach ($writableDirs as $dir) {
        run_command("mkdir -p {$dir}");
    }

    // Set base ownership - www-data as owner for web server access
    run_command("chown -R www-data:www-data {$webRoot}");

    // Set directory permissions (775 = rwxrwxr-x)
    run_command("find {$webRoot} -type d -exec chmod 775 {} \\;");

    // Set file permissions (664 = rw-rw-r--)
    run_command("find {$webRoot} -type f -exec chmod 664 {} \\;");

    // Make storage and cache fully writable
    run_command("chmod -R 775 {$webRoot}/storage");
    run_command("chmod -R 775 {$webRoot}/public/uploads");
    run_command("chmod -R 775 {$webRoot}/public/images");
    run_command("chmod -R 775 {$webRoot}/cache");
    run_command("chmod -R 775 {$webRoot}/tmp");

    // Ensure bootstrap/cache is writable if it exists
    if (is_dir("{$webRoot}/bootstrap/cache")) {
        run_command("chmod -R 775 {$webRoot}/bootstrap/cache");
    }

    // Add ACL for deploy user to also have write access (if ACL is available)
    $aclInstalled = run_command('which setfacl 2>/dev/null');
    if (!empty(trim($aclInstalled))) {
        run_command("setfacl -R -m u:www-data:rwx {$webRoot}/storage 2>/dev/null || true");
        run_command("setfacl -R -m u:www-data:rwx {$webRoot}/public/uploads 2>/dev/null || true");
        run_command("setfacl -R -d -m u:www-data:rwx {$webRoot}/storage 2>/dev/null || true");
        run_command("setfacl -R -d -m u:www-data:rwx {$webRoot}/public/uploads 2>/dev/null || true");
    }

    $output[] = 'Directory structure created with proper permissions';

    // Test and reload Nginx
    $output[] = 'Testing Nginx configuration...';
    $testResult = run_command('nginx -t 2>&1');
    if (strpos($testResult, 'successful') !== false) {
        run_command('systemctl reload nginx');
        $output[] = 'Nginx configured and reloaded';
    } else {
        return ['success' => false, 'message' => 'Nginx configuration error', 'output' => [$testResult]];
    }

    run_command('systemctl enable nginx');

    return [
        'success' => true,
        'message' => 'Nginx configured with proper permissions',
        'output' => $output
    ];
}

/**
 * Install MySQL with swap management
 */
function installMySQL($config)
{
    $output = [];
    $password = $config['mysqlRootPassword'] ?? 'changeme123!';

    // Create swap first if enabled
    if ($config['enableSwap'] ?? true) {
        $swapSize = $config['swapSize'] ?? 2;
        $output[] = "Creating {$swapSize}GB swap file...";

        // Check if swap already exists
        $swapExists = trim(run_command('swapon --show'));
        if (empty($swapExists)) {
            run_command("fallocate -l {$swapSize}G /swapfile");
            run_command('chmod 600 /swapfile');
            run_command('mkswap /swapfile');
            run_command('swapon /swapfile');

            // Make permanent
            $fstab = file_get_contents('/etc/fstab');
            if (strpos($fstab, '/swapfile') === false) {
                file_put_contents('/etc/fstab', $fstab . "\n/swapfile none swap sw 0 0\n");
            }

            // Optimize swappiness
            run_command('sysctl vm.swappiness=10');
            file_put_contents('/etc/sysctl.d/99-swappiness.conf', "vm.swappiness=10\n");

            $output[] = 'Swap file created and activated';
        } else {
            $output[] = 'Swap already exists, skipping';
        }
    }

    // Install MySQL
    $output[] = 'Installing MySQL 8.0...';

    // Pre-configure MySQL password
    run_command("debconf-set-selections <<< 'mysql-server mysql-server/root_password password {$password}'");
    run_command("debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password {$password}'");

    run_command('apt-get install -y mysql-server');

    // Secure MySQL
    $output[] = 'Securing MySQL installation...';

    $secureCommands = "
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '{$password}';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
";
    file_put_contents('/tmp/mysql_secure.sql', $secureCommands);
    run_command("mysql -u root -p'{$password}' < /tmp/mysql_secure.sql 2>/dev/null || mysql -u root < /tmp/mysql_secure.sql");
    unlink('/tmp/mysql_secure.sql');

    // Optimize MySQL for low memory
    $mysqlConfig = "[mysqld]
# Performance for low memory VPS
innodb_buffer_pool_size = 128M
innodb_log_file_size = 32M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Connection settings
max_connections = 100
wait_timeout = 600
interactive_timeout = 600

# Query cache (deprecated in 8.0 but good for older)
# query_cache_type = 1
# query_cache_size = 32M

# Temp tables
tmp_table_size = 32M
max_heap_table_size = 32M

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
";
    file_put_contents('/etc/mysql/mysql.conf.d/99-optimizations.cnf', $mysqlConfig);

    run_command('systemctl restart mysql');
    run_command('systemctl enable mysql');

    $output[] = 'MySQL installed and optimized';

    return [
        'success' => true,
        'message' => 'MySQL installed successfully',
        'output' => $output
    ];
}

/**
 * Install Redis
 */
function installRedis($config)
{
    $output = [];

    $output[] = 'Installing Redis...';
    run_command('apt-get install -y redis-server');

    // Configure Redis
    $output[] = 'Configuring Redis for caching...';
    $redisConfig = "
# Redis optimization for caching
maxmemory 256mb
maxmemory-policy allkeys-lru
bind 127.0.0.1
protected-mode yes
appendonly no
";
    file_put_contents('/etc/redis/redis.conf.d/99-cache.conf', $redisConfig);

    // Ensure config directory exists
    run_command('mkdir -p /etc/redis/redis.conf.d');

    // Add include to main config if not present
    $mainConfig = file_get_contents('/etc/redis/redis.conf');
    if (strpos($mainConfig, 'maxmemory 256mb') === false) {
        // Modify inline
        $mainConfig = preg_replace('/^# maxmemory .*/m', 'maxmemory 256mb', $mainConfig);
        $mainConfig = preg_replace('/^# maxmemory-policy .*/m', 'maxmemory-policy allkeys-lru', $mainConfig);
        file_put_contents('/etc/redis/redis.conf', $mainConfig);
    }

    run_command('systemctl restart redis-server');
    run_command('systemctl enable redis-server');

    // Verify
    $ping = trim(run_command('redis-cli ping'));
    if ($ping === 'PONG') {
        $output[] = 'Redis is responding correctly';
    }

    return [
        'success' => true,
        'message' => 'Redis installed successfully',
        'output' => $output
    ];
}

/**
 * Install GitHub deployment configuration
 */
function installGitHub($config)
{
    $output = [];
    $domain = $config['domain'] ?? 'localhost';
    $repo = $config['githubRepo'] ?? '';
    $branch = $config['githubBranch'] ?? 'main';
    $deployUser = 'deploy';
    $webRoot = "/var/www/{$domain}";

    $output[] = 'Setting up GitHub deployment...';

    // Create deploy user if it doesn't exist
    $userExists = trim(run_command("id -u {$deployUser} 2>/dev/null"));
    if (empty($userExists)) {
        $output[] = "Creating deploy user '{$deployUser}'...";
        run_command("useradd -m -s /bin/bash {$deployUser}");
        run_command("usermod -aG www-data {$deployUser}");
    }

    // Create .ssh directory
    $sshDir = "/home/{$deployUser}/.ssh";
    run_command("mkdir -p {$sshDir}");
    run_command("chmod 700 {$sshDir}");

    // Generate SSH key pair for GitHub Actions
    $keyPath = "{$sshDir}/github_deploy";
    if (!file_exists($keyPath)) {
        $output[] = 'Generating SSH key pair...';
        run_command("ssh-keygen -t ed25519 -f {$keyPath} -N '' -C 'github-actions-deploy'");
    }

    // Read the public key
    $publicKey = trim(file_get_contents("{$keyPath}.pub"));
    $output[] = 'SSH key generated successfully';

    // Set proper ownership
    run_command("chown -R {$deployUser}:{$deployUser} {$sshDir}");
    run_command("chmod 600 {$keyPath}");
    run_command("chmod 644 {$keyPath}.pub");

    // Add authorized key for deployment
    $authorizedKeys = "{$sshDir}/authorized_keys";
    if (!file_exists($authorizedKeys) || strpos(file_get_contents($authorizedKeys), $publicKey) === false) {
        file_put_contents($authorizedKeys, $publicKey . "\n", FILE_APPEND);
        run_command("chmod 600 {$authorizedKeys}");
        run_command("chown {$deployUser}:{$deployUser} {$authorizedKeys}");
    }

    // Give deploy user access to web root
    run_command("chown -R {$deployUser}:www-data {$webRoot}");
    run_command("chmod -R 775 {$webRoot}");

    // Get server IP
    $serverIp = trim(run_command('curl -s -4 ifconfig.me 2>/dev/null'));
    $sshPort = $config['sshPort'] ?? 22;

    // Generate GitHub Actions workflow YAML
    $workflowYaml = generateGitHubWorkflow($domain, $branch, $deployUser, $serverIp, $sshPort, $webRoot);

    $output[] = 'Deploy user configured with web root access';
    $output[] = "Web root: {$webRoot}";
    $output[] = '';
    $output[] = '📋 Next steps:';
    $output[] = '1. Copy the SSH public key below';
    $output[] = '2. Add it to GitHub: Repo → Settings → Deploy Keys';
    $output[] = '3. Add the private key as a GitHub Secret named DEPLOY_SSH_KEY';
    $output[] = '4. Create .github/workflows/deploy.yml with the workflow below';

    return [
        'success' => true,
        'message' => 'GitHub deployment configured',
        'output' => $output,
        'sshPublicKey' => $publicKey,
        'workflowYaml' => $workflowYaml,
        'privateKeyPath' => $keyPath
    ];
}

/**
 * Generate GitHub Actions workflow YAML
 */
function generateGitHubWorkflow($domain, $branch, $deployUser, $serverIp, $sshPort, $webRoot)
{
    return "name: 🚀 Deploy to Production

on:
  push:
    branches: [{$branch}]
  workflow_dispatch:

env:
  SERVER_HOST: {$serverIp}
  SERVER_USER: {$deployUser}
  SERVER_PORT: {$sshPort}
  WEB_ROOT: {$webRoot}
  BRANCH: {$branch}

jobs:
  deploy:
    name: Deploy to Production Server
    runs-on: ubuntu-latest
    
    steps:
      - name: 📥 Checkout code
        uses: actions/checkout@v4
        with:
          fetch-depth: 0
      
      - name: 🔐 Setup SSH Key
        run: |
          mkdir -p ~/.ssh
          echo \"\${{ secrets.DEPLOY_SSH_KEY }}\" > ~/.ssh/deploy_key
          chmod 600 ~/.ssh/deploy_key
          ssh-keyscan -p \${{ env.SERVER_PORT }} -H \${{ env.SERVER_HOST }} >> ~/.ssh/known_hosts 2>/dev/null || true
      
      - name: 🧹 Clean Remote Directory
        run: |
          ssh -i ~/.ssh/deploy_key -p \${{ env.SERVER_PORT }} -o StrictHostKeyChecking=no \${{ env.SERVER_USER }}@\${{ env.SERVER_HOST }} << 'ENDSSH'
            echo '🗑️ Removing all files from {$webRoot}...'
            cd {$webRoot}
            # Remove all files including hidden ones, except the directory itself
            find . -mindepth 1 -delete 2>/dev/null || rm -rf ./* ./.[!.]* ./..?* 2>/dev/null || true
            echo '✅ Directory cleaned'
          ENDSSH
      
      - name: 📤 Deploy Files to Server
        run: |
          # Sync all files to server (fresh deployment)
          rsync -avz --delete \\
            -e \"ssh -i ~/.ssh/deploy_key -p \${{ env.SERVER_PORT }} -o StrictHostKeyChecking=no\" \\
            --exclude '.git' \\
            --exclude '.github' \\
            --exclude 'node_modules' \\
            --exclude 'vendor' \\
            ./ \${{ env.SERVER_USER }}@\${{ env.SERVER_HOST }}:\${{ env.WEB_ROOT }}/
      
      - name: 📦 Install Dependencies & Build
        run: |
          ssh -i ~/.ssh/deploy_key -p \${{ env.SERVER_PORT }} -o StrictHostKeyChecking=no \${{ env.SERVER_USER }}@\${{ env.SERVER_HOST }} << 'ENDSSH'
            cd {$webRoot}
            
            echo '📦 Installing dependencies...'
            
            # Composer (if composer.json exists)
            if [ -f \"composer.json\" ]; then
              echo '🎼 Running composer install...'
              composer install --no-dev --no-interaction --optimize-autoloader 2>&1 || echo '⚠️ Composer install had issues, continuing...'
            fi
            
            # NPM (if package.json exists)
            if [ -f \"package.json\" ]; then
              echo '📦 Running npm install...'
              npm ci --production 2>&1 || npm install --production 2>&1 || echo '⚠️ NPM install had issues, continuing...'
              
              # Build (if build script exists)
              if grep -q '\"build\"' package.json; then
                echo '🔨 Building assets...'
                npm run build 2>&1 || echo '⚠️ Build had issues, continuing...'
              fi
            fi
            
            # Laravel/PHP Framework specific (if artisan exists)
            if [ -f \"artisan\" ]; then
              echo '⚡ Running Laravel optimizations...'
              php artisan migrate --force 2>&1 || echo '⚠️ Migration had issues, continuing...'
              php artisan config:cache 2>&1 || true
              php artisan route:cache 2>&1 || true
              php artisan view:cache 2>&1 || true
            fi
            
            echo '✅ Dependencies installed'
          ENDSSH
      
      - name: 🔧 Set Permissions
        run: |
          ssh -i ~/.ssh/deploy_key -p \${{ env.SERVER_PORT }} -o StrictHostKeyChecking=no \${{ env.SERVER_USER }}@\${{ env.SERVER_HOST }} << 'ENDSSH'
            cd {$webRoot}
            
            echo '🔧 Setting permissions...'
            
            # Set ownership
            sudo chown -R www-data:www-data {$webRoot}
            
            # Set directory permissions
            find {$webRoot} -type d -exec chmod 775 {} \\;
            
            # Set file permissions
            find {$webRoot} -type f -exec chmod 664 {} \\;
            
            # Make storage and cache writable if they exist
            [ -d \"storage\" ] && chmod -R 775 storage
            [ -d \"bootstrap/cache\" ] && chmod -R 775 bootstrap/cache
            [ -d \"public/uploads\" ] && chmod -R 775 public/uploads
            [ -d \"cache\" ] && chmod -R 775 cache
            
            # Restart PHP-FPM
            sudo systemctl reload php*-fpm 2>/dev/null || true
            
            echo '✅ Permissions set'
          ENDSSH
      
      - name: 🧹 Cleanup SSH Key
        if: always()
        run: rm -f ~/.ssh/deploy_key
      
      - name: ✅ Deployment Complete
        run: echo '🎉 Deployment to {$domain} completed successfully!'
";
}

/**
 * Handle component testing
 */
function handleTest($component, $config)
{
    switch ($component) {
        case 'nginx':
            $result = run_command('systemctl is-active nginx');
            return ['success' => trim($result) === 'active'];

        case 'php':
            $result = run_command('php -v');
            return ['success' => strpos($result, 'PHP') !== false];

        case 'mysql':
            $password = $config['mysqlRootPassword'] ?? '';
            $result = run_command("mysqladmin -u root -p'{$password}' ping 2>/dev/null");
            return ['success' => strpos($result, 'alive') !== false];

        case 'redis':
            $result = run_command('redis-cli ping');
            return ['success' => trim($result) === 'PONG'];

        case 'nodejs':
            $result = run_command('node --version');
            return ['success' => strpos($result, 'v') === 0];

        default:
            return ['success' => false, 'message' => 'Unknown component'];
    }
}

/**
 * Handle component repair (auto-fix broken configs)
 */
function handleRepair($component, $config)
{
    $output = [];
    $domain = $config['domain'] ?? 'localhost';

    switch ($component) {
        case 'nginx':
            $output[] = 'Attempting to repair Nginx...';

            // Remove broken configs
            run_command("rm -f /etc/nginx/sites-enabled/{$domain}");
            run_command("rm -f /etc/nginx/sites-available/{$domain}");
            run_command('rm -f /etc/nginx/conf.d/rate-limit.conf');

            // Test default config
            $testResult = run_command('nginx -t 2>&1');
            if (strpos($testResult, 'successful') !== false) {
                run_command('systemctl restart nginx');
                $output[] = 'Nginx repaired - broken configs removed';
                $output[] = 'Please re-run Nginx installation step';
                return ['success' => true, 'message' => 'Nginx repaired', 'output' => $output];
            } else {
                // Restore default config
                run_command('apt-get install --reinstall -y nginx');
                run_command('systemctl restart nginx');
                $output[] = 'Nginx reinstalled with default config';
                return ['success' => true, 'message' => 'Nginx reinstalled', 'output' => $output];
            }

        case 'mysql':
            $output[] = 'Attempting to repair MySQL...';

            // Check if MySQL is running
            $status = run_command('systemctl is-active mysql');
            if (trim($status) !== 'active') {
                // Try to start MySQL
                run_command('systemctl start mysql');
                sleep(2);

                $status = run_command('systemctl is-active mysql');
                if (trim($status) !== 'active') {
                    // Check for socket issues
                    run_command('mkdir -p /var/run/mysqld');
                    run_command('chown mysql:mysql /var/run/mysqld');
                    run_command('systemctl restart mysql');
                    $output[] = 'Fixed MySQL socket directory';
                }
            }

            $output[] = 'MySQL service checked and restarted';
            return ['success' => true, 'message' => 'MySQL repaired', 'output' => $output];

        case 'redis':
            $output[] = 'Attempting to repair Redis...';
            run_command('systemctl restart redis-server');
            sleep(1);

            $ping = run_command('redis-cli ping');
            if (trim($ping) === 'PONG') {
                $output[] = 'Redis is now responding';
                return ['success' => true, 'message' => 'Redis repaired', 'output' => $output];
            } else {
                // Reinstall Redis
                run_command('apt-get install --reinstall -y redis-server');
                run_command('systemctl restart redis-server');
                $output[] = 'Redis reinstalled';
                return ['success' => true, 'message' => 'Redis reinstalled', 'output' => $output];
            }

        case 'php':
            $output[] = 'Attempting to repair PHP-FPM...';
            $phpVersion = trim(run_command('php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2'));
            run_command("systemctl restart php{$phpVersion}-fpm");
            $output[] = "PHP-FPM {$phpVersion} restarted";
            return ['success' => true, 'message' => 'PHP-FPM repaired', 'output' => $output];

        default:
            return ['success' => false, 'message' => 'Unknown component'];
    }
}

/**
 * Finalize installation and cleanup
 */
function handleFinalize($config)
{
    $output = [];
    $domain = $config['domain'] ?? '';
    $sslMode = $config['sslMode'] ?? 'letsencrypt'; // 'letsencrypt', 'cloudflare', or 'none'

    // Handle SSL based on mode
    if ($sslMode === 'letsencrypt') {
        $output[] = 'Installing Certbot for SSL...';
        run_command('apt-get install -y certbot python3-certbot-nginx');

        if (!empty($domain) && $domain !== 'localhost') {
            $output[] = 'Obtaining SSL certificate...';
            $certResult = run_command("certbot --nginx -d {$domain} -d www.{$domain} --non-interactive --agree-tos --email admin@{$domain} 2>&1");

            if (strpos($certResult, 'Successfully') !== false) {
                $output[] = 'SSL certificate installed';
            } else {
                $output[] = 'SSL certificate installation skipped (verify DNS first)';
            }
        }
    } elseif ($sslMode === 'cloudflare') {
        $output[] = 'Configuring Cloudflare Origin SSL...';

        // Save origin certificate and key from user input
        $cert = $config['cloudflareCert'] ?? '';
        $key = $config['cloudflareKey'] ?? '';

        if (!empty($cert) && !empty($key)) {
            // Create SSL directory
            $sslDir = "/etc/ssl/{$domain}";
            run_command("mkdir -p {$sslDir}");

            // Save certificate and key
            file_put_contents("{$sslDir}/origin.crt", $cert);
            file_put_contents("{$sslDir}/origin.key", $key);

            // Set secure permissions
            run_command("chmod 600 {$sslDir}/origin.key");
            run_command("chmod 644 {$sslDir}/origin.crt");
            run_command("chown root:root {$sslDir}/*");

            $output[] = "Origin certificate saved to {$sslDir}";

            // Configure Nginx with the origin certificate
            configureCloudflareSSL($domain, $sslDir);
            $output[] = 'Nginx configured for Cloudflare Full (Strict) SSL';
        } else {
            // Fallback to flexible mode config
            configureCloudflareSSL($domain, null);
            $output[] = 'Cloudflare SSL configured (Flexible mode - no origin cert provided)';
        }
    } else {
        $output[] = 'SSL skipped - configure manually later';
    }

    // Remove temporary installer port from UFW
    run_command('ufw delete allow 8080/tcp 2>/dev/null');

    // Final system cleanup
    run_command('apt-get autoremove -y');
    run_command('apt-get clean');

    // Save credentials to a secure file
    $credentials = [
        'domain' => $domain,
        'mysql_password' => $config['mysqlRootPassword'] ?? '',
        'ssh_port' => $config['sshPort'] ?? 22,
        'ssl_mode' => $sslMode,
        'installed_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents('/root/.server-panel-credentials.json', json_encode($credentials, JSON_PRETTY_PRINT));
    chmod('/root/.server-panel-credentials.json', 0600);

    $output[] = 'Credentials saved to /root/.server-panel-credentials.json';

    // Self-destruction - find all possible installer locations
    $possibleDirs = [
        dirname(__DIR__),
        '/tmp/server-panel-installer',
        '/tmp/server-panel',
        '/root/server-panel',
        '/var/www/server-panel'
    ];

    $dirsToRemove = [];
    foreach ($possibleDirs as $dir) {
        if (is_dir($dir) && file_exists($dir . '/install.sh')) {
            $dirsToRemove[] = $dir;
        }
    }

    // Create cleanup script with all directories
    $rmCommands = implode("\n", array_map(fn($d) => "rm -rf \"{$d}\"", $dirsToRemove));
    $cleanupScript = "#!/bin/bash
sleep 3
{$rmCommands}
pkill -f 'php -S 0.0.0.0:8080' 2>/dev/null || true
rm -- \"\$0\"
";
    file_put_contents('/tmp/cleanup-installer.sh', $cleanupScript);
    chmod('/tmp/cleanup-installer.sh', 0755);

    // Run cleanup in background using at command or nohup
    run_command('nohup /tmp/cleanup-installer.sh > /dev/null 2>&1 &');

    $output[] = 'Installer files will be removed in 3 seconds';
    $output[] = 'Directories to clean: ' . implode(', ', $dirsToRemove);

    return [
        'success' => true,
        'message' => 'Setup complete! Server is production-ready.',
        'output' => $output
    ];
}

/**
 * Configure Nginx for Cloudflare SSL
 */
function configureCloudflareSSL($domain, $sslDir = null)
{
    // Get PHP version
    $phpVersion = trim(run_command('php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2'));
    $webRoot = "/var/www/{$domain}";

    // Cloudflare IP ranges for real IP restoration
    $cloudflareConfig = "# Cloudflare Configuration
# Restore real visitor IP
set_real_ip_from 103.21.244.0/22;
set_real_ip_from 103.22.200.0/22;
set_real_ip_from 103.31.4.0/22;
set_real_ip_from 104.16.0.0/13;
set_real_ip_from 104.24.0.0/14;
set_real_ip_from 108.162.192.0/18;
set_real_ip_from 131.0.72.0/22;
set_real_ip_from 141.101.64.0/18;
set_real_ip_from 162.158.0.0/15;
set_real_ip_from 172.64.0.0/13;
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 188.114.96.0/20;
set_real_ip_from 190.93.240.0/20;
set_real_ip_from 197.234.240.0/22;
set_real_ip_from 198.41.128.0/17;
set_real_ip_from 2400:cb00::/32;
set_real_ip_from 2606:4700::/32;
set_real_ip_from 2803:f800::/32;
set_real_ip_from 2405:b500::/32;
set_real_ip_from 2405:8100::/32;
set_real_ip_from 2c0f:f248::/32;
set_real_ip_from 2a06:98c0::/29;
real_ip_header CF-Connecting-IP;
";
    file_put_contents('/etc/nginx/conf.d/cloudflare.conf', $cloudflareConfig);

    // Determine if we have origin certificate for Full Strict mode
    $hasOriginCert = $sslDir && file_exists("{$sslDir}/origin.crt") && file_exists("{$sslDir}/origin.key");

    if ($hasOriginCert) {
        // Full Strict mode - HTTPS with origin certificate
        $nginxConfig = "# {$domain} - Cloudflare Full (Strict) SSL Configuration
server {
    listen 80;
    listen [::]:80;
    server_name {$domain} www.{$domain};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {$domain} www.{$domain};
    root {$webRoot}/public;
    index index.php index.html;

    # Cloudflare Origin Certificate
    ssl_certificate {$sslDir}/origin.crt;
    ssl_certificate_key {$sslDir}/origin.key;
    
    # SSL settings optimized for Cloudflare
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    # Security headers
    add_header X-Frame-Options \"SAMEORIGIN\" always;
    add_header X-Content-Type-Options \"nosniff\" always;
    add_header X-XSS-Protection \"1; mode=block\" always;
    add_header Referrer-Policy \"strict-origin-when-cross-origin\" always;
    add_header Strict-Transport-Security \"max-age=31536000; includeSubDomains\" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/xml+rss application/atom+xml image/svg+xml;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        fastcgi_pass unix:/run/php/php{$phpVersion}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)\$ {
        expires 1y;
        add_header Cache-Control \"public, immutable\";
    }

    location ~ /\\. {
        deny all;
    }

    location /api {
        limit_req zone=api burst=20 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    access_log /var/log/nginx/{$domain}.access.log;
    error_log /var/log/nginx/{$domain}.error.log;
}
";
    } else {
        // Flexible mode - HTTP only (Cloudflare handles SSL termination)
        $nginxConfig = "# {$domain} - Cloudflare Flexible SSL Configuration
server {
    listen 80;
    listen [::]:80;
    server_name {$domain} www.{$domain};
    root {$webRoot}/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options \"SAMEORIGIN\" always;
    add_header X-Content-Type-Options \"nosniff\" always;
    add_header X-XSS-Protection \"1; mode=block\" always;
    add_header Referrer-Policy \"strict-origin-when-cross-origin\" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/xml+rss application/atom+xml image/svg+xml;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        fastcgi_pass unix:/run/php/php{$phpVersion}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)\$ {
        expires 1y;
        add_header Cache-Control \"public, immutable\";
    }

    location ~ /\\. {
        deny all;
    }

    location /api {
        limit_req zone=api burst=20 nodelay;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    access_log /var/log/nginx/{$domain}.access.log;
    error_log /var/log/nginx/{$domain}.error.log;
}
";
    }

    file_put_contents("/etc/nginx/sites-available/{$domain}", $nginxConfig);
    run_command('nginx -t && systemctl reload nginx');
}

