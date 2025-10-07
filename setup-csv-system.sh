#!/bin/bash

# CSV Upload System Setup Script
# This script helps set up the required dependencies for CSV upload functionality

echo "🔧 Setting up CSV Upload System"
echo "================================"
echo ""

# Check if running as root
if [ "$EUID" -eq 0 ]; then 
    echo "❌ Please don't run this script as root"
    exit 1
fi

# Check PHP installation
echo "📦 Checking PHP installation..."
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP 8.1 or higher"
    exit 1
fi

PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
echo "✅ PHP $PHP_VERSION found"

# Check Composer
echo "📦 Checking Composer..."
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer first"
    exit 1
fi
echo "✅ Composer found"

# Check MongoDB
echo "📦 Checking MongoDB..."
if ! command -v mongod &> /dev/null; then
    echo "⚠️  MongoDB not found. Installing MongoDB..."
    echo "Please follow MongoDB installation guide for your OS"
else
    echo "✅ MongoDB found"
fi

# Check Beanstalkd
echo "📦 Checking Beanstalkd..."
if ! command -v beanstalkd &> /dev/null; then
    echo "⚠️  Beanstalkd not found. Attempting to install..."
    
    # Detect OS and install Beanstalkd
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        if command -v apt-get &> /dev/null; then
            echo "Installing Beanstalkd via apt..."
            sudo apt-get update
            sudo apt-get install -y beanstalkd
        elif command -v yum &> /dev/null; then
            echo "Installing Beanstalkd via yum..."
            sudo yum install -y beanstalkd
        else
            echo "❌ Could not detect package manager. Please install Beanstalkd manually"
            exit 1
        fi
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        if command -v brew &> /dev/null; then
            echo "Installing Beanstalkd via Homebrew..."
            brew install beanstalkd
        else
            echo "❌ Homebrew not found. Please install Beanstalkd manually"
            exit 1
        fi
    else
        echo "❌ Unsupported OS. Please install Beanstalkd manually"
        exit 1
    fi
else
    echo "✅ Beanstalkd found"
fi

# Install PHP dependencies
echo ""
echo "📚 Installing PHP dependencies..."
if [ -f "composer.json" ]; then
    composer install --no-dev --optimize-autoloader
    echo "✅ PHP dependencies installed"
else
    echo "❌ composer.json not found. Make sure you're in the Laravel project directory"
    exit 1
fi

# Check .env file
echo ""
echo "⚙️  Checking configuration..."
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo "📝 Created .env file from .env.example"
    else
        echo "❌ .env.example not found. Please create .env file manually"
        exit 1
    fi
fi

# Generate app key if needed
if ! grep -q "APP_KEY=base64:" .env; then
    php artisan key:generate
    echo "🔑 Generated application key"
fi

# Start services
echo ""
echo "🚀 Starting services..."

# Start Beanstalkd
echo "Starting Beanstalkd..."
if command -v systemctl &> /dev/null; then
    sudo systemctl start beanstalkd
    sudo systemctl enable beanstalkd
    echo "✅ Beanstalkd started and enabled"
else
    # Fallback: start manually
    beanstalkd -d -p 11300
    echo "✅ Beanstalkd started on port 11300"
fi

# Start MongoDB
echo "Starting MongoDB..."
if command -v systemctl &> /dev/null; then
    sudo systemctl start mongod
    sudo systemctl enable mongod
    echo "✅ MongoDB started and enabled"
else
    echo "⚠️  Please start MongoDB manually"
fi

# Test connections
echo ""
echo "🔍 Testing connections..."

# Test Beanstalkd connection
if nc -z localhost 11300 2>/dev/null; then
    echo "✅ Beanstalkd is running on port 11300"
else
    echo "❌ Cannot connect to Beanstalkd on port 11300"
fi

# Test MongoDB connection
if nc -z localhost 27017 2>/dev/null; then
    echo "✅ MongoDB is running on port 27017"
else
    echo "❌ Cannot connect to MongoDB on port 27017"
fi

echo ""
echo "🎉 Setup complete!"
echo ""
echo "Next steps:"
echo "1. Configure your .env file with database credentials"
echo "2. Run migrations: php artisan migrate"
echo "3. Start the queue worker: ./start-csv-worker.sh"
echo "4. Access the upload form: http://localhost:8000/upload-csv"
echo ""
echo "For detailed instructions, see CSV_UPLOAD_README.md"