<?php

namespace Laf\UI\Form;

/**
 * Values of the HTML "type" attribute the inputs and controls render.
 */
class InputType
{
    const Button = 'button';
    const Color = 'color';
    const Checkbox = 'checkbox';
    const Date = 'date';
    const DatetimeLocal = 'datetime-local';
    /** @deprecated obsolete in HTML; use DatetimeLocal */
    const Datetime = 'datetime';
    const Email = 'email';
    const File = 'file';
    const Hidden = 'hidden';
    const Number = 'number';
    const Password = 'password';
    const Range = 'range';
    const Search = 'search';
    const Tel = 'tel';
    const Text = 'text';
    const Time = 'time';
    const Url = 'url';
    const Submit = 'submit';
}
