<?php

ini_set('serialize_precision', 12);

class payselectionPayment extends waPayment implements waIPayment, waIPaymentCancel, waIPaymentCapture, waIPaymentRefund
{
    protected $host = array(
        'gw' => 'https://gw.payselection.com',
        'webpay' => 'https://webform.payselection.com'
    );

    public function allowedCurrency()
    {
        return $this->currency;
    }

    public function supportedOperations()
    {
        return array(self::OPERATION_AUTH_ONLY,
            self::OPERATION_CAPTURE,
            self::OPERATION_REFUND,
            self::OPERATION_CANCEL
        );
    }

    public function getUrl($method, $type_host = 'gw')
    {
        return $this->host[$type_host] . $method;
    }

    public function getSignature($method, $url, $request_id, $content)
    {
        $string = $method . PHP_EOL;
        $string .= $url . PHP_EOL;
        $string .= $this->site_id . PHP_EOL;
        $string .= $request_id . PHP_EOL;
        $string .= json_encode($content);
        return hash_hmac('sha256', $string, $this->secret_key, false);
    }

    public function getWebhookSignature()
    {
        $string = 'POST' . PHP_EOL;
        $string .= $this->getRelayUrl() . '?app_id=' . $this->app_id . '&merchant_id=' . $this->merchant_id . '&order_id=' . $this->order_id . '&transaction_result=webhook' . PHP_EOL;
        $string .= $this->site_id . PHP_EOL;
        $string .= file_get_contents('php://input');
        return hash_hmac('sha256', $string, $this->secret_key, false);
    }

    public function query($api_method, $content, $method = waNet::METHOD_POST, $type_host = 'gw')
    {
        $url = $this->getUrl($api_method, $type_host);
        $options = array(
            'format' => waNet::FORMAT_JSON,
            'timeout' => 30,
            'expected_http_code' => null
        );
        $uuid = waString::uuid();
        $headers = array(
            'X-SITE-ID' => (string)$this->site_id,
            'X-REQUEST-ID' => $uuid,
            'X-REQUEST-SIGNATURE' => $this->getSignature(strtoupper($method), $api_method, $uuid, $content),
        );
        $net = new waNet($options, $headers);
        try {
            if ($this->log) {
                waLog::dump([$url, $headers, $content], 'payselection.' . $this->app_id . '.' . $this->merchant_id . '.log');
            }
            $response = $net->query($url, $content, $method);
            if ($this->log) {
                waLog::dump($response, 'payselection.' . $this->app_id . '.' . $this->merchant_id . '.log');
            }
            return $response;
        } catch (Exception $e) {
            if ($this->log) {
                waLog::dump([$e->getMessage()], 'payselection.' . $this->app_id . '.' . $this->merchant_id . '.log');
            }
            waLog::dump($e->getMessage(), 'payselection.' . $this->app_id . '.' . $this->merchant_id . '.error.log');
        }
    }

