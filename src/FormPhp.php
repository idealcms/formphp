<?php
/**
 * Ideal FormPhp (http://idealcms.ru/)
 *
 * @link      http://github.com/idealcms/formphp репозиторий исходного кода
 * @copyright Copyright (c) 2012-2020 Ideal CMS (https://idealcms.ru)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal;

/**
 * Класс для работы с веб-формами
 *
 */
class FormPhp
{
    /** @var \Ideal\FormPhp\Field\AbstractField[] Список полей ввода в форме */
    public $fields = array();

    /** @var array Список ошибок, возникших во время работы формы */
    public $errors = [];

    /** @var string Название формы  */
    protected $formName;

    /** @var string Класс формы  */
    protected $formClassName;

    /** @var string Метод передачи данных формы (POST||GET) */
    protected $method = 'POST';

    /** @var string Html-текст формы */
    protected $text = '';

    /** @var string javascript формы */
    protected $js = '';

    /** @var array Массив валидаторов, применённых к элементам формы */
    protected $validators = array();

    /** @var bool Флаг отображения html-сущностей в XHTML или в HTML стиле */
    protected $xhtml = true;

    /** @var string Тип заказа */
    protected $orderType = 'Заявка с сайта';

    /** @var array Атрибуты формы */
    protected $attributes = array();

    /** @var bool Флаг для осуществления изначальной валидации по местонахождению формы */
    protected $locationValidation = false;
    protected $targets;
    protected $counters = array();
    protected $ajaxUrl;

    /** @var array Аргументы js функции формы */
    protected $formJsArguments = array();

    /** @var bool Флаг для вывода сообщения при правильно заполненной форме */
    protected $successMessage = true;

    /** @var bool Флаг для очищения формы после удачной отправки */
    protected $clearForm = true;

    /** @var bool Флаг отвечающий за отправку заголовков */
    protected $sendHeader = true;

    /** @var bool Флаг отвечающий за отправку формы методом ajax */
    protected $ajaxSend = true;

    /**
     * Инициализируем сессии, если это нужно
     *
     * @param string $formName Название формы (используется в html-теге form)
     * @param bool $xhtml Если истина, то код полей ввода будет отображается в xhtml-стиле
     */
    public function __construct($formName, $xhtml = true, $formClassName = '')
    {
        /**  Будет работать только на PHP 5.4, здесь можно проверить не запрещены ли сессии PHP_SESSION_DISABLED
        if(session_status() != PHP_SESSION_ACTIVE) {
        session_start();
        }*/

        if (session_id() == '') {
            // Если сессия не инициализирована, инициализируем её
            session_start();
        }

        $this->xhtml = $xhtml;
        $this->formName = $formName;
        $this->formClassName = $formClassName;

        // Добавляем поля токена, реферера и текущей страницы.
        $this->add('_token', 'token');
        $this->add('referer', 'referer');
        $this->add('_location', 'text');
    }

    /**
     * Возвращает токен и валидацию для присутствия на форме
     *
     * @return string
     */
    public function start()
    {
        $fileSendForm = '';
        // Проверяем на наличие поля типа "File" или "FileMulti"
        foreach ($this->fields as $class) {
            if (is_a($class, 'FormPhp\Field\File\Controller') || is_a($class, 'FormPhp\Field\FileMulti\Controller')) {
                $fileSendForm = 'enctype="multipart/form-data" ';
            }
        }

        $attributes = '';
        foreach ($this->attributes as $k => $v) {
            $attributes .= $k . '="' . $v . '" ';
        }

        /** @var \Ideal\FormPhp\Field\Token\Controller $token */
        $token = $this->fields['_token'];
        $start = '<form method="' . $this->method . '" id="' . $this->formName . '" '
            . 'data-click="' . $this->targets['click'] . '" '
            . 'data-send="' . $this->targets['send'] . '" '
            . $attributes
            . $fileSendForm . '>' . "\n"
            . $token->getInputText() . "\n"
            . $this->getValidatorsInput();

        // Установка скрытых полей для целей и счётчиков метрики или GTM
        foreach ($this->counters as $key => $counter) {
            $start .= "\n" . '<input type="hidden" value="' . $counter . '" name="_' . $key . '">';
        }

        return $start;
    }

