<?php

declare(strict_types=1);

use Laf\Database\ManyToMany;
use Laf\Database\Table;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Form;
use Laf\UI\Form\FormLayout;
use Laf\UI\Form\Input\Autocomplete;
use Laf\UI\Form\Input\CheckboxCollection;
use Laf\UI\Form\Input\ManyToManyAutocomplete;
use Laf\UI\Form\Input\ManyToManyCheckboxes;
use Laf\UI\Form\Input\ManyToManyDualList;
use Laf\UI\Form\Input\Select;
use Laf\Util\Settings;
use LafShell\DummyTable;
use LafShell\M2mPerson;
use PHPUnit\Framework\TestCase;

/**
 * Many-to-many pickers through a linker table (m2m_person ⇄ m2m_group through m2m_person_group,
 * shaped like asm's person_group), the relation helpers, the form and layout wiring, and the inline
 * "create new" request, without a database: options are static and the owner is new.
 */
class ManyToManyTest extends TestCase
{
    private const RELATION = 'm2m_person_group';

    protected function setUp(): void
    {
        Autocomplete::resetRequestState();
        ManyToMany::resetRequestState();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Settings::remove('form.select_autocomplete_threshold');
        Autocomplete::resetRequestState();
        ManyToMany::resetRequestState();
        unset($_SERVER['REQUEST_METHOD']);
    }

    /** A new person's insert form with the groups picker over static options */
    private function personForm(?FormLayout $layout = null): array
    {
        $person = new M2mPerson();
        $form = $person->getForm(DrawMode::INSERT, $layout);
        $picker = $form->getRelationElement(self::RELATION);
        if ($picker !== null) {
            $picker->setOptions(['1' => 'Admins', '2' => 'Staff <b>', '3' => 'Guests']);
        }
        return [$person, $form, $picker];
    }

    /** @test */
    public function the_generated_definition_describes_the_linker_table()
    {
        $table = (new M2mPerson())->getTable();
        $this->assertTrue($table->hasManyToMany(self::RELATION));
        $this->assertSame(
            ['owner' => 'person_id', 'other' => 'group_id', 'table' => 'm2m_group', 'label' => 'M2m Groups', 'form' => []],
            $table->getManyToManyDefinition(self::RELATION)
        );

        $relation = (new M2mPerson())->manyToMany(self::RELATION);
        $this->assertTrue($relation->isAvailable());
        $this->assertSame('\\LafShell\\M2mPersonGroup', $relation->getJunctionClass());
        $this->assertSame('m2m_group', $relation->otherMeta()->getName());
        $this->assertSame('id', $relation->getOtherKeyColumn());
        $this->assertSame([], $relation->ids(), 'a new person has no links and reads nothing');

        $this->expectException(\InvalidArgumentException::class);
        (new M2mPerson())->manyToMany('unknown_table');
    }