    public function apiCreateArray($order_data)
    {
        $contact = new waContact($order_data->contact_id);
        $email = $contact->getFirst('email');
        $phone = $contact->getFirst('phone');
        $content = array(
            'MetaData' => array(
                'PaymentType' => $this->payment_type,
                'TypeLink' => 'Reusable',
                'PreviewForm' => $this->form_type == 'prewidget'
            ),
            'PaymentRequest' => array(
                'OrderId' => (string)$order_data->id_str,
                'Amount' => (string)$order_data->total,
                'Currency' => (string)$this->currency,
                'Description' => _w('Оплата заказа').' '.$order_data->id_str,
                'ExtraData' => array(
                    'ReturnUrl' => $this->getRelayUrl() . '?app_id=' . $this->app_id . '&merchant_id=' . $this->merchant_id . '&order_id=' . $order_data->id,
                    'SuccessUrl' => $this->getRelayUrl() . '?app_id=' . $this->app_id . '&merchant_id=' . $this->merchant_id . '&order_id=' . $order_data->id . '&transaction_result=success',
                    'DeclineUrl' => $this->getRelayUrl() . '?app_id=' . $this->app_id . '&merchant_id=' . $this->merchant_id . '&order_id=' . $order_data->id . '&transaction_result=failure',
                    'WebhookUrl' => $this->getRelayUrl() . '?app_id=' . $this->app_id . '&merchant_id=' . $this->merchant_id . '&order_id=' . $order_data->id . '&transaction_result=webhook'
                )
            ),
            'CustomerInfo' => array(
                'ReceiptEmail' => ifset($email, 'value', ''),
                'Email' => ifset($email, 'value', ''),
                'Phone' => str_replace(array('++', '+8'), array('', '+7'), '+' . ifset($phone, 'value', '+')),
            ),
            'ReceiptData' => array(
                'timestamp' => date('d.m.Y H:i:s'),
                'external_id' => waString::uuid(),
                'receipt' => array(
                    'client' => array(
                        'email' => ifset($email, 'value', ''),
                        'phone' => str_replace(array('++', '+8'), array('', '+7'), '+' . ifset($phone, 'value', '+')),
                    ),
                    'company' => array(
                        'payment_address' => wa()->getRootUrl(true, true),
                        'inn' => $this->inn
                    ),
                    'total' => round($order_data->total, 2),
                    'payments' => array(
                        array(
                            'type' => $this->payment_type == 'Block' ? 2 : 1,
                            'sum' => round($order_data->total, 2)
                        )
                    )
                ),
            )
        );

        if (!$this->fz54 || $order_data['currency'] != 'RUB') {
            unset($content['ReceiptData']);
        } else {
            foreach ($order_data['items'] as $item) {
                $tax = empty($this->tax) ? $item['tax_rate'] : $this->tax;
                if ($tax > 10) {
                    $tax = 'vat20';
                } else if ($tax > 0) {
                    $tax = 'vat10';
                } else {
                    $tax = 'none';
                }

                if($this->payment_type == 'Block') {
                    $tax = str_replace('vat', 'vat1', $tax);
                }

                $content['ReceiptData']['receipt']['items'][] = array(
                    'name' => mb_substr($item['name'], 0, 128),
                    'price' => round($item['price'],2),
                    'quantity' => round($item['quantity'], 3),
                    'sum' => round($item['total'], 3),
                    'payment_method' => $this->payment_type == 'Block' ? 'full_prepayment' : 'full_payment',
                    'payment_object' => $item['type'] == 'product' ? 'commodity' : 'service',
                    'vat' => array(
                        'type' => $tax
                    )
                );
            }

            if (!empty($order_data['shipping'])) {
                $tax = $this->tax_delivery;
                if ($tax > 10) {
                    $tax = 'vat20';
                } else if ($tax > 0) {
                    $tax = 'vat10';
                } else {
                    $tax = 'none';
                }

                if($this->payment_type == 'Block') {
                    $tax = str_replace('vat', 'vat1', $tax);
                }
                $content['ReceiptData']['receipt']['items'][] = array(
                    'name' => 'Доставка',
                    'price' => round($order_data['shipping'],2),
                    'quantity' => 1,
                    'sum' => round($order_data['shipping'], 3),
                    'payment_method' => $this->payment_type == 'Block' ? 'full_prepayment' : 'full_payment',
                    'payment_object' => 'service',
                    'vat' => array(
                        'type' => $tax
                    )
                );
            }
        }

        foreach ($content['CustomerInfo'] as $k => $v) {
            if ($v == '') {
                unset($content['CustomerInfo'][$k]);
            }
        }
        if (empty($content['CustomerInfo'])) {
            unset($content['CustomerInfo']);
        }

        return $content;
    }

