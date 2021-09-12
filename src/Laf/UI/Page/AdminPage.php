<?php

namespace Laf\UI\Page;

class AdminPage extends GenericPage
{

    /**
     * @return string
     * @throws \Laf\Exception\MissingConfigParamException
     */
    public function draw(): string
    {
        if (!$this->isEnabled())
            return "";

        return parent::draw();
    }
}
