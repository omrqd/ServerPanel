#!/bin/bash

#===============================================================================
#
#          FILE:  install.sh
#
#         USAGE:  ./install.sh
#
#   DESCRIPTION:  Server Panel Bootstrap Installer
#                 Prepares a fresh Linux VPS for production use
#
#       VERSION:  1.0.0
#        AUTHOR:  Server Panel
#
#===============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

# Configuration
INSTALLER_PORT=8080
INSTALLER_DIR="/tmp/server-panel-installer"
MIN_PHP_VERSION="8.1"

#===============================================================================
# Helper Functions
#===============================================================================

print_banner() {
    clear
    echo -e "${PURPLE}"
    echo "╔══════════════════════════════════════════════════════════════════════╗"
    echo "║                                                                      ║"
    echo "║   ███████╗███████╗██████╗ ██╗   ██╗███████╗██████╗                   ║"
    echo "║   ██╔════╝██╔════╝██╔══██╗██║   ██║██╔════╝██╔══██╗                  ║"
    echo "║   ███████╗█████╗  ██████╔╝██║   ██║█████╗  ██████╔╝                  ║"
    echo "║   ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██╔══╝  ██╔══██╗                  ║"
    echo "║   ███████║███████╗██║  ██║ ╚████╔╝ ███████╗██║  ██║                  ║"
    echo "║   ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚══════╝╚═╝  ╚═╝                  ║"
    echo "║                                                                      ║"
    echo "║   ██████╗  █████╗ ███╗   ██╗███████╗██╗                              ║"
    echo "║   ██╔══██╗██╔══██╗████╗  ██║██╔════╝██║                              ║"
    echo "║   ██████╔╝███████║██╔██╗ ██║█████╗  ██║                              ║"
    echo "║   ██╔═══╝ ██╔══██║██║╚██╗██║██╔══╝  ██║                              ║"
    echo "║   ██║     ██║  ██║██║ ╚████║███████╗███████╗                         ║"
    echo "║   ╚═╝     ╚═╝  ╚═╝╚═╝  ╚═══╝╚══════╝╚══════╝                         ║"
    echo "║                                                                      ║"
    echo "║           Production VPS Setup & Security Installer                  ║"
    echo "║                                                                      ║"
    echo "╚══════════════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo ""
}

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "${CYAN}[STEP]${NC} $1"
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root!"
        log_info "Please run: sudo ./install.sh"
        exit 1
    fi
}

detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
    elif type lsb_release >/dev/null 2>&1; then
        OS=$(lsb_release -si)
        VER=$(lsb_release -sr)
    else
        OS=$(uname -s)
        VER=$(uname -r)
    fi
    
    log_info "Detected OS: $OS $VER"
    
    # Check if supported
    case "$OS" in
        *Ubuntu*|*Debian*)
            log_success "Supported operating system detected"
            ;;
        *)
            log_warning "This OS may not be fully supported. Proceeding anyway..."
            ;;
    esac
}

get_server_ip() {
    # Try multiple methods to get the public IP
    SERVER_IP=$(curl -s -4 ifconfig.me 2>/dev/null || \
                curl -s -4 icanhazip.com 2>/dev/null || \
                curl -s -4 ipecho.net/plain 2>/dev/null || \
                hostname -I | awk '{print $1}' 2>/dev/null || \
                echo "localhost")
    
    log_info "Server IP: $SERVER_IP"
}

#===============================================================================
# Installation Functions
#===============================================================================

update_system() {
    log_step "Updating system packages..."
    
    export DEBIAN_FRONTEND=noninteractive
    
    apt-get update -y > /dev/null 2>&1
    apt-get upgrade -y > /dev/null 2>&1
    
    log_success "System packages updated"
}

install_dependencies() {
    log_step "Installing base dependencies..."
    
    apt-get install -y \
        curl \
        wget \
        git \
        unzip \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        gnupg \
        lsb-release \
        > /dev/null 2>&1
    
    log_success "Base dependencies installed"
}

