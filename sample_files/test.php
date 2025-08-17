<?php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/config.php';

use Laf\Generator\TableInspector;
use Laf\UI\Grid\PhpGrid\PhpGrid;
use Laf\UI\Grid\PhpGrid\Column;
use Laf\UI\Grid\PhpGrid\ActionButton;

$ti = new TableInspector("person");
$ti->inspect();

$grid = new PhpGrid('business_partner_list');
$grid->setTitle('business_partner List')
    ->setRowsPerPage(20)
    ->setSqlQuery('
SELECT * FROM (
	SELECT
		  business_partner.id AS business_partner_id
		, business_partner.name AS business_partner_name
		, business_partner.business_partner_group_id AS business_partner_business_partner_group_id
		, business_partner_group.name AS business_partner_group_name
		, business_partner.business_partner_type_id AS business_partner_business_partner_type_id
		, business_partner_type.label AS business_partner_type_label
		, business_partner.industry_id AS business_partner_industry_id
		, industry.label AS industry_label
		, business_partner.hq_address_id AS business_partner_hq_address_id
		, address.address1 AS address_address1
		, business_partner.address_id AS business_partner_address_id
		, address_address_id.address1 AS address_address_id_address1
		, business_partner.record_status_id AS business_partner_record_status_id
		, record_status.label AS record_status_label
		, business_partner.notes AS business_partner_notes
		, business_partner.collector_id AS business_partner_collector_id
		, person.name AS person_name
		, business_partner.sales_agent_id AS business_partner_sales_agent_id
		, person_sales_agent_id.name AS person_sales_agent_id_name
		, business_partner.is_commissionable AS business_partner_is_commissionable
		, yes_or_no.label AS yes_or_no_label
		, business_partner.tax_id AS business_partner_tax_id
		, business_partner.website AS business_partner_website
		, business_partner.main_phone AS business_partner_main_phone
		, business_partner.currency_id AS business_partner_currency_id
		, currency.code AS currency_code
		, business_partner.payment_terms_id AS business_partner_payment_terms_id
		, payment_terms.label AS payment_terms_label
		, business_partner.created_on::date AS business_partner_created_on
		, business_partner.created_by AS business_partner_created_by
		, person_created_by.name AS person_created_by_name
		, business_partner.updated_on::date AS business_partner_updated_on
		, business_partner.updated_by AS business_partner_updated_by
		, person_updated_by.name AS person_updated_by_name
	FROM business_partner business_partner
	LEFT JOIN business_partner_group business_partner_group ON business_partner.business_partner_group_id = business_partner_group.id
	LEFT JOIN business_partner_type business_partner_type ON business_partner.business_partner_type_id = business_partner_type.id
	LEFT JOIN industry industry ON business_partner.industry_id = industry.id
	LEFT JOIN address address ON business_partner.hq_address_id = address.id
	LEFT JOIN address address_address_id ON business_partner.address_id = address_address_id.id
	LEFT JOIN record_status record_status ON business_partner.record_status_id = record_status.id
	LEFT JOIN person person ON business_partner.collector_id = person.id
	LEFT JOIN person person_sales_agent_id ON business_partner.sales_agent_id = person_sales_agent_id.id
	LEFT JOIN yes_or_no yes_or_no ON business_partner.is_commissionable = yes_or_no.id
	LEFT JOIN currency currency ON business_partner.currency_id = currency.id
	LEFT JOIN payment_terms payment_terms ON business_partner.payment_terms_id = payment_terms.id
	LEFT JOIN person person_created_by ON business_partner.created_by = person_created_by.id
	LEFT JOIN person person_updated_by ON business_partner.updated_by = person_updated_by.id
	WHERE 1=1 
)l1 ');

$grid->addColumn(((new Column('business_partner_id', 'Id', true, true, '?module=[module]&action=view&id={business_partner_id}'))->setInnerElementCssClass('btn btn-sm btn-outline-success'))->setOuterElementCssStyle('width:100px;'));
$grid->addColumn(new Column('business_partner_name', 'Name', true));
$grid->addColumn(new Column('business_partner_business_partner_group_id', 'Business Partner GroupId', false));
$grid->addColumn(new Column('business_partner_group_name', 'Business Partner Group', true));
$grid->addColumn(new Column('business_partner_business_partner_type_id', 'Business Partner TypeId', false));
$grid->addColumn(new Column('business_partner_type_label', 'Business Partner Type', true));
$grid->addColumn(new Column('business_partner_industry_id', 'IndustryId', false));
$grid->addColumn(new Column('industry_label', 'Industry', true));
$grid->addColumn(new Column('business_partner_hq_address_id', 'Hq AddressId', false));
$grid->addColumn(new Column('address_address1', 'Hq Address', true));
$grid->addColumn(new Column('business_partner_address_id', 'AddressId', false));
$grid->addColumn(new Column('address_address_id_address1', 'Address', true));
$grid->addColumn(new Column('business_partner_record_status_id', 'Record StatusId', false));
$grid->addColumn(new Column('record_status_label', 'Record Status', true));
$grid->addColumn(new Column('business_partner_notes', 'Notes', true));
$grid->addColumn(new Column('business_partner_collector_id', 'CollectorId', false));
$grid->addColumn(new Column('person_name', 'Collector', true));
$grid->addColumn(new Column('business_partner_sales_agent_id', 'Sales AgentId', false));
$grid->addColumn(new Column('person_sales_agent_id_name', 'Sales Agent', true));
$grid->addColumn(new Column('business_partner_is_commissionable', 'Is CommissionableId', false));
$grid->addColumn(new Column('yes_or_no_label', 'Is Commissionable', true));
$grid->addColumn(new Column('business_partner_tax_id', 'Tax', true));
$grid->addColumn(new Column('business_partner_website', 'Website', true));
$grid->addColumn(new Column('business_partner_main_phone', 'Main Phone', true));
$grid->addColumn(new Column('business_partner_currency_id', 'CurrencyId', false));
$grid->addColumn(new Column('currency_code', 'Currency', true));
$grid->addColumn(new Column('business_partner_payment_terms_id', 'Payment TermsId', false));
$grid->addColumn(new Column('payment_terms_label', 'Payment Terms', true));
$grid->addColumn(new Column('business_partner_created_on', 'Created On', true));
$grid->addColumn(new Column('business_partner_created_by', 'Created ById', false));
$grid->addColumn(new Column('person_created_by_name', 'Created By', true));
$grid->addColumn(new Column('business_partner_updated_on', 'Updated On', true));
$grid->addColumn(new Column('business_partner_updated_by', 'Updated ById', false));
$grid->addColumn(new Column('person_updated_by_name', 'Updated By', true));

$grid->addActionButton(new ActionButton('View', '?module=[module]&action=view&id={business_partner_id}', 'fa fa-eye'));
$grid->addActionButton(new ActionButton('Update', '?module=[module]&action=update&id={business_partner_id}', 'fa fa-edit'));
$deleteLink = new ActionButton('Delete', '?module=[module]&action=delete&id={business_partner_id}', 'fa fa-trash');

$deleteLink->addAttribute('onclick', "return confirm('Are you sure you want to delete this?')");
$grid->addActionButton($deleteLink);

if ($grid->isReadyToHandleRequests()) {
    $grid->bootstrap();
}