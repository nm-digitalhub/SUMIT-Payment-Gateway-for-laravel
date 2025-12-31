#!/bin/bash
#
# Enhance phpDocumentor UI/UX
# Adds custom CSS to all HTML files
#

DOCS_DIR="/var/www/vhosts/nm-digitalhub.com/httpdocs/public/sumit-docs"

echo "🎨 Enhancing phpDocumentor UI/UX..."

# Add custom CSS link to all HTML files
find "$DOCS_DIR" -name "*.html" -type f | while read -r file; do
    # Check if custom.css is already linked
    if ! grep -q "custom.css" "$file"; then
        # Add custom CSS before </head>
        sed -i 's|</head>|    <link rel="stylesheet" href="css/custom.css">\n</head>|' "$file"
        echo "  ✅ Enhanced: $(basename "$file")"
    fi
done

# Fix relative paths for nested directories
find "$DOCS_DIR" -path "$DOCS_DIR/classes/*.html" -o -path "$DOCS_DIR/namespaces/*.html" -o -path "$DOCS_DIR/files/*.html" | while read -r file; do
    # Update CSS path for nested files
    sed -i 's|href="css/custom.css"|href="../css/custom.css"|g' "$file"
done

echo ""
echo "✨ UI Enhancement Complete!"
echo "📍 Documentation: https://nm-digitalhub.com/sumit-docs/"
