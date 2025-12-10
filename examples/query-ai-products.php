<!-- 
WP Query to Display Products with AI-Generated Content

Usage: Add this to any WordPress template file or create a custom page template.
This query fetches all products that have AI-generated content (based on _pdg_generations meta).
-->

<?php
/**
 * Query products that have AI-generated content
 */
$args = [
    'post_type'      => 'product',
    'posts_per_page' => -1, // Change to a number like 20 for pagination
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => [
        [
            'key'     => '_pdg_generations',
            'compare' => 'EXISTS',
        ],
    ],
];

$ai_products = new WP_Query( $args );

if ( $ai_products->have_posts() ) : ?>
    
    <div class="ai-generated-products">
        <h2>Products with AI-Generated Content</h2>
        <p>Found <?php echo $ai_products->found_posts; ?> products with AI-generated content.</p>
        
        <div class="products-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
            
            <?php while ( $ai_products->have_posts() ) : $ai_products->the_post(); 
                global $product;
                
                // Get generation meta to see what was generated
                $generations = get_post_meta( get_the_ID(), '_pdg_generations', true );
                $template_count = is_array( $generations ) ? count( $generations ) : 0;
            ?>
                
                <div class="product-card" style="border: 1px solid #ddd; padding: 15px; border-radius: 8px;">
                    
                    <?php if ( has_post_thumbnail() ) : ?>
                        <a href="<?php the_permalink(); ?>">
                            <?php the_post_thumbnail( 'medium', [ 'style' => 'width: 100%; height: auto; border-radius: 4px;' ] ); ?>
                        </a>
                    <?php endif; ?>
                    
                    <h3 style="margin: 10px 0;">
                        <a href="<?php the_permalink(); ?>" style="text-decoration: none; color: #333;">
                            <?php the_title(); ?>
                        </a>
                    </h3>
                    
                    <?php if ( $product ) : ?>
                        <p class="price" style="font-size: 18px; font-weight: bold; color: #2c3e50;">
                            <?php echo $product->get_price_html(); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="ai-badge" style="background: #3498db; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; display: inline-block; margin-top: 10px;">
                        🤖 <?php echo $template_count; ?> AI Generation<?php echo $template_count !== 1 ? 's' : ''; ?>
                    </div>
                    
                    <?php if ( is_array( $generations ) && ! empty( $generations ) ) : ?>
                        <div class="generated-templates" style="margin-top: 10px; font-size: 12px; color: #666;">
                            <strong>Generated:</strong>
                            <ul style="margin: 5px 0; padding-left: 20px;">
                                <?php foreach ( $generations as $template_id => $timestamp ) : ?>
                                    <li>
                                        <?php 
                                        echo esc_html( ucwords( str_replace( '_', ' ', $template_id ) ) ); 
                                        echo ' <em>(' . human_time_diff( $timestamp ) . ' ago)</em>';
                                        ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="excerpt" style="margin-top: 10px; font-size: 14px; color: #555;">
                        <?php echo wp_trim_words( get_the_excerpt(), 20 ); ?>
                    </div>
                    
                    <a href="<?php the_permalink(); ?>" class="view-product" style="display: inline-block; margin-top: 10px; padding: 8px 15px; background: #2ecc71; color: white; text-decoration: none; border-radius: 4px;">
                        View Product
                    </a>
                    
                </div>
                
            <?php endwhile; ?>
            
        </div>
        
    </div>
    
<?php else : ?>
    
    <p>No products with AI-generated content found.</p>
    
<?php endif; 

wp_reset_postdata();
?>


<!-- 
====================================================================
VARIATIONS OF THE QUERY
====================================================================
-->

<?php
/*
// Query products with SPECIFIC template generated (e.g., only product_description)
$args = [
    'post_type'      => 'product',
    'posts_per_page' => 20,
    'post_status'    => 'publish',
    'meta_query'     => [
        [
            'key'     => '_pdg_generations',
            'value'   => 'product_description',
            'compare' => 'LIKE',
        ],
    ],
];


// Query products generated in the last 7 days
$args = [
    'post_type'      => 'product',
    'posts_per_page' => 20,
    'post_status'    => 'publish',
    'meta_query'     => [
        [
            'key'     => '_pdg_generations',
            'compare' => 'EXISTS',
        ],
    ],
    'date_query'     => [
        [
            'column' => 'post_modified',
            'after'  => '7 days ago',
        ],
    ],
];


// Query products with multiple generations (more than 2 templates)
// Note: This requires a custom meta query callback
$args = [
    'post_type'      => 'product',
    'posts_per_page' => 20,
    'post_status'    => 'publish',
    'meta_query'     => [
        [
            'key'     => '_pdg_generations',
            'compare' => 'EXISTS',
        ],
    ],
];
$query = new WP_Query( $args );
// Then filter in the loop to check count( $generations ) > 2


// Query products in specific category with AI content
$args = [
    'post_type'      => 'product',
    'posts_per_page' => 20,
    'post_status'    => 'publish',
    'tax_query'      => [
        [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => 'books', // Change to your category slug
        ],
    ],
    'meta_query'     => [
        [
            'key'     => '_pdg_generations',
            'compare' => 'EXISTS',
        ],
    ],
];


// Query products WITHOUT AI content (for comparison)
$args = [
    'post_type'      => 'product',
    'posts_per_page' => 20,
    'post_status'    => 'publish',
    'meta_query'     => [
        [
            'key'     => '_pdg_generations',
            'compare' => 'NOT EXISTS',
        ],
    ],
];
*/
?>


<!-- 
====================================================================
SHORTCODE VERSION
Add this to your theme's functions.php to use as [ai_products]
====================================================================
-->

<?php
/*
function ai_generated_products_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'limit'    => 12,
        'columns'  => 3,
        'category' => '',
    ], $atts );
    
    $args = [
        'post_type'      => 'product',
        'posts_per_page' => intval( $atts['limit'] ),
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'     => '_pdg_generations',
                'compare' => 'EXISTS',
            ],
        ],
    ];
    
    if ( ! empty( $atts['category'] ) ) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( $atts['category'] ),
            ],
        ];
    }
    
    $query = new WP_Query( $args );
    
    ob_start();
    
    if ( $query->have_posts() ) {
        echo '<div class="ai-products-grid" style="display: grid; grid-template-columns: repeat(' . intval( $atts['columns'] ) . ', 1fr); gap: 20px;">';
        
        while ( $query->have_posts() ) {
            $query->the_post();
            wc_get_template_part( 'content', 'product' );
        }
        
        echo '</div>';
    }
    
    wp_reset_postdata();
    
    return ob_get_clean();
}
add_shortcode( 'ai_products', 'ai_generated_products_shortcode' );

// Usage: [ai_products limit="12" columns="3" category="books"]
*/
?>
