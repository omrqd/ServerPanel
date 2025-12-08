<?php
/**
 * Server Panel - Helper Functions
 */

/**
 * Execute a shell command and return output
 */
function run_command($command)
{
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    return implode("\n", $output);
}

/**
 * Stream output in real-time (for long-running commands)
 */
function stream_command($command, $callback = null)
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $process = proc_open($command, $descriptors, $pipes);

    if (!is_resource($process)) {
        return false;
    }

    fclose($pipes[0]);

    $output = '';
    while (!feof($pipes[1])) {
        $line = fgets($pipes[1]);
        if ($line !== false) {
            $output .= $line;
            if ($callback) {
                $callback($line);
            }
        }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    return $output;
}

/**
 * Check if a service is running
 */
function check_service($service)
{
    $result = run_command("systemctl is-active {$service}");
    return trim($result) === 'active';
}

/**
 * Check if a package is installed
 */
function is_package_installed($package)
{
    $result = run_command("dpkg -s {$package} 2>/dev/null | grep Status");
    return strpos($result, 'install ok installed') !== false;
}

/**
 * Get system memory in MB
 */
function get_system_memory()
{
    $result = run_command("free -m | grep Mem | awk '{print $2}'");
    return (int) trim($result);
}

/**
 * Get system disk space in GB
 */
function get_disk_space()
{
    $result = run_command("df -BG / | tail -1 | awk '{print $4}' | tr -d 'G'");
    return (int) trim($result);
}

/**
 * Generate a secure random password
 */
function generate_password($length = 16)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Validate domain name
 */
function is_valid_domain($domain)
{
    return preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $domain);
}

/**
 * Get server's public IP
 */
function get_public_ip()
{
    $ip = run_command('curl -s -4 ifconfig.me 2>/dev/null');
    if (empty(trim($ip))) {
        $ip = run_command('curl -s -4 icanhazip.com 2>/dev/null');
    }
    return trim($ip);
}

/**
 * Log to file
 */
function log_message($message, $level = 'INFO')
{
    $logFile = '/var/log/server-panel.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Backup a file before modifying
 */
function backup_file($path)
{
    if (file_exists($path)) {
        $backupPath = $path . '.backup.' . date('YmdHis');
        copy($path, $backupPath);
        return $backupPath;
    }
    return false;
}

/**
 * Replace content in a file
 */
function replace_in_file($path, $search, $replace)
{
    if (!file_exists($path)) {
        return false;
    }
    $content = file_get_contents($path);
    $content = str_replace($search, $replace, $content);
    return file_put_contents($path, $content);
}

/**
 * Append to a file if content doesn't exist
 */
function append_if_missing($path, $search, $content)
{
    $existing = file_exists($path) ? file_get_contents($path) : '';
    if (strpos($existing, $search) === false) {
        file_put_contents($path, $existing . $content);
        return true;
    }
    return false;
}

/**
 * Get PHP version
 */
function get_php_version()
{
    return trim(run_command('php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2'));
}

/**
 * Check if running as root
 */
function is_root()
{
    // Use whoami instead of posix_getuid() as posix extension may not be available
    return trim(shell_exec('whoami')) === 'root';
}

/**
 * Get OS information
 */
function get_os_info()
{
    $info = [];

    if (file_exists('/etc/os-release')) {
        $content = file_get_contents('/etc/os-release');
        preg_match('/^NAME="?([^"\n]+)"?/m', $content, $matches);
        $info['name'] = $matches[1] ?? 'Unknown';

        preg_match('/^VERSION_ID="?([^"\n]+)"?/m', $content, $matches);
        $info['version'] = $matches[1] ?? 'Unknown';
    }

    return $info;
}
