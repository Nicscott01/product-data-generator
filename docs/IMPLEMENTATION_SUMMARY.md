# Bulk Generation Queue - Implementation Summary

## What Was Built

A complete bulk generation queue system for the Product Data Generator plugin that allows background processing of AI content generation across multiple products.

## Files Created

### Core Classes
1. **`includes/queue/class-queue-post-type.php`** - Custom post type registration with custom statuses
2. **`includes/queue/class-queue-admin.php`** - Admin interface with metaboxes and AJAX handlers
3. **`includes/queue/class-queue-processor.php`** - Background processing engine using Action Scheduler

### Assets
4. **`assets/js/queue-admin.js`** - Frontend JavaScript for queue management
5. **`assets/css/queue-admin.css`** - Styling for queue list table

### Documentation
6. **`docs/QUEUE_SYSTEM.md`** - Complete user and developer documentation

## Files Modified

1. **`init.php`** - Added queue namespace autoloading and initialization hooks

## Key Features Implemented

### 1. Custom Post Type: `pdg_queue`
- Located under **Products → Bulk Generation** menu
- Custom statuses: Processing, Completed, Failed, Paused
- Capabilities tied to `manage_woocommerce`

### 2. Admin Interface (4 Metaboxes)

**Query Builder:**
- Simple textarea for WP_Query args
- PHP array format with syntax examples
- Auto-adds `post_type => 'product'`

**Generation Settings:**
- Table view of all registered templates
- Per-template configuration:
  - Enable/disable checkbox
  - Skip if generated checkbox (per-template!)
  - Temperature slider (0-2)
- Enable/disable toggles dependent fields

**Queue Options:**
- Batch size (products per batch)
- Delay between batches (seconds)
- Retry failed (future feature toggle)

**Preview & Start:**
- Preview button shows:
  - Product count
  - Template count
  - Total generations (accounting for skip logic)
  - First 10 products with their templates
- Start button (disabled if another queue is processing)
- Pause button (when processing)
- Status notices

### 3. Progress Tracking Metabox
- Visual progress bar
- Completed/Failed/Remaining counts
- Currently processing product link
- Percentage complete

### 4. Processing Engine

**Action Scheduler Integration:**
- Two hooks: `pdg_process_queue_batch` and `pdg_process_queue_product`
- Batch processing with configurable size/delay
- Individual product/template jobs for isolation
- 1-second delay between items in batch

**Queue Locking:**
- Only 1 queue can process at a time
- Checked on start and in UI
- Warning displayed if another queue is active

**Skip Logic (Per-Template):**
```php
foreach ( $enabled_templates as $template_id => $config ) {
    $skip_if_generated = $config['skip_if_generated'];
    
    // Check THIS template for THIS product
    if ( $skip_if_generated && isset( $generation_meta[$template_id] ) ) {
        continue; // Skip only this template
    }
    
    // Generate content
}
```

**Result Logging:**
- Stores success/failure per product/template
- Includes message and timestamp
- Updates progress after each item
- Auto-completes queue when done

### 5. List Table Enhancements
- Custom columns: Progress, Products, Templates
- Mini progress bars with percentage
- Status badges with colors
- Responsive design (hides columns on mobile)

## Preview Count Logic (Option B)

The preview counts **account for skip logic**:
- Queries all matching products
- For each product, checks each template's skip setting
- Only counts templates that will actually generate
- Shows realistic "Total Generations" number

Example:
- 100 products found
- 3 templates enabled
- 50 products already have template A generated
- Template A has "Skip if Generated" checked
- Template B and C don't have skip checked

**Preview shows:**
- Products: 100
- Templates: 3
- Total Generations: 250 (not 300)
  - Template A: 50 (100 - 50 skipped)
  - Template B: 100
  - Template C: 100

## Hooks Provided

```php
// Queue lifecycle
do_action( 'product_data_generator_queue_started', $queue_id, $total_items );
do_action( 'product_data_generator_queue_paused', $queue_id );
do_action( 'product_data_generator_queue_completed', $queue_id, $progress );
do_action( 'product_data_generator_item_processed', $queue_id, $product_id, $template_id, $success, $message );
```

## Database Schema

### Queue Post Meta
- `_pdg_query_args` - WP_Query args as string
- `_pdg_template_config` - Array of template settings
- `_pdg_batch_size` - Batch size (int)
- `_pdg_delay` - Delay in seconds (int)
- `_pdg_retry_failed` - Retry toggle (bool)
- `_pdg_work_items` - Remaining work queue (array)
- `_pdg_progress` - Progress tracking (array)
- `_pdg_results` - Results log (array)
- `_pdg_preview_cache` - Preview data (array)

### Product Meta (Existing)
- `_pdg_generations` - Template timestamps (array)

## Security

- All AJAX handlers verify nonces
- Capability checks: `manage_woocommerce`
- Query args evaluated in controlled context
- Template/product validation before processing

## Performance

- Action Scheduler handles background processing
- Batched work prevents timeouts
- Configurable rate limiting
- Sequential API calls with delays
- Isolated failures (per product/template)

## Next Steps for User

1. Test with a small queue first
2. Monitor Action Scheduler (Tools → Action Scheduler)
3. Check progress in real-time
4. Review results when complete
5. Manually delete completed queues

## Future Enhancements

- Concurrent queue support
- Email notifications
- Auto-retry failed items
- CSV export of results
- Scheduled execution
- Template dependencies
- Conditional generation

## Testing Checklist

- [ ] Create new queue appears under Products menu
- [ ] Query builder accepts valid PHP arrays
- [ ] Template checkboxes enable/disable fields
- [ ] Preview shows correct counts with skip logic
- [ ] Start button disabled when another queue is processing
- [ ] Queue processes in background
- [ ] Progress updates in real-time
- [ ] Results logged correctly
- [ ] Pause functionality works
- [ ] Queue completes successfully
- [ ] Skip logic works per-template
- [ ] Temperature settings applied correctly

## Documentation

Full documentation available in `docs/QUEUE_SYSTEM.md` including:
- Usage instructions
- Configuration examples
- Skip logic explanation
- Hook documentation
- Troubleshooting guide
- Performance considerations
