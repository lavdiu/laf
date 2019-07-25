<?php

namespace Laf\UI\Page;

class Page extends GenericPage
{
    /**
     * @return string
     */
    public function draw()
    {
        if (!$this->isEnabled())
            return "";

        $this->setHeader("
    <section class=\"innerbanner\" style=\"background-image:url('".getBannerImage()."');\">
        <div class='container'>
            <div class='row'>
                <div class='col-12 text-center innerbanner--wrapper'>
                    <h1 class='innerbanner-heading'>{$this->getTitle()}</h1>
                </div>
            </div>
        </div>
    </section>
    <section class='contact-us'>
        <div class='container animated fadeIn'>
            <div class='row'>
                <div class='col-sm-10 offset-sm-1 col-md-8 offset-md-2'>
        ");


        $this->setFooter("
                </div>
            </div>
        </div>
    </section>
        ");


        return parent::draw();
    }
}