    public function apiRefundArray($transaction_raw_data, $order_data)
    {
        $contact = new waContact($order_data->contact_id);
        $email = $contact->getFirst('email');
        $phone = $contact->getFirst('phone');
        $content = array(
            'TransactionId' => $transaction_raw_data['transaction']['native_id'],
            'Amount' => (string)round($order_data->total, 2),
            'Currency' => (string)$this->currency,
            'ReceiptData' => array(
                'timestamp' => date('d.m.Y H:i:s'),
                'external_id' => waString::uuid(),
                'receipt' => array(
                    'client' => array(
                        'email' => ifset($email, 'value', ''),
                        'phone' => str_replace(array('++', '+8'), array('', '+7'), '+' . ifset($phone, 'value', '+')),
                    ),
                    'company' => array(
                        'payment_address' => wa()->getRootUrl(true, true),
                        'inn' => $this->inn
                    ),
                    'total' => round($order_data->total, 2),
                    'payments' => array(
                        array(
                            'type' => 1,
                            'sum' => round($order_data->total, 2)
                        )
                    )
                ),
            ),
            'WebhookUrl' => $this->getRelayUrl() . '?app_id=' . $this->app_id . '&merchant_id=' . $this->merchant_id . '&order_id=' . $order_data->id . '&transaction_result=webhook'
        );

        if (!$this->fz54 || $order_data['currency'] != 'RUB') {
            unset($content['ReceiptData']);
        } else {
            $total = 0;
            $refund_items = waRequest::post('refund_items', array());
            foreach ($order_data['items'] as $item) {
                $tax = empty($this->tax) ? $item['tax_rate'] : $this->tax;
                if ($tax > 10) {
                    $tax = 'vat20';
                } else if ($tax > 0) {
                    $tax = 'vat10';
                } else {
                    $tax = 'none';
                }

                if(waRequest::post('refund_mode') == 'full') {
                    $content['ReceiptData']['receipt']['items'][] = array(
                        'name' => mb_substr($item['name'], 0, 128),
                        'price' => round($item['price'], 2),
                        'quantity' => round($item['quantity'], 3),
                        'sum' => round($item['total'], 3),
                        'payment_method' => 'full_payment',
                        'payment_object' => $item['type'] == 'product' ? 'commodity' : 'service',
                        'vat' => array(
                            'type' => $tax
                        )
                    );
                    $total += round($item['total'], 3);
                } else {
                    $refund_id = $item['parent_id'] == NULL ? $item['id'] : $item['parent_id'];
                    if(ifset($refund_items, $refund_id, 'refund', 'off') == 'on' && ifset($refund_items, $refund_id, 'quantity', 0) > 0) {
                        $content['ReceiptData']['receipt']['items'][] = array(
                            'name' => mb_substr($item['name'], 0, 128),
                            'price' => round($item['price'], 2),
                            'quantity' => round($refund_items[$refund_id]['quantity'], 3),
                            'sum' => round(round($item['price'], 2) * round($refund_items[$refund_id]['quantity'], 3), 2),
                            'payment_method' => 'full_payment',
                            'payment_object' => $item['type'] == 'product' ? 'commodity' : 'service',
                            'vat' => array(
                                'type' => $tax
                            )
                        );
                        $total += round(round($item['price'], 2) * round($refund_items[$refund_id]['quantity'], 3), 2);
                    }
                }
            }

            if (!empty($order_data['shipping']) && waRequest::post('refund_mode') == 'full') {
                $tax = $this->tax_delivery;
                if ($tax > 10) {
                    $tax = 'vat20';
                } else if ($tax > 0) {
                    $tax = 'vat10';
                } else {
                    $tax = 'none';
                }

                $content['ReceiptData']['receipt']['items'][] = array(
                    'name' => 'Доставка',
                    'price' => round($order_data['shipping'],2),
                    'quantity' => 1,
                    'sum' => round($order_data['shipping'], 2),
                    'payment_method' => 'full_payment',
                    'payment_object' => 'service',
                    'vat' => array(
                        'type' => $tax
                    )
                );
                $total += round($order_data['shipping'], 2);
            }

            if(waRequest::post('refund_mode') != 'full') {
                $content['Amount'] = (string)$content['ReceiptData']['receipt']['total'] = $content['ReceiptData']['receipt']['payments'][0]['sum'] = $total;
            }
        }
        return $content;
    }

