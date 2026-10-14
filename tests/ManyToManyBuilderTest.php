<?php

declare(strict_types=1);

use Laf\Database\ManyToManyBuilder;
use Laf\Database\Table;
use Laf\UI\Form\Input\ManyToManyAutocomplete;
use Laf\UI\Form\Input\ManyToManyDualList;
use LafShell\DummyTable;
use LafShell\M2mGroup;
use LafShell\M2mPerson;
use PHPUnit\Framework\TestCase;

/**
 * The fluent many-to-many declaration ($model->belongsToMany()), without a database: the LafShell models
 * m2m_person ⇄ m2m_group through m2m_person_group, whose Base classes the generator wrote from the
 * linker table marked {"linker": true}.
 */
class ManyToManyBuilderTest extends TestCase
{
    private const RELATION = 'm2m_person_group';

    /** @test */
    public function the_generated_declaration_is_resolved_on_first_read(): void
    {
        $person = new M2mPerson();
        $this->assertSame(
            ['owner' => 'person_id', 'other' => 'group_id', 'table' => 'm2m_group', 'label' => 'M2m Groups', 'form' => []],
            $person->getTable()->getManyToManyDefinition(self::RELATION)
        );
        $this->assertSame('person_id', (new M2mGroup())->getTable()->getManyToManyDefinition(self::RELATION)['other'], 'the other side reads it in reverse');
    }

    /** @test */
    public function the_linker_table_and_its_columns_are_inferred(): void
    {
        $person = new M2mPerson();
        $person->getTable()->removeManyToMany(self::RELATION);
        // the linker is not called m2m_person_m2m_group / m2m_group_m2m_person, so it is named; its columns are read
        // from the linker model's foreign keys
        $person->belongsToMany(M2mGroup::class)->through(self::RELATION);   // a model class or a table name

        $this->assertSame(
            ['owner' => 'person_id', 'other' => 'group_id', 'table' => 'm2m_group', 'label' => 'M2m Groups', 'form' => []],
            $person->getTable()->getManyToManyDefinition(self::RELATION)
        );

        // a declaration without through() finds a relation already declared for the other table
        $again = new M2mPerson();
        $again->belongsToMany('m2m_group')->hint('Pick');
        $this->assertSame('Pick', $again->getTable()->getManyToManyDefinition(self::RELATION)['hint']);
    }

    /** @test */
    public function a_declaration_refines_the_generated_one(): void
    {
        $person = new M2mPerson();
        $person->belongsToMany('m2m_group')->chips()->creatable()->max(2)->label('Groups')->required();

        $definition = $person->getTable()->getManyToManyDefinition(self::RELATION);
        $this->assertSame(['person_id', 'group_id', 'Groups', true, 2], [$definition['owner'], $definition['other'], $definition['label'], $definition['required'], $definition['max']]);
        $this->assertSame(['element' => 'Autocomplete', 'create' => true], $definition['form']);
        $this->assertCount(1, $person->getTable()->getManyToManyDefinitions(), 'refined, not added twice');

        $picker = $person->manyToMany('m2m_group')->getFormElement();   // by the other table's name
        $this->assertInstanceOf(ManyToManyAutocomplete::class, $picker);
        $this->assertSame(2, $picker->getMaxItems());
        $this->assertSame(self::RELATION, $person->manyToMany('m2m_group')->getName());
    }

    /** @test */
    public function every_option_has_a_method(): void
    {
        $table = new Table('m2m_person');
        $builder = (new ManyToManyBuilder('m2m_group', $table))
            ->through('m2m_person_group')->keys('person_id', 'group_id')
            ->label('Groups')->hint('Pick some')->required()->max(4)
            ->dualList()->creatable(false)->group('Access')->tab('Rights')->order(3)->form(['hidden' => ['view']]);

        $this->assertSame(self::RELATION, $builder->getName());
        $this->assertSame([
            'owner' => 'person_id', 'other' => 'group_id', 'table' => 'm2m_group',
            'label' => 'Groups', 'hint' => 'Pick some', 'required' => true, 'max' => 4,
            'form' => ['element' => 'DualList', 'create' => false, 'group' => 'Access', 'tab' => 'Rights', 'order' => 3, 'hidden' => ['view']],
        ], $builder->toArray());
        $this->assertSame(ManyToManyDualList::class, \Laf\Database\ManyToMany::resolveElementClass('dual'));
        $this->assertSame(ManyToManyAutocomplete::class, \Laf\Database\ManyToMany::resolveElementClass('chips'));
    }

    /** @test */
    public function a_missing_linker_table_is_named_in_the_error(): void
    {
        $row = new DummyTable();
        $row->belongsToMany('m2m_group');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("dummy_table ⇄ m2m_group: no linker table found (looked for the models of dummy_table_m2m_group and m2m_group_dummy_table); name it with ->through('…')");
        $row->getTable()->getManyToManyDefinitions();
    }

    /** @test */
    public function ambiguous_linker_columns_must_be_named(): void
    {
        $row = new DummyTable();
        $row->belongsToMany('m2m_group')->through('m2m_person_group');   // no foreign key to dummy_table
        try {
            $row->getTable()->getManyToManyDefinitions();
            $this->fail('expected a LogicException');
        } catch (\LogicException $ex) {
            $this->assertStringContainsString("cannot tell the linker columns apart (foreign keys to dummy_table: none; to m2m_group: group_id); name them with ->keys('…', '…')", $ex->getMessage());
        }
    }

    /** @test */
    public function an_array_definition_added_later_wins_over_the_generated_declaration(): void
    {
        $person = new M2mPerson();   // the Base class declared the relation; it is still pending
        $person->getTable()->addManyToMany(self::RELATION, ['owner' => 'person_id', 'other' => 'group_id', 'table' => 'm2m_group', 'label' => 'Assigned']);
        $this->assertSame('Assigned', $person->getTable()->getManyToManyDefinition(self::RELATION)['label']);
    }

    /** @test */
    public function a_relation_cannot_be_redeclared_for_another_table(): void
    {
        $person = new M2mPerson();
        $person->belongsToMany('dummy_table')->through(self::RELATION);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('m2m_person ⇄ dummy_table through m2m_person_group: the relation through m2m_person_group already links m2m_group');
        $person->getTable()->getManyToManyDefinitions();
    }

    /** @test */
    public function picker_aliases_work_in_layouts_too(): void
    {
        foreach (['chips' => ManyToManyAutocomplete::class, 'dual' => ManyToManyDualList::class, 'TransferList' => ManyToManyDualList::class] as $alias => $class) {
            $this->assertSame($class, \Laf\UI\Form\FormLayout::resolveElementClass($alias));
        }
        $this->assertSame(\Laf\UI\Form\Input\Autocomplete::class, \Laf\UI\Form\FormLayout::resolveElementClass('Autocomplete'), 'a field element name keeps its class');
        $this->expectException(\InvalidArgumentException::class);
        \Laf\UI\Form\FormLayout::resolveElementClass('NoSuchThing');
    }

    /** @test */
    public function a_clone_resolves_against_its_own_table(): void
    {
        $person = new M2mPerson();
        $person->belongsToMany('m2m_group')->chips();
        $copy = clone $person;
        $this->assertSame('Autocomplete', $copy->getTable()->getManyToManyDefinition(self::RELATION)['form']['element']);
        $this->assertNotSame($person->getTable(), $copy->getTable());
    }
}
