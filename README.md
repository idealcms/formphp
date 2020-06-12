# Ideal CMS FormPhp v.5.0

Минималистичный фреймворк для работы с формами (с использованием jQuery)

Является компонентом Ideal CMS, но может работать и отдельно.

Пример использования представлен в файле `src/example.php`.

Есть два варианта подключения фреймворка форм:

1. Без окружения Ideal CMS
2. С подключение автозагрузчика Ideal CMS для использования всех возможностей CMS

## 1 вариант

Подключение через Composer:

    require_once '../vendor/autoload.php';
    $form = new Ideal\FormPhp('myForm');

## 2 вариант    

Подключение через загрузчик Ideal CMS:

    $isConsole = true;
    require_once $_SERVER['DOCUMENT_ROOT'] . '/_.php';
    $form = new Ideal\FormPhp('myForm');

Далее пример использования смотрите в файле `src/example.php`.
