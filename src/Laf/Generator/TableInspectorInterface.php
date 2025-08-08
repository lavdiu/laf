<?php

namespace Laf\Generator;

interface TableInspectorInterface
{

    /**
     * @return void
     */
    public function inspect(): void;

    /**
     * @return string
     */
    public function getColumns(): array;

}