    public function apiCreate($order_data)
    {
        $content = $this->apiCreateArray($order_data);
        return $this->query('/webpayments/create', $content, waNet::METHOD_POST, 'webpay');
    }

    public function apiRefund($content)
    {
        return $this->query('/payments/refund', $content);
    }

    public function payment($payment_form_data, $order_data, $auto_submit = false)
    {
        if ($this->form_type == 'form') {
            $form_url = $this->apiCreate($order_data);
            $view = wa()->getView();

            if (is_string($form_url)) {
                $view->assign('url', $form_url);
                $view->assign('auto_submit', $auto_submit);
                $view->assign('settings', $this);
                return $view->fetch($this->path . '/templates/payment.html');
            }
        } else {
            $view = wa()->getView();
            $view->assign('settings', $this);
            $cart = json_encode($this->apiCreateArray($order_data), JSON_UNESCAPED_UNICODE);
            $view->assign('cart', $cart);
            return $view->fetch($this->path . '/templates/widget.html');
        }
    }

    protected function callbackInit($request)
    {
        if ($this->log) {
            $headers = getallheaders();
            $body = file_get_contents('php://input');
            waLog::dump([$headers, $body], 'payselection.' . $this->app_id . '.' . $this->merchant_id . '.777.log');
        }
        $this->app_id = waRequest::get('app_id', '');
        $this->merchant_id = waRequest::get('merchant_id', '');
        $this->order_id = waRequest::get('order_id', '');
        return parent::callbackInit($request);
    }

    protected function callbackHandler($request)
    {
        if ($this->log) {
            $headers = getallheaders();
            $body = file_get_contents('php://input');
            waLog::dump([$headers, $body], 'payselection.' . $this->app_id . '.' . $this->merchant_id . '.777.log');
        }
        switch (waRequest::get('transaction_result', '')) {
            case 'webhook':
                $headers = getallheaders();
                $body = file_get_contents('php://input');
                if ($this->log) {
                    waLog::dump([$headers, $body], 'payselection.' . $this->app_id . '.' . $this->merchant_id . '.log');
                }
                if ($this->getWebhookSignature() != ifset($headers, 'X-WEBHOOK-SIGNATURE', '')) {
                    throw new Exception('Incorrect signature', 403);
                } else {
                    $req = json_decode($body, 1);
                    $transaction_data = $this->formalizeData($req);
                    $event = ifset($req, 'Event', '');
                    switch ($event) {
                        case 'Refund':
                            $transaction_data = $this->saveTransaction($transaction_data);
                            $app_payment_method = self::CALLBACK_REFUND;
                            $this->execAppCallback($app_payment_method, $transaction_data);
                            break;
                        case 'Cancel':
                            $transaction_data = $this->saveTransaction($transaction_data);
                            $app_payment_method = self::CALLBACK_CANCEL;
                            $this->execAppCallback($app_payment_method, $transaction_data);
                            break;
                        case 'Block':
                            $transaction_data = $this->saveTransaction($transaction_data);
                            $app_payment_method = self::CALLBACK_AUTH;
                            $this->execAppCallback($app_payment_method, $transaction_data);
                            break;
                        case 'Payment':
                            $transaction_data = $this->saveTransaction($transaction_data);
                            $app_payment_method = self::CALLBACK_CAPTURE;
                            $this->execAppCallback($app_payment_method, $transaction_data);
                            break;
                    }
                }
                break;
            case 'failure':
                wa()->getResponse()->redirect($this->getAdapter()->getBackUrl(waAppPayment::URL_DECLINE));
                break;
            case 'success':
            default:
                wa()->getResponse()->redirect($this->getAdapter()->getBackUrl(waAppPayment::URL_SUCCESS));
                break;
        }
    }

