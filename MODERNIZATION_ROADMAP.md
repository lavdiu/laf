# LAF Framework Modernization Roadmap

## Overview
Complete modernization of the LAF framework to PHP 8.4 with dependency injection, modern patterns, and reduced coupling.

## Architecture Principles

### 1. Dependency Injection First
- All dependencies injected via constructor
- PSR-11 compliant DI container
- No static calls or singletons
- Interface-based programming

### 2. SOLID Principles
- Single Responsibility: Each class has one reason to change
- Open/Closed: Extend via interfaces, not modification
- Liskov Substitution: Proper inheritance hierarchies
- Interface Segregation: Small, focused interfaces
- Dependency Inversion: Depend on abstractions

### 3. Modern PHP 8.4 Features
- Readonly properties
- Property hooks
- Enums for constants
- Attributes for metadata
- Constructor property promotion
- Named arguments
- Union and intersection types

## Component Architecture

### Core Layer (`src/Laf/Core/`)
```
Core/
├── Container/
│   ├── ContainerInterface.php
│   ├── Container.php
│   └── ServiceProvider.php
├── Database/
│   ├── ConnectionInterface.php
│   ├── Connection.php
│   ├── ConnectionPool.php
│   ├── QueryExecutorInterface.php
│   └── QueryExecutor.php
└── Config/
    ├── ConfigInterface.php
    └── Config.php
```

### Schema Layer (`src/Laf/Schema/`)
```
Schema/
├── Inspector/
│   ├── SchemaInspectorInterface.php
│   ├── AbstractSchemaInspector.php
│   ├── MySQLSchemaInspector.php
│   └── PostgreSQLSchemaInspector.php
├── Metadata/
│   ├── TableMetadata.php
│   ├── ColumnMetadata.php
│   ├── IndexMetadata.php
│   ├── ForeignKeyMetadata.php
│   └── RelationshipMetadata.php
├── Relationship/
│   ├── RelationshipType.php (enum)
│   ├── RelationshipDetector.php
│   └── RelationshipResolver.php
└── Cache/
    ├── SchemaCacheInterface.php
    └── SchemaCache.php
```

### Generator Layer (`src/Laf/Generator/`)
```
Generator/
├── Template/
│   ├── TemplateEngineInterface.php
│   ├── TemplateEngine.php
│   └── templates/
│       ├── model.php.tpl
│       ├── repository.php.tpl
│       ├── controller.php.tpl
│       └── view.php.tpl
├── CodeGenerator/
│   ├── ModelGenerator.php
│   ├── RepositoryGenerator.php
│   ├── ControllerGenerator.php
│   └── ViewGenerator.php
├── Writer/
│   ├── FileWriterInterface.php
│   └── FileWriter.php
└── Config/
    └── GeneratorConfig.php
```

### Repository Layer (`src/Laf/Repository/`)
```
Repository/
├── RepositoryInterface.php
├── AbstractRepository.php
├── Specification/
│   ├── SpecificationInterface.php
│   ├── AbstractSpecification.php
│   └── Specifications/
│       ├── AndSpecification.php
│       ├── OrSpecification.php
│       └── NotSpecification.php
├── UnitOfWork/
│   ├── UnitOfWorkInterface.php
│   └── UnitOfWork.php
└── Query/
    ├── QueryBuilderInterface.php
    ├── QueryBuilder.php
    └── Criteria.php
```

### Model Layer (`src/Laf/Model/`)
```
Model/
├── ModelInterface.php
├── AbstractModel.php
├── Attributes/
│   ├── Table.php
│   ├── Column.php
│   ├── PrimaryKey.php
│   ├── ForeignKey.php
│   ├── Unique.php
│   └── Index.php
├── Traits/
│   ├── HasTimestamps.php
│   ├── SoftDeletes.php
│   └── HasAttributes.php
└── Events/
    ├── ModelEvent.php
    └── EventDispatcherInterface.php
```

### UI Layer (`src/Laf/UI/`)
```
UI/
├── Form/
│   ├── FormInterface.php
│   ├── Form.php
│   ├── FormBuilder.php
│   ├── Field/
│   │   ├── FieldInterface.php
│   │   ├── AbstractField.php
│   │   ├── TextField.php
│   │   ├── NumberField.php
│   │   ├── DateField.php
│   │   ├── SelectField.php
│   │   └── FileField.php
│   └── Validation/
│       ├── ValidatorInterface.php
│       ├── Validator.php
│       └── Rules/
├── Grid/
│   ├── GridInterface.php
│   ├── Grid.php
│   ├── GridBuilder.php
│   ├── Column/
│   │   ├── ColumnInterface.php
│   │   └── Column.php
│   ├── Filter/
│   │   ├── FilterInterface.php
│   │   └── Filter.php
│   └── Export/
│       ├── ExporterInterface.php
│       ├── CsvExporter.php
│       ├── ExcelExporter.php
│       └── JsonExporter.php
└── Component/
    ├── ComponentInterface.php
    ├── AbstractComponent.php
    └── Renderer/
        ├── RendererInterface.php
        └── HtmlRenderer.php
```

## Key Improvements

### 1. Database Connection
**Before:**
```php
$db = Db::getInstance(); // Singleton
$result = $db->query($sql);
```

**After:**
```php
class UserRepository {
    public function __construct(
        private readonly ConnectionInterface $connection
    ) {}
    
    public function find(int $id): ?User {
        return $this->connection->fetchOne(
            'SELECT * FROM users WHERE id = ?',
            [$id],
            User::class
        );
    }
}
```

