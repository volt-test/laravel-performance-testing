# CSV Data Source Documentation

The VoltTest Laravel wrapper now supports CSV data sources for dynamic test data, allowing you to load realistic test data from CSV files instead of hardcoding values in your scenarios.

## Quick Start

```php
use VoltTest\Laravel\Facades\VoltTest;

$manager = VoltTest::manager();

// Create scenario with CSV data source
$scenario = $manager->scenario('User Login Flow')
    ->dataSource('users.csv', 'unique', true);

$scenario->step('Login')
    ->post('/login', [
        'email' => '${email}',        // From CSV column
        'password' => '${password}'   // From CSV column
    ])
    ->expectStatus(200);

$scenario->step('View Profile')
    ->get('/profile/${user_id}')     // user_id from same CSV row
    ->expectStatus(200);
```

## CSV File Format

Your CSV files should follow this structure:

```csv
email,password,user_id,name
user1@example.com,password123,1,John Doe
user2@example.com,password456,2,Jane Smith
user3@example.com,password789,3,Bob Wilson
```

### Requirements:
- First row contains column headers (unless `hasHeaders` is false)
- Values can be referenced in scenarios using `${column_name}` syntax
- File encoding should be UTF-8
- Standard CSV format with comma separators

## Configuration

### Default Settings
Configure default CSV behavior in `config/volttest.php`:

```php
'csv_data' => [
    'path' => storage_path('volttest/data'),  // Default CSV location
    'validate_files' => true,                 // Check file exists before run
    'default_distribution' => 'unique',       // Default distribution mode
    'default_headers' => true,                // Default header setting
],
```

### File Location
- **Relative paths**: Resolved relative to `csv_data.path` config
- **Absolute paths**: Used as-is
- **Recommended structure**:
  ```
  storage/
    volttest/
      data/
        users.csv
        products.csv
        orders.csv
  ```

## Distribution Modes

### Unique (Recommended)
Each virtual user gets a different CSV row:
```php
$scenario->dataSource('users.csv', 'unique');
```
- **Best for**: Login scenarios, user-specific data
- **Behavior**: VU1 gets row 1, VU2 gets row 2, etc.
- **Limitation**: Cannot have more VUs than CSV rows

### Random
Each virtual user gets a random CSV row:
```php
$scenario->dataSource('users.csv', 'random');
```
- **Best for**: Product browsing, general data variety
- **Behavior**: Each VU randomly selects a row
- **Advantage**: Works with any number of VUs

### Sequential
Virtual users get CSV rows in order, cycling if needed:
```php
$scenario->dataSource('users.csv', 'sequential');
```
- **Best for**: Predictable test patterns
- **Behavior**: VU1→row1, VU2→row2, VU3→row3, VU4→row1...
- **Advantage**: Predictable, works with any number of VUs

## Complete Examples

### User Authentication Test
```php
// CSV: storage/volttest/data/users.csv
// email,password,user_id,role
// admin@test.com,admin123,1,admin
// user1@test.com,pass123,2,user
// user2@test.com,pass456,3,user

$scenario = $manager->scenario('User Authentication')
    ->dataSource('users.csv', 'unique', true);

$scenario->step('Login')
    ->post('/api/login', [
        'email' => '${email}',
        'password' => '${password}'
    ])
    ->expectStatus(200)
    ->extractJson('auth_token', '$.token');

$scenario->step('Access Dashboard')
    ->get('/dashboard')
    ->header('Authorization', 'Bearer ${auth_token}')
    ->expectStatus(200);
```

### E-commerce Product Test
```php
// CSV: storage/volttest/data/products.csv
// product_id,sku,name,price
// 1,SKU001,Laptop,999.99
// 2,SKU002,Mouse,29.99
// 3,SKU003,Keyboard,79.99

$scenario = $manager->scenario('Product Browsing')
    ->dataSource('products.csv', 'random', true);

$scenario->step('View Product')
    ->get('/products/${product_id}')
    ->expectStatus(200)
    ->extractHtml('csrf_token', 'input[name="_token"]', 'value');

$scenario->step('Add to Cart')
    ->post('/cart/add', [
        '_token' => '${csrf_token}',
        'product_id' => '${product_id}',
        'quantity' => '1'
    ])
    ->expectStatus(302);
```

### Order Processing Test
```php
// CSV: storage/volttest/data/orders.csv
// customer_id,product_ids,shipping_address,payment_method
// 101,"1,2,3","123 Main St, City","credit_card"
// 102,"2,4","456 Oak Ave, Town","paypal"

$scenario = $manager->scenario('Order Processing')
    ->dataSource('orders.csv', 'sequential', true);

$scenario->step('Create Order')
    ->post('/orders', [
        'customer_id' => '${customer_id}',
        'products' => '${product_ids}',
        'shipping' => '${shipping_address}',
        'payment' => '${payment_method}'
    ])
    ->expectStatus(201)
    ->extractJson('order_id', '$.id');

$scenario->step('Confirm Order')
    ->patch('/orders/${order_id}/confirm')
    ->expectStatus(200);
```

## Advanced Usage

### Multiple Data Sources
Each scenario can have only one data source, but you can create multiple scenarios:

```php
$userScenario = $manager->scenario('User Actions')
    ->dataSource('users.csv', 'unique');

$productScenario = $manager->scenario('Product Admin')
    ->dataSource('products.csv', 'random');
```

### Conditional File Validation
Disable validation for optional CSV files:

```php
// In config/volttest.php
'csv_data' => [
    'validate_files' => false,  // Don't validate files at startup
],

// In your test
$scenario->dataSource('optional-data.csv', 'unique', true);
```

### Custom File Paths
Use absolute paths for CSV files outside the default location:

```php
$scenario->dataSource('/path/to/external/data.csv', 'unique', true);
$scenario->dataSource(base_path('tests/fixtures/test-data.csv'), 'random');
```

## Best Practices

1. **File Organization**: Keep CSV files organized by test type
2. **Data Variety**: Include diverse, realistic test data
3. **File Size**: Balance between data variety and file size
4. **Distribution Choice**: Use 'unique' for user-specific data, 'random' for general browsing
5. **Header Names**: Use clear, descriptive column names
6. **Data Validation**: Test with both valid and edge-case data

## Troubleshooting

### Common Errors

**File not found**: 
```
CSV data source file '/path/to/file.csv' does not exist
```
- Check file path and permissions
- Verify `csv_data.path` configuration
- Ensure file exists before test execution

**Invalid distribution mode**:
```
Invalid data source mode. Use "sequential", "random" or "unique"
```
- Use only supported distribution modes: 'unique', 'random', 'sequential'

**Data source already set**:
```
Data source configuration already set
```
- Each scenario can only have one data source
- Create separate scenarios for different data sources

### Performance Considerations

- **File Size**: Large CSV files may impact test startup time
- **VU Count**: With 'unique' mode, ensure CSV has enough rows for all virtual users
- **Memory Usage**: Consider splitting very large datasets into smaller files
