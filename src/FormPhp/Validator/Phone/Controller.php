<?php

namespace Ideal\FormPhp\Validator\Phone;


use Ideal\FormPhp\Validator\AbstractValidator;

/**
 * Валидатор, проверяющий наличие значения в элементе формы
 */
class Controller extends AbstractValidator
{
    protected $errorMsg = "Неверно заполнен номер телефона!";
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
        preg_match_all('/[0-9]/i', $value, $result);
        $count = count($result[0]);
        if (isset($result[0]) && ($count < 7) && !($count == 1 && $result[0][0] == '7')) {
            return false;
        }
        return true;
    }

    /**
     * Получение javascript для валидации на стороне клиента
     * @return string
     */
    public function getCheckJs()
    {
        $msg = $this->getErrorMsg();
        return <<<JS
            function validatePhone(e, messages) {
                var r = e.match(/[0-9]/g);
                if (e !== '' && (r === null || r.length < 7)) {
                    // Номер заполнен, но не содержит нужного кол-ва цифры
                    messages.errors[messages.errors.length] = "{$msg}";
                    messages.validate = false;
                    return messages;
                } else {
                    // Номер заполнен правильно
                    messages.validate = true;
                    return messages;
                }
            }
JS;
    }
}
