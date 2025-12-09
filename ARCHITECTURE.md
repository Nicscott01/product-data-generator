# Product Data Generator - System Architecture

## High-Level Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     WordPress/WooCommerce                       │
│                         Product Data                            │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                  Product Data Generator Plugin                  │
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐ │
│  │              User Request / Function Call                 │ │
│  │   generate_product_content($product_id, $template_id)    │ │
│  └───────────────────────┬───────────────────────────────────┘ │
│                          │                                       │
│                          ▼                                       │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │           Template_Config (Configuration)                │   │
│  │  • Template ID                                           │   │
│  │  • Product ID                                            │   │
│  │  • Context data                                          │   │
│  │  • AI settings                                           │   │
│  │  • Output format                                         │   │
│  └───────────────────────┬─────────────────────────────────┘   │
│                          │                                       │
│                          ▼                                       │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │           AI_Generator (Orchestrator)                    │   │
│  │  1. Validate config                                      │   │
│  │  2. Get template from registry                           │   │
│  │  3. Get product data                                     │   │
│  │  4. Generate content                                     │   │
│  │  5. Format output                                        │   │
│  │  6. Save (optional)                                      │   │
│  └───────────────────────┬─────────────────────────────────┘   │
│                          │                                       │
│         ┌────────────────┴────────────────┐                     │
│         ▼                                  ▼                     │
│  ┌──────────────┐                  ┌──────────────┐            │
│  │  Template    │                  │  Template    │            │
│  │  Registry    │                  │  (Instance)  │            │
│  │              │                  │              │            │
│  │  • Get       │                  │  • Product   │            │
│  │  • Register  │                  │  • Context   │            │
│  │  • Unregister│                  │  • Aggregate │            │
│  └──────────────┘                  │  • Build     │            │
│                                     │    Prompts   │            │
│                                     └──────┬───────┘            │
│                                            │                    │
│                                            ▼                    │
│                                     ┌──────────────┐            │
│                                     │   Messages   │            │
│                                     │  (Prompts)   │            │
│                                     └──────┬───────┘            │
└────────────────────────────────────────────┼────────────────────┘
                                             │
                                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    WordPress AI_Client                          │
│                      (wp-ai-client)                             │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    OpenAI / AI Service                          │
│                   (GPT-4, GPT-3.5, etc.)                        │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Generated Content                           │
│                    (Returned to caller)                         │
└─────────────────────────────────────────────────────────────────┘
```

## Data Flow Diagram

```
Product ID + Template ID + Config
           │
           ▼
    ┌──────────────┐
    │  Template    │
    │   Config     │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐
    │      AI      │
    │  Generator   │──────┐
    └──────┬───────┘      │
           │              │ Get Template
           │              ▼
           │       ┌──────────────┐
           │       │  Template    │
           │       │  Registry    │
           │       └──────────────┘
           │
           │ Get Product
           ▼
    ┌──────────────┐
    │  WooCommerce │
    │   Product    │
    └──────┬───────┘
           │
           ▼
    ┌──────────────────────────────┐
    │      Template Instance       │
    │                              │
    │  aggregate_product_data()    │──────┐
    │          │                   │      │
    │          ▼                   │      │ Filters
    │  build_system_prompt()       │◄─────┤ Applied
    │          │                   │      │
    │          ▼                   │      │
    │  build_user_prompt()         │◄─────┘
    │          │                   │
    │          ▼                   │
    │    get_messages()            │
    └──────────┬───────────────────┘
               │
               ▼
    ┌──────────────────────────────┐
    │       AI Messages            │
    │  [                           │
    │    {                         │
    │      role: "system",         │
    │      content: "..."          │
    │    },                        │
    │    {                         │
    │      role: "user",           │
    │      content: "..."          │
    │    }                         │
    │  ]                           │
    └──────────┬───────────────────┘
               │
               ▼
    ┌──────────────────────────────┐
    │    WordPress AI_Client       │
    │  chat_completion($params)    │
    └──────────┬───────────────────┘
               │
               ▼
    ┌──────────────────────────────┐
    │       AI Response            │
    └──────────┬───────────────────┘
               │
               ▼
    ┌──────────────────────────────┐
    │    AI_Generator              │
    │  extract_content()           │
    │  format_content()            │
    └──────────┬───────────────────┘
               │
               ▼
    ┌──────────────────────────────┐
    │    Generated Content         │
    │  {                           │
    │    content: "...",           │
    │    raw_content: "...",       │
    │    metadata: {...}           │
    │  }                           │
    └──────────┬───────────────────┘
               │
               ▼
        Save (optional)
               │
               ▼
    ┌──────────────────────────────┐
    │    WooCommerce Product       │
    │    (Updated)                 │
    └──────────────────────────────┘
```

## Template System Architecture

```
                    ┌──────────────────┐
                    │     Template     │
                    │  (Abstract Base) │
                    └────────┬─────────┘
                             │
                             │ extends
         ┌───────────────────┼───────────────────┐
         │                   │                   │
         ▼                   ▼                   ▼
┌─────────────────┐  ┌─────────────────┐  ┌──────────────┐
│  Product        │  │  Product Short  │  │  Product     │
│  Description    │  │  Description    │  │  SEO         │
│  Template       │  │  Template       │  │  Template    │
└─────────────────┘  └─────────────────┘  └──────────────┘
         │                   │                   │
         └───────────────────┼───────────────────┘
                             │
                             ▼
                    ┌──────────────────┐
                    │   Template       │
                    │   Registry       │
                    │                  │
                    │  register()      │
                    │  get()           │
                    │  get_all()       │
                    └──────────────────┘
