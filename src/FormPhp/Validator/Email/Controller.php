<?php

namespace Ideal\FormPhp\Validator\Email;


use Ideal\FormPhp\Validator\AbstractValidator;

/**
 * Валидатор, проверяющий наличие значения в элементе формы
 */
class Controller extends AbstractValidator
{
    protected $errorMsg = "Неверно заполнен адрес электронной почты!";
    /**
     * Проверка введённого пользователем значения
     *
     * @param string $value Введённое пользователем значение
     * @return bool
     */
    public function checkValue($value)
    {
        if ($value == '') {
            return true;
        }
        $filter = filter_var($value, FILTER_VALIDATE_EMAIL);
        return ($filter == $value);
    }

    /**
     * Получение javascript для валидации на стороне клиента
     * @return string
     */
    public function getCheckJs()
    {
        $msg = $this->getErrorMsg();
        return <<<JS
            function validateEmail(e, messages) {
                var pattern = new RegExp(/^[\w-\.]+@[\w-]+\.[a-z]{2,4}$/i);
                var r = pattern.test(e);
                if (!r && e != '') {
                    messages.errors[messages.errors.length] = "{$msg}";
                    messages.validate = false;
                    return messages;
                } else {
                    messages.validate = true;
                    return messages;
                }
            }
JS;
    }
}
