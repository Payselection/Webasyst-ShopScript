<?php
$settings = array(
    'site_id'      => array(
        'value'        => '',
        'title'        => /*_w*/('Идентификатор ТСП'),
        'control_type' => waHtmlControl::INPUT,
        'class'        => '',
    ),
    'public_key'      => array(
        'value'        => '',
        'title'        => /*_w*/('Публичный ключ'),
        'control_type' => waHtmlControl::INPUT,
        'class'        => '',
    ),
    'secret_key'      => array(
        'value'        => '',
        'title'        => /*_w*/('Секретный ключ'),
        'control_type' => waHtmlControl::INPUT,
        'class'        => '',
    ),
    'payment_type'      => array(
        'value'        => 'Pay',
        'title'        => /*_w*/('Тип платежа'),
        'control_type' => waHtmlControl::SELECT,
        'options'      => array(
            array(
                'title' => /*_w*/('одностадийный платеж'),
                'value' => 'Pay'
            ),
            array(
                'title' => /*_w*/('двухстадийный платеж'),
                'value' => 'Block'
            ),
        ),
    ),
    'form_type'      => array(
        'value'        => 'form',
        'title'        => /*_w*/('Тип кнопки'),
        'control_type' => waHtmlControl::SELECT,
        'options'      => array(
            array(
                'title' => /*_w*/('переход на форму оплаты'),
                'value' => 'form'
            ),
            array(
                'title' => /*_w*/('виджет с предэкраном'),
                'value' => 'prewidget'
            ),
            array(
                'title' => /*_w*/('виджет'),
                'value' => 'widget'
            ),
            array(
                'title' => /*_w*/('виджет с предэкраном с автозапуском'),
                'value' => 'autoprewidget'
            ),
            array(
                'title' => /*_w*/('виджет с автозапуском'),
                'value' => 'autowidget'
            ),
        ),
    ),
    'currency'      => array(
        'value'        => 'RUB',
        'title'        => /*_w*/('Валюта платежа'),
        'control_type' => waHtmlControl::SELECT,
        'options'      => array(
            array(
                'value' => 'USD',
                'title' => /*_w*/('Доллар США')
            ),
            array(
                'value' => 'EUR',
                'title' => /*_w*/('Евро')
            ),
            array(
                'value' => 'RUB',
                'title' => /*_w*/('Российский рубль')
            ),
        ),
    ),
    'pay_text'        => array(
        'value'        => '',
        'title'        => /*_w*/('Текст на кнопке оплаты'),
        'control_type' => waHtmlControl::INPUT,
    ),
    'redirect_text'        => array(
        'value'        => '',
        'title'        => /*_w*/('Текст переадресации на форму оплаты'),
        'control_type' => waHtmlControl::INPUT,
    ),
    'fz54'      => array(
        'value'        => '',
        'title'        => /*_w*/('Отправлять информацию для регистрации чеков'),
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'inn'      => array(
        'value'        => '',
        'title'        => /*_w*/('ИНН организации'),
        'control_type' => waHtmlControl::INPUT,
    ),
    'tax'      => array(
        'value'        => '',
        'title'        => /*_w*/('Источник значения НДС'),
        'control_type' => waHtmlControl::SELECT,
        'options'      => array(
            array(
                'title' => /*_w*/('Из карточки товара'),
                'value' => ''
            ),
            array(
                'title' => /*_w*/('НДС 20%'),
                'value' => '20'
            ),
            array(
                'title' => /*_w*/('Без НДС'),
                'value' => '-1'
            ),
        ),
    ),
    'tax_delivery'     => array(
        'value'        => '20',
        'title'        => /*_w*/('Источник значения НДС для доставки'),
        'control_type' => waHtmlControl::SELECT,
        'options'      => array(
            array(
                'title' => /*_w*/('НДС 20%'),
                'value' => '20'
            ),
            array(
                'title' => /*_w*/('Без НДС'),
                'value' => '-1'
            ),
        ),
    ),
    'payment_method'      => array(
        'value'        => 'full_payment',
        'title'        => /*_w*/('Признак способа расчёта'),
        'control_type' => waHtmlControl::SELECT,
        'options'      => array(
            array(
                'value' => 'full_prepayment',
                'title' => /*_w*/('Предоплата 100%')
            ),
            array(
                'value' => 'prepayment',
                'title' => /*_w*/('Предоплата')
            ),
            array(
                'value' => 'advance',
                'title' => /*_w*/('Аванс')
            ),
            array(
                'value' => 'full_payment',
                'title' => /*_w*/('Полный расчет')
            ),
        ),
    ),
    'log'      => array(
        'value'        => '',
        'title'        => 'Запись логов',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
);
if($this->app_id != 'shop') {
    $settings['fz54'] = array(
        'value'        => '',
        'control_type' => waHtmlControl::HIDDEN,
    );
}
return $settings;