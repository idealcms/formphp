<?php

namespace Ideal\FormPhp\Field\Referer;


use Ideal\FormPhp\Field\AbstractField;

/**
 * Простое текстовое поле ввода
 */
class Controller extends AbstractField
{
    public function getValue()
    {
        return (isset($_COOKIE['referer'])) ? $_COOKIE['referer'] : 'empty';
    }
}
