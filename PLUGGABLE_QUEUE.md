# Pluggable Queue System - Implementation Summary

## Overview
Transformed the PDG queue system into a modular, extensible architecture that allows add-on plugins to hook into the processing flow. This eliminates redundant loops and makes the system efficient for various product types.

## Architecture

### Core Flow (PDG Plugin)
```
Product enters queue
    ↓
1. Check task options
    ↓
2. Run pdg_queue_fetch_data hook (if enabled)
    ↓
3. Run pdg_queue_replace_image hook (if enabled)
    ↓
4. Run pdg_queue_generate_content (if enabled)
    ↓
Done - single pass per product
```

### Hooks Added to PDG
- `pdg_queue_fetch_data` - Fetch/update product data from external sources
- `pdg_queue_replace_image` - Upgrade/replace product images
- `pdg_queue_generate_content` - Generate AI content (existing functionality)

### Task Options
Users can select which tasks to run via checkboxes in queue UI:
- **Fetch Product Data** - Data miners run here (ISBNdb, CSV imports, APIs)
- **Replace Featured Image** - Image upgrade logic, deletes old images
- **Generate AI Content** - Template-based AI generation (uses credits)

## Bookshop Plugin Integration

### Hooks Registered
```php
add_action( 'pdg_queue_fetch_data', 'queue_fill_book_data' );
add_action( 'pdg_queue_replace_image', 'queue_upgrade_cover_image' );
```

### Methods Created

#### `queue_fill_book_data()`
- Entry point from PDG queue
- Checks if product is a book
- Calls `fill_book_data()` with force=true

#### `fill_book_data()`
- Core data fetching logic (no skip checks)
- Accepts options: metadata, image, categories, accolades, force
- Fetches from ISBNdb/Google Books
- Extracts accolades
- Updates all book metadata
- Modular - can run specific updates only

#### `update_book_metadata()`
- Separated metadata update logic
- Updates title, description, authors, publisher, dates, etc.
- Reusable helper method

#### `upgrade_cover_image()`
- Compares existing vs new image dimensions
- Downloads new image temporarily to check size
- Replaces if new is 50px+ larger in either dimension
- **Deletes old attachment with `wp_delete_attachment($id, true)`**
- Logs upgrade details in post meta

### Image Cleanup
Old images are permanently deleted when replaced:
```php
wp_delete_attachment( $current_attachment_id, true ); // true = delete file
```

Prevents media library bloat and disk space waste.

## Bulk Refresh Interface

### Location
WooCommerce → Refresh Books

### Features
**Filter Options:**
- All Books
- Books Without Cover Image
- Books Without Accolades  
- Books Added in Last 30 Days

**Task Selection:**
- ☑ Fetch Product Data (ISBNdb/Google Books)
- ☐ Replace/Upgrade Cover Images
- ☐ Generate AI Content

### How It Works
1. User selects filter and tasks
2. Creates PDG queue post with:
   - WP_Query args based on filter
   - Task options based on selection
   - Template config (only if AI generation selected)
3. Redirects to queue edit page
4. User reviews and starts queue

## Benefits

### Efficiency
✅ Single pass per product (no double-looping)
✅ Only runs selected tasks (no wasted AI credits)
✅ Parallel processing via Action Scheduler

### Extensibility
✅ Any plugin can hook into queue tasks
✅ Works for any product type (books, manufacturer data, etc.)
✅ Future-ready for AI image generation hooks

### Maintainability
✅ Clean separation of concerns
✅ Bookshop plugin hooks in, doesn't replace
✅ Core PDG logic remains generic

### User Experience
✅ Granular control over what runs
✅ Clear task descriptions
✅ No AI credits wasted on data maintenance
✅ Old images automatically cleaned up

## Example Use Cases

### Refresh All Book Images
1. Go to WooCommerce → Refresh Books
2. Select "All Books"
3. Check only "Replace/Upgrade Cover Images"
4. Create queue
5. Start processing
6. Result: All books get higher-res covers, old images deleted

### Extract Accolades for Existing Books
1. Select "All Books"
2. Check only "Fetch Product Data"
3. Uncheck "Generate AI Content"
4. Result: Accolades extracted, no AI credits used

### Full Refresh with AI Content
1. Select filter (e.g., "Recent")
2. Check all three tasks
3. Result: Fresh data, upgraded images, new AI content

## Future Extensibility

### Manufacturer Data Plugin
```php
add_action( 'pdg_queue_fetch_data', function($product_id) {
    // Import from manufacturer CSV/API
    import_manufacturer_specs( $product_id );
});
```

### AI Image Generation Plugin
```php
add_action( 'pdg_queue_generate_images', function($product_id) {
    // Generate lifestyle product photos
    generate_ai_lifestyle_image( $product_id );
});
```

### Inventory Sync Plugin
```php
add_action( 'pdg_queue_fetch_data', function($product_id) {
    // Sync from external inventory system
    sync_inventory_data( $product_id );
});
```

## Files Modified

### PDG Plugin
- `includes/queue/class-queue-processor.php` - Added task hooks and options
- `includes/queue/class-queue-admin.php` - Added task selection metabox

### Bookshop Plugin
- `inc/book-data-miner.php` - Added queue methods, refactored data fetching
- `inc/class-bulk-refresh-admin.php` - New bulk refresh interface
- `perkins-cove-bookshop.php` - Include new admin class

## Testing Checklist

- [ ] Create queue with only "Fetch Data" - verify no AI calls
- [ ] Create queue with only "Replace Image" - verify images upgraded and old deleted
- [ ] Create queue with all tasks - verify correct order of operations
- [ ] Test bulk refresh filters (missing image, missing accolades, recent)
- [ ] Verify old images are deleted from uploads folder
- [ ] Test with non-book products - verify hooks don't run
- [ ] Check queue progress tracking works correctly
- [ ] Verify accolade extraction still works in queue
