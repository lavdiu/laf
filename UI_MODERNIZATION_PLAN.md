# UI Component Modernization Plan

## Current State

The LAF framework has a **comprehensive UI system** already built:
- Form system with 17 input types
- Grid/Table with DataTables integration
- Component architecture with traits
- Bootstrap integration

**Problem**: The UI is tightly coupled to the old `BaseObject` class.

## Modernization Strategy

### Option 1: Bridge Pattern (Recommended - Fastest)

Create an adapter to make new models work with existing UI components.

#### Implementation

```php
// src/Laf/UI/Adapter/ModelAdapter.php
<?php

declare(strict_types=1);

namespace Laf\UI\Adapter;

use Laf\Database\BaseObject;
use Laf\Database\Table;
use Laf\Database\Field\Field;

/**
 * Adapts modern models to work with legacy UI components
 */
class ModelAdapter extends BaseObject
{
    private object $model;
    private array $metadata;
    
    public function __construct(object $model, array $metadata)
    {
        $this->model = $model;
        $this->metadata = $metadata;
        
        // Build Table from metadata
        $table = $this->buildTableFromMetadata($metadata);
        $this->setTable($table);
    }
    
    private function buildTableFromMetadata(array $metadata): Table
    {
        $table = new Table($metadata['table_name']);
        
        foreach ($metadata['columns'] as $columnMeta) {
            $field = new Field($columnMeta['name']);
            // Set field properties from metadata
            $table->addField($field);
        }
        
        return $table;
    }
    
    public function getField(string $name): Field
    {
        $field = parent::getField($name);
        // Sync value from modern model
        $property = $this->getPropertyName($name);
        if (property_exists($this->model, $property)) {
            $field->setValue($this->model->$property);
        }
        return $field;
    }
    
    public function insert(): void
    {
        // Delegate to repository
        // $this->repository->save($this->model);
    }
    
    public function update(): void
    {
        // Delegate to repository
        // $this->repository->save($this->model);
    }
    
    private function getPropertyName(string $columnName): string
    {
        return lcfirst(str_replace('_', '', ucwords($columnName, '_')));
    }
}
```

#### Usage

```php
// Create adapter
$userMetadata = $inspector->inspectTable('users');
$user = new User(name: 'John', email: 'john@example.com');
$adapter = new ModelAdapter($user, $userMetadata->toArray());

// Use with existing Form
$form = new Form($adapter, '/save-user');
$form->addFieldsFromObject();
echo $form->draw();

// Process form
if ($form->isSubmitted()) {
    $form->processForm();
}
```

**Pros:**
- ✅ Keep existing UI components
- ✅ Minimal changes needed
- ✅ Works immediately
- ✅ Gradual migration possible

**Cons:**
- ⚠️ Still some coupling
- ⚠️ Adapter overhead

---

### Option 2: Modernize UI Components (Long-term)

Refactor UI components to use dependency injection and work with any model.

#### New Form Builder

```php
// src/Laf/UI/Modern/FormBuilder.php
<?php

declare(strict_types=1);

namespace Laf\UI\Modern;

use Laf\Schema\Metadata\TableMetadata;

class FormBuilder
{
    public function __construct(
        private readonly FormRendererInterface $renderer
    ) {}
    
    public function buildFromModel(object $model, TableMetadata $metadata): FormInterface
    {
        $form = new ModernForm($metadata->name);
        
        foreach ($metadata->columns as $column) {
            $field = $this->createField($column, $model);
            $form->addField($field);
        }
        
        return $form;
    }
    
    private function createField(ColumnMetadata $column, object $model): FieldInterface
    {
        $fieldType = match(true) {
            $column->isString() => TextField::class,
            $column->isInteger() => NumberField::class,
            $column->isDate() => DateField::class,
            $column->isBoolean() => CheckboxField::class,
            default => TextField::class,
        };
        
        $property = $column->getPropertyName();
        $value = $model->$property ?? null;
        
        return new $fieldType(
            name: $column->name,
            label: $column->comment ?? ucfirst($column->name),
            value: $value,
            required: !$column->nullable
        );
    }
}
```

