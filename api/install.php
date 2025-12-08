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
        default:
            return ['success' => false, 'message' => 'Unknown component'];
    }
}

/**
 * Install security components (UFW, Fail2ban)
 */
function installSecurity($config)
{
    $output = [];

    // Install UFW
    if ($config['enableUfw'] ?? true) {
        $output[] = 'Installing UFW firewall...';
        run_command('apt-get install -y ufw');

        // Configure UFW
        run_command('ufw default deny incoming');
        run_command('ufw default allow outgoing');

        $sshPort = $config['sshPort'] ?? 22;
        run_command("ufw allow {$sshPort}/tcp");
        run_command('ufw allow 80/tcp');
        run_command('ufw allow 443/tcp');
        run_command('ufw allow 8080/tcp'); // Temporary for installer

        run_command('echo "y" | ufw enable');
        $output[] = 'UFW firewall configured';
    }

    // Install Fail2ban
    if ($config['enableFail2ban'] ?? true) {
        $output[] = 'Installing Fail2ban...';
        run_command('apt-get install -y fail2ban');

        // Create jail.local
        $jailConfig = "[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = {$config['sshPort']}
filter = sshd
logpath = /var/log/auth.log
maxretry = 3

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
port = http,https
logpath = /var/log/nginx/error.log
";
        file_put_contents('/etc/fail2ban/jail.local', $jailConfig);

        run_command('systemctl enable fail2ban');
        run_command('systemctl restart fail2ban');
        $output[] = 'Fail2ban configured';
    }

    // SSH hardening (optional port change)
    if (($config['sshPort'] ?? 22) != 22) {
        $output[] = "Changing SSH port to {$config['sshPort']}...";
        run_command("sed -i 's/#Port 22/Port {$config['sshPort']}/' /etc/ssh/sshd_config");
        run_command("sed -i 's/Port 22/Port {$config['sshPort']}/' /etc/ssh/sshd_config");
        $output[] = 'SSH port updated. Remember to connect on new port!';
    }

    return [
        'success' => true,
        'message' => 'Security components installed successfully',
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
    $output[] = 'Optimizing PHP-FPM...';
    $fpmConfig = "; Production PHP-FPM Pool
[www]
user = www-data
group = www-data
listen = /run/php/php{$phpVersion}-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
";
    file_put_contents("/etc/php/{$phpVersion}/fpm/pool.d/www.conf", $fpmConfig);

    // Optimize OPcache
    $opcacheConfig = "
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
opcache.enable_cli=0
";
    file_put_contents("/etc/php/{$phpVersion}/fpm/conf.d/99-opcache.ini", $opcacheConfig);

    run_command("systemctl restart php{$phpVersion}-fpm");
    $output[] = 'PHP-FPM optimized and restarted';

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

    // Set permissions
    run_command("chown -R www-data:www-data {$webRoot}");
    run_command("chmod -R 755 {$webRoot}");

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
        'message' => 'Nginx configured successfully',
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
 * Finalize installation and cleanup
 */
function handleFinalize($config)
{
    $output = [];
    $domain = $config['domain'] ?? '';

    // Install Certbot for SSL
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
        'installed_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents('/root/.server-panel-credentials.json', json_encode($credentials, JSON_PRETTY_PRINT));
    chmod('/root/.server-panel-credentials.json', 0600);

    $output[] = 'Credentials saved to /root/.server-panel-credentials.json';

    // Schedule self-destruction
    $installerDir = dirname(__DIR__);

    // Create cleanup script
    $cleanupScript = "#!/bin/bash
sleep 5
rm -rf {$installerDir}
rm -rf /tmp/server-panel-installer
pkill -f 'php -S 0.0.0.0:8080'
rm -- \"\$0\"
";
    file_put_contents('/tmp/cleanup-installer.sh', $cleanupScript);
    chmod('/tmp/cleanup-installer.sh', 0755);

    // Run cleanup in background
    exec('nohup /tmp/cleanup-installer.sh > /dev/null 2>&1 &');

    $output[] = 'Installer will be removed in 5 seconds';

    return [
        'success' => true,
        'message' => 'Setup complete! Server is production-ready.',
        'output' => $output
    ];
}
