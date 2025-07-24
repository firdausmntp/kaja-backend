#!/bin/bash

# storage-link.sh
# Script untuk membuat symlink storage di shared hosting

echo "🔗 Creating storage symlink for shared hosting..."

# Go to project root
cd /home/username/public_html/kaja  # Sesuaikan path

# Run artisan storage:link
php artisan storage:link

# Check if symlink was created
if [ -L "public/storage" ]; then
    echo "✅ Storage symlink created successfully!"
else
    echo "❌ Failed to create symlink. Manual method required."
    
    # Manual symlink creation
    echo "🔧 Trying manual symlink creation..."
    ln -sf ../storage/app/public public/storage
    
    if [ -L "public/storage" ]; then
        echo "✅ Manual symlink created!"
    else
        echo "❌ Symlink not supported. Using copy method..."
        
        # Copy method as fallback
        mkdir -p public/storage
        cp -r storage/app/public/* public/storage/
        echo "✅ Files copied to public/storage/"
    fi
fi

echo "🎉 Storage linking completed!"
