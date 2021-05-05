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

        $this->addCssClass('p-2');

        return parent::draw();
    }
}