```

## Component Interaction Flow

```
┌─────────────┐
│   User      │
│   Code      │
└──────┬──────┘
       │
       │ generate_product_content($product_id, $template_id, $config)
       │
       ▼
┌──────────────────────────────────────────────────────────────┐
│                  Helper Function (init.php)                   │
│                                                               │
│  1. Create Template_Config                                   │
│  2. Create AI_Generator                                      │
│  3. Call generate()                                          │
└──────┬───────────────────────────────────────────────────────┘
       │
       ▼
┌──────────────────────────────────────────────────────────────┐
│                     AI_Generator                             │
│                                                              │
│  ┌──────────────┐      ┌──────────────┐                    │
│  │   Config     │──────│   Template   │                    │
│  │              │      │              │                    │
│  └──────────────┘      └──────┬───────┘                    │
│                               │                             │
│                               ▼                             │
│                        ┌──────────────┐                    │
│                        │   Product    │                    │
│                        │              │                    │
│                        └──────┬───────┘                    │
│                               │                             │
│  ┌────────────────────────────┼───────────────────────┐   │
│  │  1. aggregate_product_data()                       │   │
│  │  2. build_system_prompt()                          │   │
│  │  3. build_user_prompt()                            │   │
│  │  4. get_messages()                                 │   │
│  └────────────────────────────┼───────────────────────┘   │
│                                │                            │
│                                ▼                            │
│                         ┌──────────────┐                   │
│                         │  AI_Client   │                   │
│                         │  Request     │                   │
│                         └──────┬───────┘                   │
│                                │                            │
│                                ▼                            │
│                         ┌──────────────┐                   │
│                         │   Process    │                   │
│                         │   Response   │                   │
│                         └──────┬───────┘                   │
│                                │                            │
│                                ▼                            │
│                         ┌──────────────┐                   │
│                         │   Format     │                   │
│                         │   Content    │                   │
│                         └──────┬───────┘                   │
│                                │                            │
│                                ▼                            │
│                         ┌──────────────┐                   │
│                         │  Auto-Save   │                   │
│                         │  (Optional)  │                   │
│                         └──────┬───────┘                   │
└────────────────────────────────┼───────────────────────────┘
                                 │
                                 ▼
                          ┌──────────────┐
                          │   Result     │
                          │   Array      │
                          └──────────────┘
```

## Filter/Hook System

```
┌──────────────────────────────────────────────────────────────────┐
│                        Filters Applied                           │
└──────────────────────────────────────────────────────────────────┘

1. Product Data Aggregation
   ↓
   product_data_generator_basic_product_data
   product_data_generator_{template}_product_data
   ↓
2. Prompt Building
   ↓
   product_data_generator_{template}_system_prompt
   product_data_generator_{template}_user_prompt
   product_data_generator_prompt_data
   ↓
3. Message Creation
   ↓
   product_data_generator_messages
   ↓
4. AI Parameters
   ↓
   product_data_generator_ai_params
   ↓
5. Content Generation
   ↓
   [AI_Client Request]
   ↓
6. Post-Processing
   ↓
   product_data_generator_generated_content
   product_data_generator_format_{format}
   ↓
7. Saving
   ↓
   product_data_generator_before_save (action)
   [Save to Product]
   product_data_generator_after_save (action)


┌──────────────────────────────────────────────────────────────────┐
│                        Actions Fired                             │
└──────────────────────────────────────────────────────────────────┘

init (priority 5)
   └─> AI_Client::init()

init (priority 10)
   └─> Template_Registry::init()
       └─> Template_Registry::register_default_templates()
           └─> product_data_generator_register_templates (action)

[During Generation]
   product_data_generator_before_generate
   product_data_generator_after_generate
   product_data_generator_before_save (if saving)
   product_data_generator_after_save (if saving)
```

## Configuration Preset Flow

```
User calls:
  AI_Generator::from_preset('short_description', $product_id)
           │
           ▼
  Template_Config::from_preset('short_description', $product_id)
           │
           ▼
  Template_Config::get_presets()
           │
           ▼
  apply_filters('product_data_generator_config_presets', $presets)
           │
           ▼
  Retrieve preset config
           │
           ▼
  Merge with product_id
           │
           ▼
  Return new Template_Config instance
           │
           ▼
  Create AI_Generator with config
           │
           ▼
  Ready to generate()
```

## Extension Points

```
Custom Template Creation:
1. Extend Template class
2. Implement abstract methods
3. Register via hook
4. Use in generation calls

Custom Data:
1. Hook into filters
2. Add product meta
3. Add custom context
4. Modify prompts

Custom Presets:
1. Filter presets array
2. Add custom config
3. Use via from_preset()

Custom Output:
1. Filter generated content
2. Add custom formatting
3. Post-process results
```

## Class Relationships

```
Template_Config ────creates───▶ AI_Generator
                                     │
                                     │ uses
                                     │
                    ┌────────────────┼────────────────┐
                    │                │                │
                    ▼                ▼                ▼
              Template_Registry   Template      WC_Product
                    │
                    │ provides
                    ▼
              Template Instance
                    │
                    │ generates
                    ▼
               AI Messages
                    │
                    │ sent to
                    ▼
               AI_Client
                    │
                    │ returns
                    ▼
            Generated Content
```

## Summary

The system is designed with:
- **Separation of Concerns**: Each class has a single responsibility
- **Extensibility**: Multiple hooks and filters at every stage
- **Flexibility**: Config system allows fine-grained control
- **Reusability**: Templates can be reused with different configs
- **Maintainability**: Clear structure and comprehensive documentation
