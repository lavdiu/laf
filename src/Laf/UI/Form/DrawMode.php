<?php

namespace Laf\UI\Form;

/**
 * The three ways a form or an element is drawn: read-only (VIEW), a new record
 * (INSERT) or an existing one (UPDATE). Plain string constants, so a mode can
 * travel in a request and be compared with ==.
 */
class DrawMode
{
    const VIEW = 'view';
    const UPDATE = 'update';
    const INSERT = 'insert';

    /** Every mode */
    const ALL = [self::VIEW, self::INSERT, self::UPDATE];

    /** The modes that post a form */
    const EDIT = [self::INSERT, self::UPDATE];

    public static function isValid(?string $mode): bool
    {
        return in_array($mode, self::ALL, true);
    }
}
