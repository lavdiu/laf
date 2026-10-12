<?php

namespace Laf\Generator;

/**
 * Former name of the MySQL / MariaDB inspector, kept so existing code keeps working.
 *
 * @deprecated since 2026-10-12, use MysqlTableInspector (or TableInspectorFactory::create()).
 */
class TableInspector extends MysqlTableInspector
{
}
