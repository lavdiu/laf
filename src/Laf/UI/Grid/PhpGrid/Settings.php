<?php


namespace Laf\UI\Grid\PhpGrid;


class Settings
{
    /**
     * @var ActionButton[]
     */
    protected $actionButtons = [];

    /**
     * @return ActionButton[]
     */
    public function getActionButtons(): array
    {
        return $this->actionButtons;
    }

    /**
     * @param ActionButton[] $actionButtons
     * @return Settings
     */
    public function setActionButtons(array $actionButtons): Settings
    {
        $this->actionButtons = $actionButtons;
        return $this;
    }

    public function addActionButton(ActionButton $button): Settings
    {
        $this->actionButtons[] = $button;
        return $this;
    }

}