    /** @test */
    public function a_definition_needs_both_columns()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Table('person'))->addManyToMany('person_group', ['owner' => 'person_id']);
    }

    /** @test */
    public function diff_and_clean_ids()
    {
        $this->assertSame(['added' => ['9'], 'removed' => ['7']], ManyToMany::diff([3, '7'], ['3', 9, 9, '']));
        $this->assertSame(['added' => [], 'removed' => []], ManyToMany::diff(['1', '2'], [2, 1]));
        $this->assertSame(['5', 'a', '1'], ManyToMany::cleanIds([' 5 ', 'a', null, ['x'], 5, true, '']));
    }

    /** @test */
    public function picker_classes_resolve_from_the_single_value_names()
    {
        $this->assertSame(ManyToManyAutocomplete::class, ManyToMany::resolveElementClass('Autocomplete'));
        $this->assertSame(ManyToManyAutocomplete::class, ManyToMany::resolveElementClass(Autocomplete::class));
        $this->assertSame(ManyToManyCheckboxes::class, ManyToMany::resolveElementClass('CheckboxCollection'));
        $this->assertSame(ManyToManyCheckboxes::class, ManyToMany::resolveElementClass(ManyToManyCheckboxes::class));
        $this->expectException(\InvalidArgumentException::class);
        ManyToMany::resolveElementClass('TextArea');
    }

    /** @test */
    public function the_picker_appears_on_the_generated_form_by_itself()
    {
        [, $form, $picker] = $this->personForm();
        $this->assertInstanceOf(ManyToManyCheckboxes::class, $picker, 'checkboxes while the threshold is off');
        $this->assertNull($picker->getField(), 'bound to the relation, not to a column');
        $this->assertSame(self::RELATION, $picker->getRelationName());
        $this->assertSame('M2m Groups', $picker->getLabel());
        $this->assertContains('name', $form->getRenderedFieldNames(), 'the columns are still there');
        $this->assertNotContains(self::RELATION, $form->getRenderedFieldNames());

        $html = $form->draw();
        $this->assertStringContainsString("name='m2m_person_group[]' value=''", $html, 'an empty value first, so unticking all is posted');
        $this->assertSame(3, substr_count($html, "type='checkbox'"));
        $this->assertStringContainsString('Staff &lt;b&gt;', $html);
        preg_match("/name='" . preg_quote($form->getFieldListInputName(), '/') . "' value='([^']*)'/", $html, $m);
        $this->assertStringContainsString(Form::RELATION_KEY_PREFIX . self::RELATION, $m[1], 'in the signed field list');
    }

    /** @test */
    public function layouts_place_hide_or_exclude_the_picker_by_name()
    {
        $layout = FormLayout::create()->fieldset('Membership', [self::RELATION])->fields(['name']);
        [, $form] = $this->personForm($layout);
        $components = $form->getComponents();
        $this->assertInstanceOf(\Laf\UI\Container\Fieldset::class, $components[0]);
        $this->assertInstanceOf(ManyToManyCheckboxes::class, $components[0]->getComponents()[0]);

        [, $excluded] = $this->personForm(FormLayout::auto((new M2mPerson())->getTable())->exclude([self::RELATION]));
        $this->assertNull($excluded->getRelationElement(self::RELATION));

        [, $readOnly, $picker] = $this->personForm(FormLayout::auto((new M2mPerson())->getTable())->readOnly([self::RELATION]));
        $this->assertSame(DrawMode::VIEW, $picker->getDrawMode());
        $this->assertStringNotContainsString(Form::RELATION_KEY_PREFIX, (string)$readOnly->draw(), 'a read-only picker posts nothing');
    }

    /** @test */
    public function definition_hints_order_and_exclude_the_picker_in_the_auto_layout()
    {
        $person = new M2mPerson();
        $person->getTable()->addManyToMany(self::RELATION, [
            'owner' => 'person_id', 'other' => 'group_id', 'table' => 'm2m_group', 'label' => 'Groups',
            'form' => ['tab' => 'Access', 'exclude' => [DrawMode::VIEW]],
        ]);
        $layout = FormLayout::auto($person->getTable());
        $tabs = array_values(array_filter($layout->getSections(DrawMode::UPDATE), fn(array $s) => $s['kind'] === FormLayout::KIND_TAB));
        $this->assertSame('Access', $tabs[0]['title']);
        $this->assertSame([self::RELATION], $tabs[0]['fields']);
        $this->assertTrue($layout->isExcluded(self::RELATION, DrawMode::VIEW));
        $this->assertFalse($layout->isExcluded(self::RELATION, DrawMode::UPDATE));
    }

    /** @test */
    public function a_submission_reads_the_posted_keys_and_checks_them()
    {
        [, $form, $picker] = $this->personForm();
        $form->setRequest($form->buildRequest(['name' => 'Ann', self::RELATION => ['2', '1', '2', '']], DrawMode::INSERT));
        $form->applySubmittedValues();
        $this->assertSame(['2', '1'], $picker->getSelectedIds(), 'deduplicated, in the posted order');

        [, $form, $picker] = $this->personForm();
        $picker->setRequired(true);
        $form->setRequest($form->buildRequest(['name' => 'Ann', self::RELATION => []], DrawMode::INSERT));
        $form->applySubmittedValues();
        $errors = $form->validate();
        $this->assertSame(['M2m Groups: choose at least one'], $errors[self::RELATION]);
        $this->assertSame(['M2m Groups: choose at least one'], $picker->getErrors(), 'handed to the picker');
        $this->assertStringContainsString('M2m Groups: choose at least one', (string)$form->draw());

        [, $form] = $this->personForm();
        $form->setRequest($form->buildRequest(['name' => 'Ann'], DrawMode::INSERT));
        $form->applySubmittedValues();
        $this->assertArrayNotHasKey(self::RELATION, $form->validate(), 'a picker the request does not carry is left alone');
    }

    /** @test */
    public function a_picker_outside_the_signed_list_is_ignored()
    {
        [, $form, $picker] = $this->personForm();
        $request = $form->buildRequest(['name' => 'Ann'], DrawMode::INSERT);
        $request[self::RELATION] = ['1'];
        $form->setRequest($request)->applySubmittedValues();
        $this->assertSame([], $picker->getSelectedIds());
    }

    /** @test */
    public function the_chips_picker_draws_one_chip_per_key()
    {
        $relation = (new M2mPerson())->manyToMany(self::RELATION);
        /** @var ManyToManyAutocomplete $picker */
        $picker = $relation->getFormElement('Autocomplete');
        $this->assertInstanceOf(ManyToManyAutocomplete::class, $picker);
        $this->assertSame($picker, $relation->getFormElement(), 'cached');
        $picker->setOptions(['1' => 'Admins', '2' => 'Staff <b>'])->setSelectedIds(['2', '1'])->setMaxItems(3)->setDrawMode(DrawMode::UPDATE);
        $html = (string)$picker->draw();

        $this->assertSame('m2m_person_group.group_id', $picker->getLookupKey());
        $this->assertStringContainsString('data-multiple="1"', $html);
        $this->assertStringContainsString('data-max-items="3"', $html);
        $this->assertStringContainsString('<input type="hidden" class="laf-autocomplete-empty" name="m2m_person_group[]" value=""', $html);
        $this->assertSame(2, substr_count($html, 'class="badge bg-secondary laf-autocomplete-chip'));
        $this->assertStringContainsString('Staff &lt;b&gt;', $html);
        $this->assertStringContainsString('aria-label="Remove Staff &lt;b&gt;"', $html);
        $this->assertLessThan(strpos($html, 'value="1"'), strpos($html, 'value="2"'), 'chips in the chosen order');

        $picker->setDrawMode(DrawMode::VIEW);
        $this->assertStringContainsString('Staff &lt;b&gt;, Admins', (string)$picker->draw());

        Settings::set('form.select_autocomplete_threshold', 5);
        $this->assertInstanceOf(ManyToManyAutocomplete::class, $picker, 'the threshold picks the class of a new picker only');
    }

    /** @test */
    public function the_dual_list_shows_available_and_selected_rows()
    {
        $this->assertSame(ManyToManyDualList::class, ManyToMany::resolveElementClass('DualList'));
        $this->assertSame(ManyToManyDualList::class, ManyToMany::resolveElementClass('TransferList'));
        $this->assertSame(ManyToManyDualList::class, FormLayout::resolveElementClass('DualList'), 'usable in comments and layouts');

        [, $form] = $this->personForm(FormLayout::auto((new M2mPerson())->getTable())->element(self::RELATION, 'DualList'));
        /** @var ManyToManyDualList $picker */
        $picker = $form->getRelationElement(self::RELATION);
        $this->assertInstanceOf(ManyToManyDualList::class, $picker);
        $picker->setOptions(['1' => 'Admins', '2' => 'Staff <b>', '3' => 'Guests'])
            ->setSelectedIds(['3', '1'])
            ->setAllowCreate(true)
            ->setTexts(['available' => 'Groups to pick', 'unknown' => 'ignored']);
        $html = $form->draw();

        preg_match('/laf-duallist-available">(.*?)<\/select>/s', $html, $available);
        preg_match('/laf-duallist-selected"[^>]*>(.*?)<\/select>/s', $html, $selected);
        $this->assertStringContainsString('Staff &lt;b&gt;', $available[1]);
        $this->assertStringNotContainsString('Admins', $available[1], 'a chosen row is only on the selected side');
        $this->assertLessThan(strpos($selected[1], 'Admins'), strpos($selected[1], 'Guests'), 'selected rows keep the chosen order');
        $this->assertStringContainsString('<input type="hidden" name="m2m_person_group[]" value="3" />', $html);
        $this->assertStringContainsString('<input type="hidden" class="laf-duallist-empty" name="m2m_person_group[]" value=""', $html);
        $this->assertStringContainsString('Groups to pick', $html);
        $this->assertStringContainsString('laf-duallist-new', $html, 'the new-entry box when creating is allowed');
        $this->assertStringContainsString('data-csrf-token="' . $form->getCsrfToken() . '"', $html);
        $this->assertArrayNotHasKey('unknown', $picker->getTexts());

        $picker->setDrawMode(DrawMode::VIEW);
        $this->assertStringContainsString('Guests, Admins', (string)$picker->draw());
    }

    /** @test */
    public function a_foreign_key_stays_single()
    {
        $row = new DummyTable();
        $field = $row->getParentIdFld();
        $field->setFormElementClass('Select');
        /** @var Select $select */
        $select = $field->getFormElement();
        $select->setMultiple(true)->setOptions(['1' => 'One', '2' => 'Two']);
        $this->assertFalse($select->isMultiple());
        $this->assertStringNotContainsString('[]', (string)$select->setDrawMode(DrawMode::UPDATE)->draw());

        $field->setFormElementClass('CheckboxCollection');
        /** @var CheckboxCollection $list */
        $list = $field->getFormElement();
        $list->setOptions(['1' => 'One', '2' => 'Two']);
        $html = (string)$list->setDrawMode(DrawMode::UPDATE)->draw();
        $this->assertStringContainsString("type='radio'", $html, 'radios: one key');
        $this->assertStringNotContainsString("type='checkbox'", $html);
    }

    /* ───────────── create ───────────── */

    /** DummyTable.parent_id as an autocomplete that creates rows through a callback */
    private function creatingForm(callable $callback): array
    {
        $row = new DummyTable();
        $field = $row->getParentIdFld();
        $field->setFormElementClass('Autocomplete');
        /** @var Autocomplete $element */
        $element = $field->getFormElement();
        $element->setOptions(['1' => 'Alpha'])->setCreateCallback($callback);
        // the link_table picker (dummy_table ⇄ code_table) would read its choices from the database
        return [$row->getForm(DrawMode::UPDATE, FormLayout::auto($row->getTable())->exclude(['link_table'])), $element];
    }

    private function create(Form $form, array $post, string $key = 'dummy_table.parent_id'): array
    {
        ob_start();
        $form->handleLookupRequest(['laf_lookup' => $key, 'laf_create' => '1'], false, $post);
        $body = json_decode((string)ob_get_clean(), true);
        return [$form->getLookupStatus(), $body];
    }

    /** @test */
    public function the_create_entry_is_offered_with_the_forms_token()
    {
        [$form, $element] = $this->creatingForm(fn() => ['value' => 9, 'label' => 'New']);
        $this->assertTrue($element->isCreateAllowed());
        $html = $form->draw();
        $this->assertStringContainsString('data-create="1"', $html);
        $this->assertStringContainsString('data-create-text="Create &quot;%s&quot;"', $html);
        $this->assertStringContainsString('data-csrf-name="' . $form->getCsrfSessionKey() . '"', $html);
        $this->assertStringContainsString('data-csrf-token="' . $form->getCsrfToken() . '"', $html);

        $plain = (new Autocomplete())->setOptions(['1' => 'A']);
        $this->assertFalse($plain->isCreateAllowed(), 'off by default');
    }

    /** @test */
    public function a_create_request_needs_post_a_valid_token_and_an_editable_field()
    {
        $seen = null;
        [$form] = $this->creatingForm(function (string $term) use (&$seen) {
            $seen = $term;
            return ['value' => 9, 'label' => 'Gamma'];
        });
        $token = $form->getCsrfToken();
        $tokenName = $form->getCsrfSessionKey();

        $this->assertSame([403, 'The form is out of date; reload the page and try again'], [$this->create($form, ['laf_q' => 'x'])[0], $this->create($form, ['laf_q' => 'x'])[1]['error']]);
        $this->assertSame(404, $this->create($form, ['laf_q' => 'x', $tokenName => $token], 'user.password')[0]);
        $this->assertSame([200, ['value' => '9', 'label' => 'Gamma', 'created' => true]], $this->create($form, ['laf_q' => '  Gamma  ', $tokenName => $token]));
        $this->assertSame('Gamma', $seen, 'trimmed');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        $form->handleLookupRequest(['laf_lookup' => 'dummy_table.parent_id', 'laf_create' => '1'], false);
        ob_end_clean();
        $this->assertSame(405, $form->getLookupStatus());

        $viewed = new DummyTable();
        $viewed->getParentIdFld()->setFormElementClass('Autocomplete');
        $viewed->getParentIdFld()->getFormElement()->setCreateCallback(fn() => ['value' => 1, 'label' => 'x']);
        $view = $viewed->getForm(DrawMode::VIEW);
        $this->assertSame(403, $this->create($view, ['laf_q' => 'x', $view->getCsrfSessionKey() => $view->getCsrfToken()])[0], 'never from a view form');
    }

    /** @test */
    public function a_refused_create_explains_why()
    {
        [$form] = $this->creatingForm(fn(string $term) => $term === 'bad' ? 'Names need two words' : null);
        $post = [$form->getCsrfSessionKey() => $form->getCsrfToken()];
        $this->assertSame([422, ['error' => 'Names need two words']], $this->create($form, $post + ['laf_q' => 'bad']));
        $this->assertSame([422, ['error' => '"other" cannot be added here']], $this->create($form, $post + ['laf_q' => 'other']));
        $this->assertSame([422, ['error' => 'Type a name first']], $this->create($form, $post + ['laf_q' => '   ']));
    }

    /** @test */
    public function the_relation_picker_answers_lookups_and_creates()
    {
        [, $form] = $this->personForm(FormLayout::auto((new M2mPerson())->getTable())->element(self::RELATION, 'Autocomplete'));
        /** @var ManyToManyAutocomplete $picker */
        $picker = $form->getRelationElement(self::RELATION);
        $this->assertInstanceOf(ManyToManyAutocomplete::class, $picker);
        $picker->setOptions(['1' => 'Admins', '2' => 'Staff', '3' => 'Guests'])
            ->setCreateCallback(fn(string $term) => ['value' => 4, 'label' => $term]);

        ob_start();
        $form->handleLookupRequest(['laf_lookup' => 'm2m_person_group.group_id', 'laf_q' => 'st'], false);
        $this->assertSame(['results' => [['value' => '2', 'label' => 'Staff'], ['value' => '3', 'label' => 'Guests']], 'more' => false], json_decode((string)ob_get_clean(), true));

        $answer = $this->create($form, ['laf_q' => 'Drivers', $form->getCsrfSessionKey() => $form->getCsrfToken()], 'm2m_person_group.group_id');
        $this->assertSame([200, ['value' => '4', 'label' => 'Drivers', 'created' => true]], $answer);
    }
}