    /**
     * Установка целей, срабатывающих при клике на кнопку Отправить и на реальной отправке формы
     *
     * @param string $click Цель на нажатие на кнопку Отправить
     * @param string $send Цель на отправку формы
     */
    public function setClickAndSend($click, $send)
    {
        $this->targets = array(
            'click' => $click,
            'send' => $send,
        );
    }

    /**
     * Установка GTM-события на успешную отправку формы
     *
     * @param string $gtmEvent Название события, которое должно сработать при отправке формы
     */
    public function setGtm($gtmEvent = 'gtm-event')
    {
        $this->counters['gtm'] = $gtmEvent;
    }

    /**
     * Установка идентификатора счётчика Яндекс.Метрики
     *
     * @param string $counterId Счётчик Яндекс.Метрики (например, yaCounter12345678)
     */
    public function setMetrika($counterId)
    {
        $this->counters['yandex'] = $counterId;
    }

    /**
     * Устанавливает тип заказа
     *
     * @param $orderType
     */
    public function setOrderType($orderType)
    {
        $this->orderType = $orderType;
    }

    /**
     * Устанавливает флаг для осуществления изначальной валидации по местонахождению формы
     *
     * @param $locationValidation
     */
    public function setLocationValidation($locationValidation)
    {
        $this->locationValidation = $locationValidation;
    }

    /**
     * Устанавливает флаг для вывода сообщения при правильно заполненной форме
     *
     * @param $successMessage
     */
    public function setSuccessMessage($successMessage)
    {
        $this->successMessage = $successMessage;
    }

    /**
     * Устанавливает флаг для очищения формы после удачной отправки
     *
     * @param $clearForm
     */
    public function setClearForm($clearForm)
    {
        $this->clearForm = $clearForm;
    }

