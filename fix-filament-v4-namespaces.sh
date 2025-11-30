#!/bin/bash
# Fix Filament v4 namespace issues in all Resources
# Changes Forms\Components\Section → Schemas\Components\Section
# Adds "use Filament\Schemas;" import where needed

cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel

FILES=$(grep -rl "Forms\\Components\\Section" src/Filament/ --include="*.php" 2>/dev/null || echo "")

if [ -z "$FILES" ]; then
    echo "✅ No files found with Forms\Components\Section - all fixed!"
    exit 0
fi

echo "📋 Found files to fix:"
echo "$FILES" | sed 's/^/  - /'
echo ""

for file in $FILES; do
    echo "🔧 Processing: $file"

    # Check if file already has "use Filament\Schemas;"
    if ! grep -q "^use Filament\\\\Schemas;" "$file"; then
        # Add "use Filament\Schemas;" after "use Filament\Forms;"
        if grep -q "^use Filament\\\\Forms;" "$file"; then
            sed -i '/^use Filament\\Forms;/a use Filament\\Schemas;' "$file"
            echo "  ✓ Added: use Filament\Schemas;"
        else
            # If no Forms import, add after Filament\Schemas\Schema
            if grep -q "^use Filament\\\\Schemas\\\\Schema;" "$file"; then
                sed -i '/^use Filament\\Schemas\\Schema;/a use Filament\\Schemas;' "$file"
                echo "  ✓ Added: use Filament\Schemas;"
            fi
        fi
    fi

    # Replace Forms\Components\Section with Schemas\Components\Section
    sed -i 's/Forms\\Components\\Section/Schemas\\Components\\Section/g' "$file"
    echo "  ✓ Replaced: Forms\Components\Section → Schemas\Components\Section"

    echo "  ✅ Done: $file"
    echo ""
done

echo "🎉 All files fixed!"
echo ""
echo "Verification:"
REMAINING=$(grep -rl "Forms\\Components\\Section" src/Filament/ --include="*.php" 2>/dev/null | wc -l)
if [ "$REMAINING" -eq 0 ]; then
    echo "✅ SUCCESS - No remaining Forms\Components\Section found!"
else
    echo "⚠️  WARNING - Still found $REMAINING files with Forms\Components\Section"
fi
