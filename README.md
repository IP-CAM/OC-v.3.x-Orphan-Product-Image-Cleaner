# Orphan Product Image Cleaner for Opencart v3.x
⚠️ Warning: This script permanently deletes images not found in:  
- `DB_PREFIX + product.image`  
- `DB_PREFIX + product_image.image`  
Always backup your system before proceeding!

**Features:**
1. Two-step process (Dry Run → Confirmation)
2. Database prefix aware
3. Recursive directory scanning
4. Supports common image formats
5. Simple UI with warnings
6. Results display with error reporting

**Instructions:**
1. Place in OpenCart root directory
2. Backup system first!
3. Access via browser
4. Start with Dry Run
5. Review results before deletion

**Security Notes:**
- Remove the script after use
- Protect with .htaccess if needed
- Should be run from admin area in production
- Processes only image files (no SQL/executables)

The script maintains OpenCart database prefix consistency and focuses only on product-related images to minimize accidental deletions.
