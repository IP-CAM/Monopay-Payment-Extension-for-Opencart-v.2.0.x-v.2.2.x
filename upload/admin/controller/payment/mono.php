<?php

$model = null;
$log = null;

const MONOBANK_PAYMENT_VERSION = 'Polia_2.3.1';
const VALID_STATUSES = [
    "created" => "ще не сплачено",
    "processing" => "в процесі обробки",
    "hold" => "клієнт оплатив, кошти на утриманні",
    "success" => "клієнт оплатив або холд фіналізовано",
    "failure" => "оплата не пройшла",
    "reversed" => "оплату було частково або повністю повернено",
    "expired" => "успішних спроб оплати не було і не більше буде"
];

function handleException($e, $m = null, $is_init = false) {
    global $model, $log;
    if ($is_init) {
        $log = new Log('error.log');
        $model = $m;
        return;
    }
    $e_message = $e->getMessage();
//    this is to be expected, do not log it
    if ($e_message == "Error: Duplicate key name 'monopay_invoice_order_id_index'<br />Error No: 1061<br />CREATE INDEX monopay_invoice_order_id_index ON oc_monopay_invoice (order_id);") {
        return;
    }

    if ($m == null) {
        $m = $model;
        if ($model == null) {
        }
    } else if ($model == null) {
        $model = $m;
    }
    if ($log == null) {
        $log = new Log('error.log');
    }
    if ($m != null) {
        try {
            $m->InsertLogs($e_message, json_encode([
                'version' => VERSION,
                'stack' => $e->getTrace(),
            ]), MONOBANK_PAYMENT_VERSION);
            $m->DeleteLogs();
        } catch (\Throwable $th) {
            $log->write($e_message);
            $log->write(sprintf('failed to write error in admin logs to db: %s', $th->getMessage()));
        }
    } else {
        $log->write($e_message);
    }
}

function fatalErrorHandler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting, so let it fall through to the standard PHP error handler
        return false;
    }
    try {
        throw new ErrorException($message, 0, $severity, $file, $line);
    } catch (\Throwable $th) {
        handleException($th);
    }
    return true;
}

// Set the custom error handler
set_error_handler("fatalErrorHandler");

set_exception_handler("handleException");

class ControllerPaymentMono extends Controller {
    private $error = [];
    private $rates_file_path = 'mono_rates.json';

    const RATE_CACHE_TIMEOUT_SEC = 600;

    private $CURRENCY_CODE = [
        'UAH' => 980,
        'EUR' => 978,
        'USD' => 840,
    ];

    public function __construct($registry) {
        parent::__construct($registry);

        $this->load->model('payment/mono');
        $this->load->model('localisation/currency');

        global $model;
        if (key_exists('monopay_admin_inited', $this->session->data) && $model != null) {
            return;
        }
        try {
            throw new Exception("init");
        } catch (Exception $e) {
            handleException($e, $this->model_payment_mono, true);
        }
        try {
            $this->model_payment_mono->install();
        } catch (Exception $e) {
            handleException($e, $this->model_payment_mono);
        }
        $this->session->data['monopay_admin_inited'] = true;
        $uah = $this->model_localisation_currency->getCurrencyByCode('UAH');
        if (!$uah) {
            return;
        }

//        we do not allow custom rate, so we make force update of the rate
//        it's not pretty, але маємо те шо маємо
        $default_currency_code = $this->config->get('config_currency');
        if ($default_currency_code != 'UAH') {
            $rates = $this->getRates();
            if (isset($rates['created']) && $rates['created'] >= self::RATE_CACHE_TIMEOUT_SEC) {
                $rate_buy = 0;
                foreach ($rates['rates'] as $rate) {
                    if ($rate['currencyCodeA'] == $this->CURRENCY_CODE[$default_currency_code] && $rate['currencyCodeB'] == 980) {
                        $rate_buy = $rate['rateBuy'];
                        break;
                    }
                }
                if ($rate_buy == 0) {
                    try {
                        throw new ErrorException(sprintf('Rate for currency %s not found!', $default_currency_code));
                    } catch (Exception $e) {
                        $data['error_message'] = $this->language->get('text_general_error');
                        clientHandleException($e, $this->model_payment_mono);
                        if (VERSION < '2.2.0.0') {
                            $this->response->setOutput($this->load->view('payment/mono.tpl', $data));
                        } else {
                            $this->response->setOutput($this->load->view('payment/mono', $data));
                        }
                        return;
                    }
                }
                $this->model_payment_mono->UpdateSettingsCurrencyValue('UAH', $rate_buy);
            }
        }
    }