    /**
     * Устанавливаем атрибуты формы
     *
     * @param array $attributes
     */
    public function setAttributes($attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);
    }

    /**
     * Устанавливаем надобность отправки заголовка в ответе
     *
     * @param bool $sendHeader
     */
    public function setSendHeader($sendHeader)
    {
        $this->sendHeader = $sendHeader;
    }

    /**
     * Устанавливает надобность отправки формы ajax методом
     *
     * @param bool $ajaxSend
     */
    public function setAjaxSend($ajaxSend)
    {
        $this->ajaxSend = $ajaxSend;
    }

    /**
     * Добавление элемента к форме
     *
     * Options:
     * label, placeholder, help, default
     *
     * @param string $name    Название элемента формы
     * @param string $type    Тип элемента формы
     * @param array  $options Массив опций
     * @return $this
     * @throws \Exception
     */
    public function add($name, $type, $options = array())
    {
        $fieldName = '\\Ideal\\FormPhp\\Field\\' . ucfirst($type) . '\\Controller';
        if (!class_exists($fieldName)) {
            throw new \Exception('Не найден класс ' . $fieldName . ' для поля ввода');
        }
        /** @var \Ideal\FormPhp\Field\AbstractField $field */
        $field = new $fieldName($name, $options);
        $field->setMethod($this->method);
        $field->setXhtml($this->xhtml);
        $this->fields[$name] = $field;
        return $this;
    }

    /**
     * Отображение поля ввода с json-массивом полей с валидаторами
     *
     * @return string
     */
    public function getValidatorsInput()
    {
        $arr = array();
        foreach ($this->fields as $name => $field) {
            /** @var $field \Ideal\FormPhp\Field\AbstractField */
            $arr[$name] = $field->getValidators();
            if (count($arr[$name]) == 0) {
                unset($arr[$name]);
            }
        }
        $xhtml = ($this->xhtml) ? '/' : '';
        return '<input type="hidden" name="_validators" value="'
            . htmlspecialchars(json_encode($arr, JSON_FORCE_OBJECT)) . '" ' . $xhtml
            . '>' . "\n";
    }

    /**
     * Проверка на передачу данных формы методом POST
     *
     * @return bool
     */
    public function isPostRequest()
    {
        return (isset($_SERVER['REQUEST_METHOD']) && (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST'));
    }

    /**
     * Получение значения переданного полем формы
     *
     * @param string $name Имя параметра
     * @return mixed Значение параметра (если не указан, то null)
     */
    public function getValue($name)
    {
        /** @var \Ideal\FormPhp\Field\AbstractField $field */
        $field = $this->fields[$name];
        return $field->getValue();
    }

    /**
     * @param string $name Имя параметра
     * @return mixed Значение параметра (если не указан, то null)
     * @deprecated
     */
    public function getParam($name)
    {
        return $this->getValue($name);
    }

    /**
     * Проверка валидности всех введённых пользователем данных
     *
     * @return bool
     */
    public function isValid()
    {
        // Если установлен флаг проверки по странице отправки формы, то проверяем по рефереру, иначе по токену.
        if ($this->locationValidation) {
            $location = $this->getValue('_location');
            $pos = mb_strpos($location, '#');
            if ($pos !== false) {
                $location = mb_substr($location, 0, $pos);
            }
            if (empty($location) || $_SERVER['HTTP_REFERER'] != $location) {
                return false;
            }
        } else {
            $token = $this->getValue('_token');
            if (is_null($token)) {
                // Токен не установлен
                return false;
            }
            if (crypt(session_id(), $token) != $token) {
                // Токен не сопадает с сессией
                return false;
            }
        }

        $result = true;
        foreach ($this->fields as $name => $field) {
            /** @var \Ideal\FormPhp\Field\AbstractField $field */
            $valid = $field->isValid();
            if (!$valid) {
                // Если были ошибки, то добавить их в общий список ошибок
                $this->errors += $field->getErrorMsg();
            }
            $result = $result && $valid;
        }

        return $result;
    }

    /**
     * Отображение формы, css- или js-скриптов
     * @param bool $echo
     * @return string
     */
    public function render($echo = true)
    {
        $header = '';
        $content = '';
        if (!isset($_REQUEST['mode']) || ($_REQUEST['mode'] == 'form')) {
            $header = 'Content-Type: application/json';
            $content = $this->text;
        } else {
            switch ($_REQUEST['mode']) {
                case 'css':
                    $header = 'Content-type: text/css; charset=utf-8';
                    $content = $this->renderCss();
                    break;
                case 'js':
                    $header = 'Content-Type: text/javascript; charset=utf-8';
                    $content = $this->renderJs();
                    break;
            }
        }
        if ($echo) {
            if ($this->sendHeader) {
                header($header);
            }
            echo $content;
        }
        return $content;
    }

    /**
     * Получение текста на отображение
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Установка url, по которому будет производиться ajax-запрос отправки формы
     *
     * @param string $url url скрипта для обработки формы
     */
    public function setAjaxUrl($url)
    {
        $this->ajaxUrl = $url;
    }

    /**
     * Установка аргументов js-функции формы
     *
     * В передаваемом массиве могут быть следующие параметры:
     * options — json-массив настроек скрипта (ajaxUrl, ajaxDataType, location, successMessage, clearForm)
     * messages — json-массив сообщений формы (submitError, notValid, errors, validate)
     * methods — объект со списком методов для переопределения стандартных методов формы
     *
     * @param array $arguments Массив аргументов js-функции формы
     */
    public function setFormJsArg($arguments)
    {
        $this->formJsArguments = $arguments;
    }

    /**
     * Установка метода передачи данных формы
     *
     * @param string $method Метод передачи данных формы (POST||GET)
     * @throws \Exception
     */
    public function setMethod($method)
    {
        $method = strtoupper($method);
        $availableMethods = array('POST', 'GET');
        if (!in_array($method, $availableMethods)) {
            throw new \Exception('Метод может быть только ' . implode('или', $availableMethods));
        }

        // Проставляем метод для всех полей
        foreach ($this->fields as $name => $field) {
            /** @var \Ideal\FormPhp\Field\AbstractField $field */
            $field->setMethod($method);
        }

        $this->method = $method;
    }

    /**
     * Ручная установка текста формы
     *
     * @param string $text Текст формы
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * Ручная установка общего javascript формы
     *
     * @param string $js
     */
    public function setJs($js)
    {
        $this->js .= "\n" . $js . "\n";
    }

    /**
     * Установка валидатора на элемент формы
     *
     * @param string $name Название элемента формы
     * @param string|array $validator Название, список или класс валидатора
     * @param array $options Массив опций
     * @throws \Exception
     */
    public function setValidator($name, $validator, $options = array())
    {
        if (is_string($validator)) {
            if (!isset($this->fields[$name])) {
                throw new \Exception('Не найден элемент формы с именем ' . $name);
            }
            $fieldName = '\\Ideal\\FormPhp\\Validator\\' . ucfirst($validator) . '\\Controller';
            if (!class_exists($fieldName)) {
                throw new \Exception('Не найден класс ' . $fieldName . ' для валидатора');
            }
            $this->validators[$validator] = new $fieldName($options);
            /** @var \Ideal\FormPhp\Field\AbstractField $field */
            $field = $this->fields[$name];
            $field->setValidator($this->validators[$validator]);
        }
    }

    /**
     * Генерирование css-скрипта, общего для всей формы
     *
     * Css генерируется на основе общего скрипта для формы, плюс css-скрипты для полей ввода
     * и валидаторов
     *
     * @return string
     */
    protected function renderCss()
    {
        $css = file_get_contents(__DIR__ .'/form.css');

        return $css;
    }

    /**
     * Определение кастомного кода отправки формы
     *
     * @return string Кастомный код отправки формы
     * @throws \Exception
     */
    protected function getSenderJs()
    {
        $js = '';
        foreach ($this->fields as $v) {
            /** @var $v \Ideal\FormPhp\Field\AbstractField */
            $nextJs = $v->getSenderJs();
            if (!empty($js) && !empty($nextJs) && ($js != $nextJs)) {
                throw new \Exception('Ошибка! Найдено несколько классов отправки формы');
            }
            $js .= $nextJs;
        }
        return $js;
    }

    /**
     * Генерирование js-скрипта, общего для всей формы
     *
     * Js генерируется на основе общих js-скриптов для формы, плюс js-скрипты для полей ввода
     * и валидаторов
     *
     * @return string
     * @throws \Exception
     */
    protected function renderJs()
    {
        $js = array();

        foreach ($this->validators as $v) {
            /** @var $v \Ideal\FormPhp\Validator\AbstractValidator */
            $js[] = $v->getCheckJs();
        }

        foreach ($this->fields as $v) {
            /** @var $v \Ideal\FormPhp\Field\AbstractField */
            $js[get_class($v)] = $v->getJs();
        }

        $js[] = $this->getSenderJs();

        $options = [
            'ajaxUrl' => $this->ajaxUrl,
            'location' => $this->locationValidation,
            'successMessage' => $this->successMessage,
            'clearForm' => $this->clearForm,
            'ajaxSend' => $this->ajaxSend,
        ];

        if (isset($this->formJsArguments['options'])) {
            $options += $this->formJsArguments['options'];
        }

        $messages = isset($this->formJsArguments['messages']) ? $this->formJsArguments['messages'] : [];

        // todo решить, что делать с methods
        $methods = isset($this->formJsArguments['methods']) ? $this->formJsArguments['methods'] : '{}';

        $options = json_encode($options);
        $messages = json_encode($messages);

        $selector = empty($this->formClassName) ? '#' . $this->formName : '.' . $this->formClassName;

        $ajaxUrl = "$('{$selector}').idealForm({$options}, {$messages});";

        $this->js = ''
            . implode("\n", $js)
            . "\njQuery(document).ready(function () {\n var $ = jQuery;\n"
            //. file_get_contents(__DIR__ .'/form.js')
            . $this->js
            . "\n" . $ajaxUrl
            . "\n"  . '})';

        return $this->js;
    }

    /**
     * Отправление писем получателям
     *
     * @param string $from От имени кого отправляется почта
     * @param string $to Список получателей
     * @param string $title Заголовок письма
     * @param string $body Тело письма
     * @param $html bool Флаг, если true значит текст содержит html, false - обычный текст
     * @param $smtpParams array Параметры для отправки сообщения посредством smtp
     * @return bool Признак принятия почты к отправке
     */
    public function sendMail($from, $to, $title, $body, $html = false, $smtpParams = array())
    {
        if (!class_exists('\Ideal\Mailer')) {
            // Окружение не инициализировано и продвинутого класса отправки почты нет
            // Поэтому шлём самое простое письмо
            $response = mail($to, $title, $body, 'From: ' . $from);
            return $response;
        }
        /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        $sender = new \Ideal\Mailer();
        if (!empty($smtpParams)) {
            $sender->setSmtp($smtpParams);
        }

        // Устанавливаем заголовок письма
        $sender->setSubj($title);

        // Если были переданы файлы и не возникло ошибок, то прикрепляем их к письму
        if (isset($_FILES) && !empty($_FILES)) {
            foreach ($_FILES as $file) {
                if ($file['error'] == UPLOAD_ERR_OK) {
                    if ($file['name'] == '') {
                        continue;
                    }
                    $sender->fileAttach($file['tmp_name'], $file['type'], $file['name']);
                } elseif ($file['error'] != UPLOAD_ERR_NO_FILE) {
                    // Собираем данные о возникшей ошибке
                    switch ($file['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $this->errors[] = 'Файл слишком большой.';
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $this->errors[] = 'Файл с таким расширением не может быть загружен.';
                            break;
                        default:
                            $this->errors[] = 'Не удалось загрузить файл.';
                            break;
                    }
                }
            }
        }

        // Если был установлен флаг html, то устанавливаем текст как html.
        // В противном случае тело письма устанавливается как обычный текст
        if ($html) {
            $sender->setHtmlBody($body);
        } else {
            $sender->setPlainBody($body);
        }

        $response = $sender->sent($from, $to);

        return $response;
    }

    /**
     * Получение файлов, переданных через форму
     * Состав элементов массива: ['path' => '', 'type' => '', 'name' => '']
     *
     * @return array
     */
    public function getFiles()
    {
        if (!isset($_FILES) || empty($_FILES)) {
            // Если файлы не прикреплены к письму, возвращаем пустой массив
            return [];
        }

        // Если были переданы файлы, то записываем их координаты в массив $files
        $files = [];
        foreach ($_FILES as $file) {
            if ($file['error'] == UPLOAD_ERR_OK) {
                if ($file['name'] == '') {
                    continue;
                }
                $files[] = [
                    'path' => $file['tmp_name'],
                    'type' => $file['type'],
                    'name' => $file['name'],
                ];
            } elseif ($file['error'] != UPLOAD_ERR_NO_FILE) {
                // Собираем данные о возникшей ошибке
                switch ($file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $this->errors[] = 'Файл слишком большой.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $this->errors[] = 'Файл с таким расширением не может быть загружен.';
                        break;
                    default:
                        $this->errors[] = 'Не удалось загрузить файл.';
                        break;
                }
            }
        }

        return $files;
    }

    /**
     * Сохраняем в базу информацию о заказе и заказчике
     *
     * @param string $name Имя заказчика
     * @param string $email E-mail заказчика
     * @param string $content Текст заказа
     * @param int $price Сумма заказа
     * @param string $phone Телефон заказчика
     *
     * @return int $newOrderId Идентификатор нового заказа
     */
    public function saveOrder($name, $email, $content = '', $price = 0, $phone = '')
    {
        $newOrderId = 0;
        // Записываем в базу, только если доступны нужные классы
        if (class_exists('\Ideal\Core\Db') && class_exists('\Ideal\Core\Config')) {
            // Получаем подключение к базе
            /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
            $db = \Ideal\Core\Db::getInstance();

            // Получаем конфигурационные данные сайта
            /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
            $config = \Ideal\Core\Config::getInstance();

            // Формируем название таблицы, в которую записывается информация о заказе
            $orderTable = $config->db['prefix'] . 'ideal_structure_order';

            // Получаем идентификатор справочника "Заказы с сайта" для построения поля "prev_structure"
            $dataList = $config->getStructureByName('Ideal_DataList');
            $prevStructure = $dataList['ID'] . '-';
            $par = array('structure' => 'Ideal_Order');
            $fields = array('table' => $config->db['prefix'] . 'ideal_structure_datalist');
            $row = $db->select('SELECT ID FROM &table WHERE structure = :structure', $par, $fields);
            $prevStructure .= $row[0]['ID'];

            // Умножаем сумму заказа на 100 для хранения в базе
            $price *= 100;

            // Определяем Google ClientID
            $googleClientId = null;
            if (isset($_COOKIE['_ga'])) {
                list($version, $domainDepth, $cid1, $cid2) = explode('.', $_COOKIE["_ga"], 4);
                $googleClientId = empty($cid1) && empty($cid2) ? null : $cid1 . '.' . $cid2;
            }

            // Записываем данные
            $newOrderId = $db->insert(
                $orderTable,
                array(
                    'prev_structure' => $prevStructure,
                    'date_create' => time(),
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'client_id' => $googleClientId,
                    'price' => $price,
                    'referer' => $this->getValue('referer'),
                    'content' => $content,
                    'order_type' => $this->orderType,
                )
            );
        }
        return $newOrderId;
    }

    /**
     * @param bool $isArray
     * @return string
     */
    public function getErrors($isArray = false)
    {
        if (empty($this->errors)) {
            return '';
        }

        if ($isArray) {
            return $this->errors;
        }

        $text = '';
        foreach ($this->errors as $error) {
            $text .= $error['name'] . ': ' . $error['error'];
        }

        return $text;
    }

    /**
     * Генерация json-ответа при успешном заполнении формы
     *
     * @param string $message
     */
    public function exitJsonSuccess($message = 'Форма заполнена правильно')
    {
        $this->setText(json_encode([
            'status' => 'ok',
            'data' => $this->getValues(),
            'text' => $message
        ]));
        $this->render();
        exit;
    }

    /**
     * Генерация json-ответа при успешном заполнении формы
     *
     * @param string $message
     */
    public function exitJsonError($message = "Форма заполнена неправильно:\n")
    {
        $errorsText = '';
        $errors = $this->getErrors(true);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $errorsText .= $error['error'] . "\n";
            }
        }
        $this->setText(json_encode([
            'status' => 'error',
            'data' => $this->getValues(),
            'errorText' => $message . "\n" . $errorsText
        ]));
        $this->render();
        exit;
    }

    /**
     * Возвращает массив с заполненными данными формы
     *
     * При заполнении исключает технические поля, начинающиеся с подчёркивания
     *
     * @return array Данные формы
     */
    public function getValues()
    {
        $values = [];
        foreach ($this->fields as $field) {
            $name = $field->getName();
            if (strpos($name, '_') === 0) {
                continue;
            }
            $values[$name] = $this->getValue($name);
        }
        return $values;
    }
}