#### Usage

```php
$formBuilder = new FormBuilder(new HtmlRenderer());
$form = $formBuilder->buildFromModel($user, $userMetadata);

echo $form->render();
```

**Pros:**
- ✅ Clean architecture
- ✅ No coupling
- ✅ Testable
- ✅ Modern patterns

**Cons:**
- ⚠️ More work required
- ⚠️ Need to rewrite UI components
- ⚠️ Breaking changes

---

### Option 3: Hybrid Approach (Best of Both)

1. **Short-term**: Use bridge pattern for immediate compatibility
2. **Long-term**: Gradually modernize UI components

#### Phase 1: Bridge (Now)
```php
// Use adapter for existing UI
$adapter = new ModelAdapter($user, $metadata);
$form = new Form($adapter);
```

#### Phase 2: New Components (Later)
```php
// New modern form builder
$form = FormBuilder::create($user, $metadata);
```

#### Phase 3: Deprecation (Future)
```php
// Mark old components as deprecated
// Migrate all code to new components
// Remove old components
```

---

## Recommended Implementation

### Step 1: Create Model Adapter

```php
namespace Laf\UI\Adapter;

class ModelAdapter extends BaseObject
{
    // Adapts new models to work with existing UI
}
```

### Step 2: Create Helper Function

```php
function createFormFromModel(object $model, TableMetadata $metadata): Form
{
    $adapter = new ModelAdapter($model, $metadata);
    return new Form($adapter);
}
```

### Step 3: Update Generator

Add UI generation to `ModelGenerator`:

```php
public function generateFormUsage(TableMetadata $table): string
{
    $className = $table->getClassName();
    
    return <<<PHP
// Using existing UI with adapter
\$user = new {$className}(...);
\$metadata = \$inspector->inspectTable('{$table->name}');
\$adapter = new ModelAdapter(\$user, \$metadata->toArray());
\$form = new Form(\$adapter, '/save');
\$form->addFieldsFromObject();
echo \$form->draw();
PHP;
}
```

---

## Decision Matrix

| Approach | Time | Compatibility | Modern | Recommended |
|----------|------|---------------|--------|-------------|
| Bridge Pattern | 1-2 days | ✅ 100% | ⚠️ Medium | ✅ Yes (Now) |
| Modernize UI | 2-3 weeks | ❌ Breaking | ✅ Full | ⚠️ Later |
| Hybrid | Gradual | ✅ Both | ✅ Full | ✅ Best |

---

## Next Steps

1. **Implement `ModelAdapter`** - Bridge new models to old UI (2 hours)
2. **Test with existing forms** - Verify compatibility (1 hour)
3. **Document usage** - Add examples (1 hour)
4. **Plan UI modernization** - Long-term roadmap (optional)

---

## Example: Complete Integration

```php
<?php
// bootstrap.php - Setup
$container = new Container();
$container->singleton(ConnectionInterface::class, Connection::class);
$container->singleton(SchemaInspectorInterface::class, MySQLSchemaInspector::class);

// Generate models (one-time)
$inspector = $container->get(SchemaInspectorInterface::class);
$generator = new ModelGenerator('App\\Models');
$metadata = $inspector->inspectTable('users');
file_put_contents('Models/User.php', $generator->generate($metadata));

// Use in application
$user = new User(name: 'John', email: 'john@example.com');

// Option A: Use adapter with existing UI
$adapter = new ModelAdapter($user, $metadata->toArray());
$form = new Form($adapter, '/users/save');
$form->addFieldsFromObject();

if ($form->isSubmitted()) {
    $form->processForm();
    // Sync back to model and save via repository
    $userRepo->save($user);
}

echo $form->draw();
```

---

## Conclusion

**Recommended**: Implement the **Bridge Pattern** (Option 1) immediately to get your existing UI working with new models, then plan UI modernization for the future.

This gives you:
- ✅ Immediate compatibility
- ✅ Keep existing UI investment
- ✅ Path to modernization
- ✅ No breaking changes

**Estimated Time**: 4-6 hours for full bridge implementation