    public function install() {
        $this->load->model('payment/mono');
        $this->model_payment_mono->install();
    }

    public function uninstall() {
        $this->load->model('payment/mono');
        $this->model_payment_mono->uninstall();
    }

    public function index() {
        $data = $this->load->language('payment/mono');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['version'] = MONOBANK_PAYMENT_VERSION;

        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('mono', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], true));
        }

        $error_message_values = ["warning", "merchant"];
        foreach ($error_message_values as $error_message_value)
            $data['error_' . $error_message_value] = (isset($this->error[$error_message_value])) ? $this->error[$error_message_value] : "";

        $data['action'] = $this->url->link('payment/mono', 'token=' . $this->session->data['token'], true);

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        $data['fiscalization_code_fields'] = ["product_id", "sku", "upc", "ean", "isbn", "mpn"];
//        sku is default for fiscalization
        $data['mono_fiscalization_code_field'] = "sku";

        $form_inputs = [
            "mono_status",
            "mono_merchant",
            'mono_geo_zone_id',
            "mono_sort_order",
            "mono_order_success_status_id",
            "mono_order_cancelled_status_id",
            "mono_order_process_status_id",
            "mono_use_holds",
            "mono_order_hold_status_id",
            "mono_destination",
        ];

        foreach ($form_inputs as $form_input) {
            $data[$form_input] = (isset($this->request->post[$form_input])) ? $this->request->post[$form_input] : $this->config->get($form_input);
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['token'] = $this->session->data['token'];
        $data['statuses'] = VALID_STATUSES;
        $data['refresh_invoices_url'] = HTTP_CATALOG . 'index.php?route=payment/mono/refresh_invoices';
        $data['login_url'] = HTTP_CATALOG . 'index.php?route=api/login';

        $data['settings_text'] = $this->language->get('settings_text');
        $data['invoices_text'] = $this->language->get('invoices_text');
        $data['refresh_invoices_btn_text'] = $this->language->get('refresh_invoices_btn_text');
        $data['all_statuses_text'] = $this->language->get('all_statuses_text');
        $data['status_text'] = $this->language->get('status_text');
        $data['created_text'] = $this->language->get('created_text');

        // API login
        $this->load->model('user/api');

        $api_info = $this->model_user_api->getApi($this->config->get('config_api_id'));
        if ($api_info) {
            $data['key'] = $api_info['key'];
        }

        if (VERSION < '2.2.0.0') {
            $this->response->setOutput($this->load->view('payment/mono.tpl', $data));
        } else {
            $this->response->setOutput($this->load->view('payment/mono', $data));
        }

    }

    private function get_invoices($status) {
        return $this->model_payment_mono->GetInvoices($status);
    }

    private function validate() {
        if (!$this->user->hasPermission('modify', 'payment/mono')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['mono_merchant']) {
            $this->error['merchant'] = $this->language->get('error_merchant');
        }

        return !$this->error;
    }

    public function order_info($somedata) {
        $orderId = $this->request->get['order_id'];
        $this->load->model('payment/mono');
        $this->load->model('sale/order');

        if ($orderId <= 0) {
//            invalid order
            return;
        }


        $invoice_db = $this->model_payment_mono->InvoiceGetLastByOrderId($orderId);
        if ($invoice_db == null) {
            try {
                $mono_order = $this->model_payment_mono->getOrder($orderId);
            } catch (Exception $e) {
                $can_skip_msg = "Error: Table 'opencart.oc_mono_orders' doesn't exist";
                if (substr($e->getMessage(), 0, strlen($can_skip_msg)) === $can_skip_msg) {
                    return;
                }
                handleException($e);
                return;
            }
            if (!$mono_order) {
//                 nothing to do here
                return;
            }
            $order_info = $this->model_sale_order->getOrder($orderId);

            $status_response = $this->getStatus($mono_order['InvoiceId']);
            $payment_type = ($mono_order['is_hold'] == 'hold') ? 'hold' : 'debit';

            $failure_reason = (isset($status_response['failureReason'])) ? $status_response['failureReason'] : '';
            $final_amount = (isset($status_response['finalAmount'])) ? $status_response['finalAmount'] : 0;
            $amount_refunded = $status_response['amount'] - $final_amount;
            switch ($status_response['status']) {
                case 'created':
                case 'hold':
                case 'failure':
                case 'success':
                case 'expired':
                    $amount_refunded = 0;
                    break;
                case 'processing':
                    if ($final_amount == 0) {
                        $amount_refunded = 0;
                        break;
                    }
            }
            $invoice_db = [
                'invoice_id' => $mono_order['InvoiceId'],
                'order_id' => $mono_order['OrderId'],
                'payment_type' => $payment_type,
                'order_ccy' => $this->CURRENCY_CODE[$order_info['currency_code']],
                'order_amount' => (int)($order_info['total'] * 100 + 0.5),
                'payment_amount' => $status_response['amount'],
                'payment_amount_refunded' => $amount_refunded,
                'payment_amount_final' => $final_amount,
                'status' => $status_response['status'],
                'failure_reason' => $failure_reason,
            ];
            $this->model_payment_mono->InvoiceInsert($invoice_db['invoice_id'], $invoice_db['order_id'],
                $invoice_db['payment_type'], $invoice_db['order_amount'], $invoice_db['payment_amount_refunded'],
                $invoice_db['payment_amount_final'], $invoice_db['status'], $invoice_db['order_ccy'], $invoice_db['payment_amount'], $invoice_db['failure_reason']);
        }

        $this->language->load('payment/mono');

        $ccy = 'UAH';
        switch ($invoice_db['order_ccy']) {
            case '840':
                $ccy = 'USD';
                break;
            case '978':
                $ccy = 'EUR';
                break;
        }

        $params = [
            'invoice_id' => $invoice_db['invoice_id'],
            'order_id' => $invoice_db['order_id'],
            'text_invoice_amount' => $this->language->get('invoice_amount'),
            'text_invoice_on_hold' => $this->language->get('invoice_on_hold'),
            'text_invoice_amount_finalized' => $this->language->get('amount_finalized'),
            'text_invoice_finalized_at' => $this->language->get('finalized_at'),
            'text_invoice_amount_refunded' => $this->language->get('invoice_amount_refunded'),
            'text_invoice_amount_to_refund' => $this->language->get('invoice_amount_to_refund'),
            'text_invoice_refund' => $this->language->get('invoice_refund'),
            'text_invoice_finalize_hold' => $this->language->get('invoice_finalize_hold'),
            'text_invoice_cancel_hold' => $this->language->get('invoice_cancel_hold'),
            'text_cancel' => $this->language->get('text_cancel'),
            'text_enter_amount' => $this->language->get('text_enter_amount'),
            'status' => $invoice_db['status'],
            'can_refund' => $invoice_db['payment_amount_final'] > 0,
            'order_amount' => sprintf('%.2f', $invoice_db['order_amount'] / 100),
            'payment_amount' => sprintf('%.2f', $invoice_db['payment_amount'] / 100),
            'payment_amount_refunded' => sprintf('%.2f', $invoice_db['payment_amount_refunded'] / 100),
            'payment_amount_final' => sprintf('%.2f', $invoice_db['payment_amount_final'] / 100),
            'token' => $this->session->data['token'],
            'payment_type' => $invoice_db['payment_type'],
            'order_ccy' => $ccy,
        ];
        if ($invoice_db['payment_type'] == 'hold') {
            $params['finalized_at'] = $invoice_db['finalized'];
            $finalized_amount = $this->getAmount($invoice_db);
            $params['finalized_amount'] = sprintf('%.2f', $finalized_amount / 100);
        }

        $params['header'] = $this->load->controller('common/header');
        $params['column_left'] = $this->load->controller('common/column_left');
        $params['footer'] = $this->load->controller('common/footer');

        if (VERSION < '2.2.0.0') {
            $this->response->setOutput($this->load->view('payment/mono_payment.tpl', $params));
        } else {
            $this->response->setOutput($this->load->view('payment/mono_payment', $params));
        }
    }

    public function cancel($somedata) {
        $invoice_id = $this->request->post['invoice_id'];
        $data = [
            'invoiceId' => $invoice_id,
        ];
        $invoice_db = $this->model_payment_mono->InvoiceGetById($invoice_id);

//        we can cancel hold or cancel payment, this condition determines that we need to cancel payment
        if ($invoice_db['payment_amount_final'] > 0) {
            $refund_amount = (int)($this->request->post['mono_amount'] * 100 + 0.5);
            if ($refund_amount > $invoice_db['payment_amount_final']) {
                return $this->response->setOutput(json_encode(['errText' => sprintf('Refund amount should be less or equal to %.2f', $invoice_db['payment_amount_final'])]));
            }

            $data['extRef'] = (string)$this->request->post['order_id'];
            $data['amount'] = $refund_amount;
        }

        $token = $this->config->get('mono_merchant');
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.monobank.ua/api/merchant/invoice/cancel',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-Token: ' . $token,
                'X-Cms: Opencart',
                'X-Cms-Version: ' . VERSION,
            ),
        ));


        $response = curl_exec($curl);
        curl_close($curl);

        if (!$response) {
            throw new ErrorException('No response');
        }

        $this->load->model('payment/mono');

        return $this->response->setOutput($response);
    }

    public function finalize_hold() {
        $this->response->addHeader('Content-Type: application/json');

        $invoice_id = $this->request->post['invoice_id'];
        $data = [
            'invoiceId' => $invoice_id,
            'amount' => (int)($this->request->post['amount'] * 100 + 0.5),
        ];

        $token = $this->config->get('mono_merchant');
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.monobank.ua/api/merchant/invoice/finalize',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-Token: ' . $token,
                'X-Cms: Opencart',
                'X-Cms-Version: ' . VERSION,
            ),
        ));

        $response = curl_exec($curl);

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!$response) {
            throw new ErrorException('No response');
        }

        if ($httpcode == 200) {
            $this->model_payment_mono->InvoiceFinalizeHold($invoice_id);
        }
        return $this->response->setOutput($response);
    }

    public function invoices() {
        $this->response->addHeader('Content-Type: application/json');

        $this->load->model('payment/mono');
        $this->load->model('sale/order');

        $status = $this->request->get['status'] ?? "";
        if ($status && !key_exists($status, VALID_STATUSES)) {
            http_response_code(400);
            return $this->response->setOutput(json_encode([
                'err' => "invalid 'status'",
            ]));
        }

        $invoices = $this->get_invoices($status);
        return $this->response->setOutput(json_encode([
            'invoices' => $invoices,
        ]));
    }

    public function getStatus($invoice_id) {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.monobank.ua/api/merchant/invoice/status?invoiceId=' . $invoice_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-Token: ' . $this->config->get('mono_merchant'),
                'X-Cms: Opencart',
                'X-Cms-Version: ' . VERSION,
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    public function getAmount(array $invoice) {
        if ($invoice['payment_type'] == 'debit') {
            return $invoice['payment_amount'];
        }
        $amount_held = $invoice['payment_amount_final'];
        if ($invoice['payment_amount_refunded'] > 0) {
            $amount_held += $invoice['payment_amount_refunded'];
        }
        return $amount_held;
    }

    public function getRates() {
        $rates_data = $this->readSettingsFromFile($this->rates_file_path);
        if (isset($rates_data['created']) && (time() - (int)$rates_data['created']) < self::RATE_CACHE_TIMEOUT_SEC) {
            return $rates_data;
        }
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.monobank.ua/bank/currency',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-Cms: Opencart',
                'X-Cms-Version: ' . VERSION,
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!$response) {
            throw new ErrorException('No response');
        }

        if ($httpcode == 429) {
            return $rates_data;
        }

        $rates_data = [
            'created' => time(),
            'rates' => json_decode($response, true),
        ];
        $this->writeSettingsToFile($this->rates_file_path, $rates_data);
        return $rates_data;
    }

    function readSettingsFromFile($file_path) {
        $settings = [];

        // Check if the file exists
        if (file_exists($file_path)) {
            // Read the file contents
            $file_contents = file_get_contents($file_path);

            // Parse the contents into an associative array (assuming JSON format)
            $settings = json_decode($file_contents, true);
        }

        return $settings;
    }

    function writeSettingsToFile($file_path, $settings) {
        // Convert the settings array to a JSON string
        $file_contents = json_encode($settings, JSON_PRETTY_PRINT);

        // Write the contents to the file
        file_put_contents($file_path, $file_contents);
    }
}