    protected function formalizeData($transaction_raw_data)
    {
        $transaction_data = parent::formalizeData($transaction_raw_data);
        $transaction_data = array_merge(
            $transaction_data,
            array(
                'type' => null,
                'native_id' => $transaction_raw_data['TransactionId'],
                'amount' => $transaction_raw_data['Amount'],
                'currency_id' => $transaction_raw_data['Currency'],
                'result' => 1,
                'order_id' => $this->order_id,
                'view_data' => '',
            )
        );
        $event = ifset($transaction_raw_data, 'Event', '');
        switch ($event) {
            case 'Block':
                $transaction_data['type'] = self::OPERATION_AUTH_ONLY;
                $transaction_data['state'] = self::STATE_AUTH;
                $transaction_data['view_data'] = _w('Средства заморожены, требуется подтверждение списания');
                break;
            case 'Payment':
                $transaction_data['type'] = self::OPERATION_CAPTURE;
                $transaction_data['state'] = self::STATE_CAPTURED;
                $transaction_data['view_data'] = _w('Заказ оплачен');
                break;
            case 'Refund':
                $transaction_data['type'] = self::OPERATION_REFUND;
                $transaction_data['state'] = self::STATE_REFUNDED;
                $transaction_data['view_data'] = _w('Оплата возвращена клиенту');
                break;
            case 'Cancel':
                $transaction_data['type'] = self::OPERATION_CANCEL;
                $transaction_data['state'] = self::STATE_CANCELED;
                $transaction_data['view_data'] = _w('Возврат средств клиенту');
                break;
        }
        if ($this->log) {
            waLog::dump($transaction_data, 'payselection.' . $this->merchant_id . '.transaction.debug.log');
        }
        return $transaction_data;
    }

    public function cancel($transaction_raw_data)
    {
        if (!empty($transaction_raw_data['transaction']['native_id'])) {
            $content = array(
                'TransactionId' => $transaction_raw_data['transaction']['native_id'],
                'Amount' => (string)round($transaction_raw_data['transaction']['amount'], 2),
                'Currency' => $transaction_raw_data['transaction']['currency_id'],
                'WebhookUrl' => $this->getRelayUrl() . '?app_id=' . $this->app_id . '&merchant_id=' . $this->merchant_id . '&order_id=' . $transaction_raw_data['transaction']['order_id'] . '&transaction_result=webhook'
            );
            $res = $this->query('/payments/cancellation', $content);
            sleep(2);
            return empty($res['TransactionId']);
        }
    }

    public function capture($transaction_raw_data) {
        if (!empty($transaction_raw_data['transaction']['native_id'])) {
            $content = array(
                'TransactionId' => $transaction_raw_data['transaction']['native_id'],
                'Amount' => (string)round($transaction_raw_data['transaction']['amount'], 2),
                'Currency' => $transaction_raw_data['transaction']['currency_id'],
                'WebhookUrl' => $this->getRelayUrl() . '?app_id=' . $this->app_id . '&merchant_id=' . $this->merchant_id . '&order_id=' . $transaction_raw_data['transaction']['order_id'] . '&transaction_result=webhook'
            );
            $res = $this->query('/payments/charge', $content);
            sleep(2);
            return empty($res['TransactionId']);
        }
    }

    public function refund($transaction_raw_data)
    {
        if($this->app_id == 'shop') {
            $order_data = shopPayment::getOrderData($transaction_raw_data['transaction']['order_id'], $this);
            $content = $this->apiRefundArray($transaction_raw_data, $order_data);
            $res = $this->apiRefund($content);
            sleep(2);
            return empty($res['TransactionId']);
        }
    }
}

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}