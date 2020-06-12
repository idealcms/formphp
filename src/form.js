// todo событие перед валидацией для обработки данных (внедрить на доне для семёрки)
(function ( $ ) {
    /**
     * Функция для привязки к форме события на клик и сабмит формы
     * @param options Объект с параметрами обработки формы
     * @returns {$}
     */
    $.fn.idealForm = function (options, messages) {
        // Получаем форму, на которую навешивается обработчик
        form = this[0];
        // Флаг, который ставится только при отправке файлов через ajax
        form.defaultSubmit = false;
        // Устанавливаем флаг, разрешающий сабмит формы
        form.disableSubmit = false;
        // Записываем в форму её настройки
        form.options = $.extend({
            ajaxUrl: '/', // адрес для ajax-запроса
            ajaxDataType: 'json', // тип получаемых данных
            location: false, // нужно ли добавлять защиту через автогенерируемое поле location
            successMessage:  true, // нужно ли выводить сообщение об успешной отправке
            clearForm: true, // нужно ли очищать форму после удачной отправки
            redirect: '', // куда делать редирект, после удачного заполнения формы
            ajaxSend: true, // отправка формы через ajax
            iframeSend: false, // нужно ли отправлять через iframe (требуется для прикрепления файлов через ajax)
            disableSubmit: false, // для внутреннего использования (блокирует кнопку отправки после однократного нажатия)
            msgSubmitError: 'Форма не отправилась. Попробуйте повторить отправку позже.'
        }, options);
        form.messages = $.extend({
            ajaxError: 'Форма не отправилась. Попробуйте повторить отправку позже.',
            notValid: 'Поля заполнены неверно!',
            errors: [],
            validate: true
        }, messages);

        $(document)
            .off('submit.form-plugin', this.selector, onAjaxSubmit)
            .on('submit.form-plugin', this.selector, onAjaxSubmit);
        return this;
    };

    // private event handlers
    function onAjaxSubmit(e)
    {
        /*jshint validthis:true */
        // Если идёт запрос на отправку через iframe, то просто отправляем форму
        if (this.defaultSubmit === true) {
            return true;
        }
        var options = e.data;
        if (!e.isDefaultPrevented() && this.disableSubmit !== true) { // if event has been canceled, don't proceed
            // Блокируем кнопку отправки формы
            this.disableSubmit = true;
            $(this).find(':submit').attr('disabled', 'disabled');
            // Отменяем стандартный обработчик формы
            e.preventDefault();
            // Посылаем срабатывание цели "Клик на кнопку отправки"
            sendGoal.apply(this, ['click']);
            // Если нужно сгенерировать поле для защиты от роботов
            locationFieldAdd.apply(this);
            // Валидация формы
            if (!idealValidate.apply(this)) {
                // Форма не прошла валидацию
                // Разрешаем нажатие на кнопку Отправки
                this.disableSubmit = false;
                $(this).find(':submit').removeAttr('disabled');
                locationFieldRemove.apply(this);
                return false;
            }
            if (this.options.iframeSend) {
                // Для отправки через iframe используемый отдельный объект
                senderAjax.send(this, idealFormSuccess);
                locationFieldRemove.apply(this);
            } else if (this.options.ajaxSend) {
                // Выполняем ajax-запрос на сервер
                $.ajax({
                    context: this,
                    type: 'post',
                    url: this.options.ajaxUrl,
                    data: $(this).serialize(),
                    dataType: this.options.ajaxDataType,
                    async: true,
                    success: idealFormSuccess,
                    error: idealFormError
                });
                locationFieldRemove.apply(this);
            } else {
                // Выполняем обычный submit
                return true;
            }
        }
        return false;
    }

    /**
     * Обработчик события успешной отправки формы
     * @param result
     */
    function idealFormSuccess(result)
    {
        if (result.status === 'error') {
            // От сервера пришло сообщение об ошибке, переключаемся на обработчик ошибок
            idealFormError.apply(this, [result]);
            return;
        }
        if (this.options.clearForm === true) {
            // Если поставлен флаг очистки формы после удачной отправки данных, то очищаем её от введённых данных
            this.reset();
        }
        // Отправляем цель отправки формы в метрику
        sendGoal.apply(this, ['send']);
        if (this.options.successMessage) {
            // Если нужно отображать сообщение об успешной отправке формы
            alert(result.text);
        }
        // Разблокируем кнопку отправки формы
        $(this).find(':submit').removeAttr('disabled');
        this.disableSubmit = false;
        if (this.options.redirect !== '') {
            // Если нужно - редиректим на указанную страницу
            document.location.href = this.options.redirect;
        }
    }

    /**
     * Обработчик события неудачной отправки формы
     * @param result
     */
    function idealFormError(result)
    {
        // Выводим сообщение о неудаче и текст ответа сервера
        var errorMessage = this.options.msgSubmitError + "\n" + result.responseText;
        errorMessage = result.status === 'error' ? result.errorText : errorMessage;
        alert(errorMessage);
        // todo Добавляем текст об ошибке к соответствующим полям
        // Разблокируем кнопку отправки формы
        $(this).find(':submit').removeAttr('disabled');
        this.disableSubmit = false;
    }

    /**
     * Валидация формы с помощью вызова методов проверки отдельных полей
     * @returns {boolean}
     */
    function idealValidate()
    {
        var $form = $(this);

        $form.find('.error-text').remove();
        var values = $form.find('[name]');
        var check = $.parseJSON(values.filter('[name = "_validators"]').val());
        var messages = this.messages;

        var isValid = true;
        for (var field in check) {
            var input = values.filter('[name = "' + field + '"]');
            for (var k in check[field]) {
                var fn = 'validate' + check[field][k].charAt(0).toUpperCase() + check[field][k].substr(1);
                var value = '';
                if (input.filter('select').size()) {
                    value = input.find(':selected').val();
                } else if (input.filter("[type='radio']").size()) {
                    value = '';
                    input.each(function () {
                        if ($(this).prop("checked")) {
                            value = $(this).val();
                        }
                    });
                } else {
                    value = (typeof input.val() == 'undefined') ? '' : input.val();
                }
                // Выполняем метод проверки соответствующего поля
                messages = eval(fn)(value, messages);
                if (messages.validate) {
                    input.removeClass('error-' + check[field][k]);
                } else {
                    isValid = false;
                    if (input.parent().find('.error-text').length === 0) {
                        input.addClass('error-' + check[field][k]);
                        var errorText = messages.errors[messages.errors.length - 1];
                        if (errorText !== '') {
                            input.parent().append("<div class='error-text'>" + errorText + "</div>");
                        }
                    }
                }
            }
        }
        if (!isValid) {
            if (messages.errors.length > 1) {
                $(this).find('.error-text').show();
                alert(messages.notValid);
            } else {
                alert(messages.errors[0]);
            }
            messages.errors.length = 0;
        }
        return isValid;
    }

    /**
     * Отправка цели в сервисы аналитики
     * @param metka Имя метки, указанной в data-* атрибутах формы
     */
    function sendGoal(metka)
    {
        metka = $(this).data(metka);
        if (metka === undefined) {
            return;
        }
        if (typeof (ym) === "function") {
            var counter = forma.find('[name = "_yandex"]').val();
            ym(counter, 'reachGoal', metka);
        }
        if (typeof (dataLayer) !== 'undefined') {
            var gtmName = forma.find('[name = "_gtm"]').val();
            dataLayer.push({'event': metka});
        }
    }

    /**
     * Для защиты от роботов при клике на кнопку отправки добавляет поле _location
     */
    function locationFieldAdd()
    {
        if (this.options.location) {
            $(this).prepend('<input type="hidden" name="_location" value="' + window.location.href + '">');
        }
    }

    /**
     * После завершения отправки формы удаляет поле_location
     */
    function locationFieldRemove()
    {
        if (this.options.location) {
            $(this).find("input[name='_location']").remove();
        }
    }

    /**
     * Объект для отправки формы с файлами по ajax (через iframe)
     * Копирование формы в фрейм; Отправка фрейма; Получение данных из фрейма
     */
    senderAjax = {
        /**
         * Отправка формы через iframe
         * @param form formID ID формы
         * @param callback Функция, которую нужно будет вывзвать после отправки формы
         */
        send: function (form, callback) {
            this.formCallback = callback;
            this.form = form;
            if (typeof $(this).id == "undefined") {
                this.create();
            }
            $(form).attr('target', this.id);
            $(form).attr('action', form.options.ajaxUrl);
            $(form).attr('method', 'post');
            form.defaultSubmit = true;
            $(form).submit();
        },

        /**
         * Создание фрейма
         * @returns {*|jQuery} Объект iframe
         */
        create: function () {
            var id = 'iFrameID' + Math.floor(Math.random() * 99999);
            var url = this.form.options.ajaxUrl;
            var html = '<iframe id="' + id + '" name="' + id + '" url="' + url
                + '" src="about:blank" style="display: none;"></iframe>';
            $(this.form).append(html);
            this.iframe = $(this.form).children('iframe');
            this.iframe.load(function () {
                senderAjax.callback(url, senderAjax.getIFrameXML());
            });
            this.id = id;
        },

        /**
         * Получение содержимого iframe после отправки
         * @param e
         * @returns {*}
         */
        getIFrameXML: function (e) {
            var response = $(this.iframe).contents().find("body").text();
            if (this.form.options.ajaxDataType === 'json' || this.form.options.ajaxDataType === 'jsonp') {
                try {
                    response = $.parseJSON(response);
                } catch (err) {
                    // Произошла ошибка парсинга json
                    response = {status: 'error', errorText: 'Ошибка сервера'};
                }
            }
            return response;
        },

        /**
         * Запуск callback-функции после получении ответа после отправки формы
         * @param url Адрес выполненного скрипта
         * @param response Ответ, полученный от сервера
         */
        callback: function (url, response) {
            $(this.iframe).remove();
            this.form.defaultSubmit = false;
            this.formCallback.apply(this.form, [response, this.form.options])
        }
    };
}( jQuery ));