### 2. Schema Inspection
**Before:**
```php
$inspector = new TableInspector('users');
$inspector->inspect();
$columns = $inspector->getColumns();
```

**After:**
```php
class SchemaService {
    public function __construct(
        private readonly SchemaInspectorInterface $inspector,
        private readonly SchemaCacheInterface $cache
    ) {}
    
    public function getTableMetadata(string $table): TableMetadata {
        return $this->cache->remember(
            "schema.{$table}",
            fn() => $this->inspector->inspectTable($table)
        );
    }
}
```

### 3. Model Generation
**Before:**
```php
$generator = new ClassGenerator($table, $config);
$generator->processBaseClass();
```

**After:**
```php
class GeneratorService {
    public function __construct(
        private readonly SchemaInspectorInterface $inspector,
        private readonly ModelGenerator $modelGenerator,
        private readonly RepositoryGenerator $repositoryGenerator,
        private readonly FileWriterInterface $writer
    ) {}
    
    public function generateForTable(string $table): GeneratedFiles {
        $metadata = $this->inspector->inspectTable($table);
        
        return new GeneratedFiles(
            model: $this->modelGenerator->generate($metadata),
            repository: $this->repositoryGenerator->generate($metadata),
            controller: $this->controllerGenerator->generate($metadata)
        );
    }
}
```

### 4. Model Usage
**Before:**
```php
$user = new UserController();
$user->select(1);
$user->setNameVal("John");
$user->insert();
```

**After:**
```php
// Using Repository Pattern
class UserService {
    public function __construct(
        private readonly UserRepository $repository,
        private readonly UnitOfWorkInterface $unitOfWork
    ) {}
    
    public function createUser(string $name, string $email): User {
        $user = new User(
            name: $name,
            email: $email
        );
        
        $this->repository->add($user);
        $this->unitOfWork->commit();
        
        return $user;
    }
}

// Direct usage with modern syntax
$user = new User(
    name: "John",
    email: "john@example.com"
);
$user->name = "Jane"; // Property hooks for validation
$repository->save($user);
```

### 5. Query Building
**Before:**
```php
$qb = User::getQueryBuilder();
$users = $qb->where('status', 'active')->get();
```

**After:**
```php
// Using Specification Pattern
$activeUsers = new AndSpecification(
    new StatusSpecification('active'),
    new CreatedAfterSpecification(new DateTime('-30 days'))
);

$users = $repository->findBySpecification($activeUsers);

// Or fluent query builder
$users = $repository->query()
    ->where('status', '=', 'active')
    ->where('created_at', '>', new DateTime('-30 days'))
    ->orderBy('name')
    ->limit(10)
    ->get();
```

### 6. Form Building
**Before:**
```php
$form = new Form();
$form->setObject($user);
$form->draw();
```

**After:**
```php
class UserFormBuilder {
    public function __construct(
        private readonly FormBuilder $builder
    ) {}
    
    public function build(?User $user = null): FormInterface {
        return $this->builder
            ->setModel($user ?? new User())
            ->addField('name', TextField::class, [
                'label' => 'Full Name',
                'required' => true,
                'maxLength' => 255
            ])
            ->addField('email', TextField::class, [
                'label' => 'Email',
                'required' => true,
                'validation' => ['email', 'unique:users,email']
            ])
            ->addField('role_id', SelectField::class, [
                'label' => 'Role',
                'options' => fn() => $this->roleRepository->all(),
                'searchable' => true
            ])
            ->build();
    }
}
```

## Migration Strategy

### Phase 1: Core Infrastructure (Week 1)
- [ ] Implement DI Container
- [ ] Create Connection abstraction
- [ ] Build Config system
- [ ] Setup PSR-4 autoloading

### Phase 2: Schema Layer (Week 2)
- [ ] Schema inspector interfaces
- [ ] MySQL implementation
- [ ] PostgreSQL implementation
- [ ] Relationship detection
- [ ] Schema caching

### Phase 3: Generator (Week 3)
- [ ] Template engine
- [ ] Model generator
- [ ] Repository generator
- [ ] Controller generator
- [ ] View generator

### Phase 4: Repository Pattern (Week 4)
- [ ] Repository interfaces
- [ ] Specification pattern
- [ ] Unit of Work
- [ ] Query builder
- [ ] Event system

### Phase 5: UI Components (Week 5)
- [ ] Form builder
- [ ] Field components
- [ ] Grid component
- [ ] Export functionality
- [ ] Validation system

### Phase 6: Testing & Documentation (Week 6)
- [ ] Unit tests
- [ ] Integration tests
- [ ] Documentation
- [ ] Examples
- [ ] Migration guide

## Breaking Changes

1. **No more static calls**: All `Db::getInstance()` replaced with DI
2. **No more singletons**: Use DI container for shared instances
3. **Namespace changes**: New namespace structure
4. **Method signatures**: Type hints everywhere
5. **Configuration**: New config format
6. **Generated code**: Different structure and patterns

## Benefits

1. **Testability**: Easy to mock dependencies
2. **Flexibility**: Swap implementations via DI
3. **Type Safety**: Full type coverage
4. **Performance**: Better caching and lazy loading
5. **Maintainability**: Clear separation of concerns
6. **Modern**: Uses latest PHP features
7. **Scalability**: Connection pooling, caching
8. **Developer Experience**: Better IDE support

## Next Steps

1. Review and approve architecture
2. Start implementation with Core layer
3. Iterative development with tests
4. Documentation alongside code
5. Example applications
6. Migration tools for existing apps
