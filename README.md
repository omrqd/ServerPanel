# Server Panel 🚀

A comprehensive web-based installer for configuring production-ready Linux VPS servers. Installs and secures everything needed for high-traffic PHP + React applications.

## Features

- **Security First**: UFW firewall, Fail2ban, SSH hardening, security headers
- **PHP 8.2+**: With all required extensions and optimized PHP-FPM
- **Node.js 20.x LTS**: For React/SPA build tools
- **Nginx**: Production-optimized with SSL, gzip, caching
- **MySQL 8.0**: With swap management for low-memory VPS
- **Redis**: For caching and session storage
- **Auto SSL**: Let's Encrypt certificate installation
- **Self-Destructing**: Installer removes itself after completion

## Quick Start

```bash
# Clone to your fresh VPS
git clone https://github.com/yourusername/server-panel.git /tmp/server-panel
cd /tmp/server-panel

# Run the installer
chmod +x install.sh
sudo ./install.sh
```

Then open your browser to: `http://YOUR_SERVER_IP:8080`

## Requirements

- Fresh Ubuntu 22.04 LTS or Debian 11/12
- Root access (sudo)
- Domain pointed to server IP (for SSL)
- Minimum 1GB RAM (2GB recommended)

## What Gets Installed

| Component | Version | Purpose |
|-----------|---------|---------|
| PHP | 8.2+ | Application runtime |
| PHP-FPM | 8.2+ | FastCGI process manager |
| Nginx | Latest | Web server |
| MySQL | 8.0 | Database |
| Redis | Latest | Caching |
| Node.js | 20.x LTS | Build tools |
| Certbot | Latest | SSL certificates |
| UFW | Latest | Firewall |
| Fail2ban | Latest | Intrusion prevention |

## Installation Steps

1. **Welcome**: Enter your domain name
2. **Security**: Configure firewall and Fail2ban
3. **PHP**: Verify and install extensions
4. **Node.js**: Install Node.js 20.x
5. **Nginx**: Configure web server
6. **MySQL**: Database with swap setup
7. **Redis**: Cache server
8. **Testing**: Verify all components
9. **Complete**: SSL setup and cleanup

## After Installation

- Your site will be available at `https://yourdomain.com`
- Credentials saved to `/root/.server-panel-credentials.json`
- Logs in `/var/log/nginx/`, `/var/log/mysql/`, `/var/log/php-fpm/`

## Project Structure

```
server-panel/
├── install.sh          # Bootstrap script
├── index.php           # Web installer UI
├── assets/
│   ├── css/
│   │   └── styles.css  # Tailwind + custom CSS
│   └── js/
│       └── installer.js # Frontend logic
├── api/
│   ├── install.php     # API handlers
│   └── helpers.php     # Utility functions
└── templates/
    ├── nginx.conf      # Nginx config template
    ├── mysql.cnf       # MySQL config template
    └── php-fpm.conf    # PHP-FPM pool template
```

## License

MIT License - Feel free to use and modify!
