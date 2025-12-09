# Bulk Generation Queue System

The Bulk Generation Queue system allows you to generate AI content for multiple products in the background using Action Scheduler.

## Features

- **Background Processing**: Uses WooCommerce's Action Scheduler for reliable background job execution
- **Flexible Product Selection**: Use WP_Query arguments to select products
- **Per-Template Control**: Configure each template individually with:
  - Enable/disable toggle
  - Skip if already generated (per-template)
  - Custom temperature setting
- **Progress Tracking**: Real-time progress monitoring with success/failure counts
- **Queue Locking**: Only one queue can process at a time
- **Pause/Resume**: Pause processing and resume later
- **Batch Processing**: Configure batch size and delay between batches

## Usage

### Creating a Queue

1. Go to **Products → Bulk Generation**
2. Click **Add New Queue**
3. Give your queue a descriptive title

### Configuring Query

Enter WP_Query arguments as a PHP array in the **Product Query** metabox:

```php
[
  'post_type' => 'product',
  'posts_per_page' => -1,
  'post_status' => 'publish',
]
```

**Examples:**

- Specific products: `['post__in' => [123, 456, 789]]`
- By category: `['tax_query' => [['taxonomy' => 'product_cat', 'terms' => 'books']]]`
- In stock only: `['meta_query' => [['key' => '_stock_status', 'value' => 'instock']]]`

### Selecting Templates

In the **Generation Settings** metabox:

1. Check the templates you want to generate
2. For each template:
   - **Skip if Generated**: Check to skip products that already have this template generated
   - **Temperature**: Set creativity level (0-2, default 0.7)

**Important:** Skip logic is per-template! You can:
- Regenerate some templates while skipping others
- Each template checks independently if it should be skipped

### Queue Options

- **Batch Size**: Number of products to process at once (default: 5)
- **Delay Between Batches**: Seconds to wait between batches (default: 2)
- **Retry Failed**: Attempt to regenerate failed items (future feature)

### Preview & Start

1. Click **Preview Queue** to see:
   - Number of products matched
   - Number of templates selected
   - Total generations to be performed (accounting for skip logic)
   - First 10 products with their templates

2. Click **Start Queue** to begin processing

## How It Works

### Skip Logic (Per-Template)

Each product has a `_pdg_generations` meta field that stores timestamps:

```php
[
  'product_description' => 1702123456,
  'product_short_description' => 1702123457,
]
```

When processing:
- If template's "Skip if Generated" is checked
- AND `_pdg_generations[$template_id]` exists for this product
- THEN skip this template for this product
- OTHERWISE generate content

**Example Scenario:**

Product has:
- `product_description` generated ✓
- `product_short_description` generated ✓

Queue configured:
- `product_description`: Skip if Generated ✓
- `product_short_description`: Skip if Generated ✗

Result:
- `product_description` → **Skipped** (already generated)
- `product_short_description` → **Regenerated** (skip unchecked)

### Processing Flow

1. **Queue Start**:
   - Validates configuration
   - Checks for active queues (only 1 allowed)
   - Calculates work items (accounting for skip logic)
   - Sets status to `pdg_processing`
   - Schedules first batch

2. **Batch Processing**:
   - Action Scheduler executes batch
   - Schedules individual product/template jobs
   - Schedules next batch if work remains

3. **Product Processing**:
   - Fetches product and template
   - Checks skip logic (again, in case product changed)
   - Generates content via AI Client
   - Updates `_pdg_generations` meta
   - Logs success/failure
   - Fires hooks for custom handling

4. **Completion**:
   - Sets status to `pdg_completed`
   - Records completion timestamp
   - Fires completion hooks

## Queue Statuses

- **Draft**: Not yet started
- **Processing**: Currently running
- **Paused**: Manually paused
- **Completed**: Finished successfully
- **Failed**: Completed with errors

## Progress Tracking

View real-time progress in the **Progress** metabox:
- Total, completed, failed, remaining counts
- Visual progress bar
- Currently processing product
- Percentage complete

## Results

Results are stored in `_pdg_results` meta field:

```php
[
  '123_product_description' => [
    'product_id' => 123,
    'template_id' => 'product_description',
    'success' => true,
    'skipped' => false,
    'message' => 'Generated successfully',
    'timestamp' => 1702123456,
  ],
]
```

**Note:** Results must be manually deleted. To reprocess a queue:
1. Edit the queue
2. Click **Start Queue** again

## Hooks

### Actions

**Queue Lifecycle:**
```php
// Fired when queue starts
do_action( 'product_data_generator_queue_started', $queue_id, $total_items );

// Fired when queue pauses
do_action( 'product_data_generator_queue_paused', $queue_id );

// Fired when queue completes
do_action( 'product_data_generator_queue_completed', $queue_id, $progress );

// Fired after each item processes
do_action( 'product_data_generator_item_processed', $queue_id, $product_id, $template_id, $success, $message );
```

**Example Usage:**

```php
// Send notification when queue completes
add_action( 'product_data_generator_queue_completed', function( $queue_id, $progress ) {
    $admin_email = get_option( 'admin_email' );
    $queue = get_post( $queue_id );
    
    wp_mail( $admin_email, 
        'Queue Completed: ' . $queue->post_title,
        sprintf( 'Completed: %d, Failed: %d', $progress['completed'], $progress['failed'] )
    );
}, 10, 2 );
```

## Performance Considerations

### Action Scheduler

- Uses WooCommerce's built-in Action Scheduler
- Runs on WordPress cron (requires cron to be active)
- Batches work to avoid timeouts
- Resilient to server restarts

### CPU/Memory

- Batch size controls concurrent processing
- Delay between batches prevents overwhelming server
- Each generation is a separate action (isolated failures)
- Monitor via Action Scheduler tools

### Rate Limiting

AI API calls are made sequentially with delays:
- 1 second between individual items in a batch
- Configurable delay between batches
- Prevents API rate limit issues

## Troubleshooting

### Queue Won't Start

- Check for another processing queue (only 1 allowed)
- Verify WP-Cron is enabled
- Ensure Action Scheduler is active (comes with WooCommerce)

### Items Not Processing

- Check Action Scheduler status: **Tools → Action Scheduler**
- Look for failed actions
- Review error messages in queue results

### Slow Processing

- Increase batch size (if server can handle it)
- Decrease delay between batches
- Check AI Client response times

### Skip Logic Not Working

- Verify `_pdg_generations` meta field exists on products
- Check template ID matches exactly
- Ensure "Skip if Generated" is checked for that specific template

## Database Schema

### Queue Meta Fields

- `_pdg_query_args`: WP_Query arguments (string)
- `_pdg_template_config`: Template settings (array)
- `_pdg_batch_size`: Products per batch (int)
- `_pdg_delay`: Seconds between batches (int)
- `_pdg_retry_failed`: Retry failed items (bool)
- `_pdg_work_items`: Remaining work (array)
- `_pdg_progress`: Progress tracking (array)
- `_pdg_results`: Results log (array)
- `_pdg_preview_cache`: Preview data (array)

### Product Meta Fields

- `_pdg_generations`: Template generation timestamps (array)

## Security

- Requires `manage_woocommerce` capability
- Nonce verification on all AJAX requests
- Query args evaluated in controlled context
- Product/template validation before processing

## Future Enhancements

- Concurrent queue support (multiple queues)
- Email notifications
- Retry failed generations automatically
- Export results to CSV
- Schedule queues for later execution
- Template dependencies (generate A before B)
- Conditional generation based on product attributes
