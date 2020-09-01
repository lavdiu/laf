<?php


namespace Laf\UI\Container;


use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;

class CardBody extends Div implements ComponentInterface
{
    use ComponentTrait;

    /**
     * Card constructor.
     */
    public function __construct()
    {
        $this->setContainerType("");
    }

    /**
     * @inheritDoc
     */
    public function draw(): ?string
    {
        $this->addCssClass('card-body');
        return parent::draw();

    }

    /**
     * @return string
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}