install_php() {
    log_step "Installing PHP..."
    
    # Detect Ubuntu version
    UBUNTU_VERSION=$(lsb_release -rs 2>/dev/null || echo "22.04")
    log_info "Ubuntu version: $UBUNTU_VERSION"
    
    # For Ubuntu 24.04+, use native PHP 8.3
    # For older versions, use ppa:ondrej/php
    if [[ "$UBUNTU_VERSION" == "24.04" ]] || [[ "$UBUNTU_VERSION" > "24" ]]; then
        log_info "Using native PHP 8.3 for Ubuntu 24.04..."
        PHP_VER="8.3"
    else
        log_info "Adding PHP repository for older Ubuntu..."
        if ! command -v php &> /dev/null; then
            add-apt-repository -y ppa:ondrej/php 2>&1 | tail -n 3 || true
            apt-get update -y 2>&1 | tail -n 3
        fi
        PHP_VER="8.2"
    fi
    
    log_info "Installing PHP $PHP_VER packages (this may take a few minutes)..."
    
    # Install PHP and essential extensions with visible output
    apt-get install -y \
        php${PHP_VER} \
        php${PHP_VER}-cli \
        php${PHP_VER}-fpm \
        php${PHP_VER}-common \
        php${PHP_VER}-mysql \
        php${PHP_VER}-zip \
        php${PHP_VER}-gd \
        php${PHP_VER}-mbstring \
        php${PHP_VER}-curl \
        php${PHP_VER}-xml \
        php${PHP_VER}-bcmath \
        php${PHP_VER}-intl \
        php${PHP_VER}-readline \
        php${PHP_VER}-opcache \
        2>&1 | grep -E "(Unpacking|Setting up|php)" | tail -n 10
    
    # Install redis extension separately (may not exist on all systems)
    apt-get install -y php${PHP_VER}-redis 2>/dev/null || log_warning "php-redis not available, will install later"
    
    # Verify installation
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
        log_success "PHP $PHP_VERSION installed successfully"
    else
        log_error "PHP installation failed!"
        exit 1
    fi
}

setup_installer_directory() {
    log_step "Setting up installer directory..."
    
    # Create installer directory
    mkdir -p "$INSTALLER_DIR"
    
    # Get the directory where this script is located
    SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
    
    # Copy installer files
    if [ -d "$SCRIPT_DIR" ]; then
        cp -r "$SCRIPT_DIR"/* "$INSTALLER_DIR/" 2>/dev/null || true
        cp -r "$SCRIPT_DIR"/.* "$INSTALLER_DIR/" 2>/dev/null || true
    fi
    
    # Set permissions
    chmod -R 755 "$INSTALLER_DIR"
    
    log_success "Installer directory ready at $INSTALLER_DIR"
}

start_web_installer() {
    log_step "Starting web installer..."
    
    # Kill any existing PHP server on the port
    pkill -f "php -S 0.0.0.0:$INSTALLER_PORT" 2>/dev/null || true
    
    # Wait a moment
    sleep 1
    
    # Start PHP built-in server
    cd "$INSTALLER_DIR"
    nohup php -S 0.0.0.0:$INSTALLER_PORT -t "$INSTALLER_DIR" > /tmp/server-panel.log 2>&1 &
    
    # Wait for server to start
    sleep 2
    
    # Check if server is running
    if pgrep -f "php -S 0.0.0.0:$INSTALLER_PORT" > /dev/null; then
        log_success "Web installer started successfully"
    else
        log_error "Failed to start web installer"
        cat /tmp/server-panel.log
        exit 1
    fi
}

print_completion_message() {
    echo ""
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                                      ║${NC}"
    echo -e "${GREEN}║              🎉 Bootstrap Installation Complete! 🎉                 ║${NC}"
    echo -e "${GREEN}║                                                                      ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "${CYAN}  Continue setup in your browser:${NC}"
    echo ""
    echo -e "${YELLOW}  ┌────────────────────────────────────────────────────────┐${NC}"
    echo -e "${YELLOW}  │                                                        │${NC}"
    echo -e "${YELLOW}  │   ${WHITE}http://${SERVER_IP}:${INSTALLER_PORT}${YELLOW}                             │${NC}"
    echo -e "${YELLOW}  │                                                        │${NC}"
    echo -e "${YELLOW}  └────────────────────────────────────────────────────────┘${NC}"
    echo ""
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "${PURPLE}  Note: After completing the web setup, all installer files${NC}"
    echo -e "${PURPLE}  will be automatically removed for security.${NC}"
    echo ""
}

#===============================================================================
# Main Execution
#===============================================================================

main() {
    print_banner
    
    log_info "Starting Server Panel Bootstrap Installation..."
    echo ""
    
    # Pre-flight checks
    check_root
    detect_os
    get_server_ip
    
    echo ""
    echo -e "${WHITE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    # Installation steps
    update_system
    install_dependencies
    install_php
    setup_installer_directory
    start_web_installer
    
    # Done!
    print_completion_message
}

# Run main function
main "$@"
