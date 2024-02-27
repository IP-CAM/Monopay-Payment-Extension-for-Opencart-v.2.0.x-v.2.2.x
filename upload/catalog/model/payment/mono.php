<?php

class ModelPaymentMono extends Model {
    private $CURRENCY_CODE = [
        'UAH' => 980,
        'EUR' => 978,
        'USD' => 840,
    ];

    public function getMethod($address, $total) {
        $this->load->language('payment/mono');

        $default_currency_code = $this->config->get('config_currency');
        if (!$default_currency_code || $default_currency_code === null || !array_key_exists($default_currency_code, $this->CURRENCY_CODE)) {
            return [];
        }
        $mono_geo_zone = $this->config->get('mono_geo_zone_id');
        if ($mono_geo_zone == '0') {
            $show_plata = true;
        } else {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$mono_geo_zone . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
            if ($query->num_rows) {
                $show_plata = true;
            } else {
                $show_plata = false;
            }
        }
        if (!$show_plata) {
            return [];
        }

        return [
            'code' => 'mono',
            'terms' => '',
            'title' => $this->language->get('text_title') . '<img src="/image/plata_light_bg.svg"  style="width: 120px;margin-left: 5px;display: inline-block; vertical-align: middle;" alt="plata by mono"/>',
            'sort_order' => $this->config->get('mono_sort_order')
        ];
    }

    public function addOrder($args) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mono_orders` WHERE OrderId = '" . (int)$args['order_id'] . "'");

        if ($query->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "mono_orders` SET SecretKey = '" . $this->db->escape($args['randKey']) . "', InvoiceId = '" . $this->db->escape($args['InvoiceId']) . "' WHERE OrderId = '" . (int)$args['order_id'] . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "mono_orders` (InvoiceId, OrderId, SecretKey) VALUES('" . $args['InvoiceId'] . "'," . $args['order_id'] . ",'" . $args['randKey'] . "')");
        }
    }

    public function getInvoiceId($order_id) {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mono_orders` WHERE OrderId = '" . $order_id . "'");

        return $q->num_rows ? $q->row : false;
    }

    public function getOrderInfo($invoice_id) {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mono_orders` WHERE InvoiceId = '" . $this->db->escape($invoice_id) . "'");

        return $q->num_rows ? $q->row : false;
    }

    public function getProducts(array $product_ids) {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product` WHERE product_id IN (" . implode(",", $product_ids) . ")");

        return $q->num_rows ? $q->rows : [];
    }

    public function getTotals($order_id) {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id =  '" . $order_id . "'");

        return $q->num_rows ? $q->rows : [];
    }

    public function getOrderProducts($order_id) {
        return $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'")->rows;
    }


    public function InvoiceInsert($invoice_d, $order_id, $payment_type, $order_amount, $payment_amount_refunded, $payment_amount_final, $status, $order_ccy, $payment_amount) {
        $sql = "INSERT INTO `" . DB_PREFIX . "monopay_invoice` (invoice_id, order_id, payment_type, order_amount, 
        order_ccy, payment_amount, payment_amount_refunded, payment_amount_final, status) 
                VALUES(" . sprintf("'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'", $this->db->escape($invoice_d),
                $this->db->escape($order_id), $this->db->escape($payment_type), $this->db->escape($order_amount),
                $this->db->escape($order_ccy), $this->db->escape($payment_amount), $this->db->escape($payment_amount_refunded),
                $this->db->escape($payment_amount_final), $this->db->escape($status)) . ")";

        $this->db->query($sql);
    }

    public function InvoiceSelectByOrderId($order_id) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "monopay_invoice` WHERE order_id = " . $this->db->escape($order_id) . " ORDER BY created DESC";

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function InvoiceGetLastByOrderId($order_id) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "monopay_invoice` WHERE order_id = " . $this->db->escape($order_id) . " ORDER BY created DESC LIMIT 1";

        $query = $this->db->query($sql);

        if ($query->num_rows) {
            return $query->row;
        } else {
            return null;
        }
    }


    public function InvoiceUpdateStatus($invoice_id, $status, $final_amount, $payment_amount, $payment_amount_refunded, $failure_reason) {
        $sql = "UPDATE `" . DB_PREFIX . "monopay_invoice` 
                SET modified = now(), status = '" . $this->db->escape($status) . "', payment_amount_final = '" . $this->db->escape($final_amount) . "', 
                failure_reason = '" . $this->db->escape($failure_reason) . "', 
                payment_amount = '" . $this->db->escape($payment_amount) . "', 
                payment_amount_refunded = '" . $this->db->escape($payment_amount_refunded) . "' 
                                WHERE invoice_id = '" . $this->db->escape($invoice_id) . "'";

        $this->db->query($sql);
    }

    public function InsertLogs($key, $value, $module_version) {
        $sql = "INSERT INTO `" . DB_PREFIX . "monopay_logs` (`key`, `value`, `module_version`) 
                VALUES " . sprintf("('%s', '%s', '%s')", $this->db->escape($key), $this->db->escape($value), $this->db->escape($module_version));

        $this->db->query($sql);
    }

    public function DeleteLogs() {
        $sql = "DELETE
FROM `" . DB_PREFIX . "monopay_logs`
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 2 DAY);
";

        $this->db->query($sql);
    }

    public function SelectLogs($from, $limit, $offset) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "monopay_logs` 
        WHERE timestamp >= '" . $this->db->escape($from) . "' 
        ORDER BY timestamp DESC 
        LIMIT " . $this->db->escape($limit) . " 
        OFFSET " . $this->db->escape($offset);

        return $this->db->query($sql)->rows;
    }

    public function InvoiceGetById($invoice_id) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "monopay_invoice` WHERE invoice_id = '" . $this->db->escape($invoice_id) . "'";

        $query = $this->db->query($sql);

        return $query->row;
    }

    public function UpdateSettingsCurrencyValue($currency_code, $currency_value) {
        $sql = "UPDATE `" . DB_PREFIX . "currency` SET value = '" . $this->db->escape($currency_value) . "' WHERE code = '" . $this->db->escape($currency_code) . "'";

        $this->db->query($sql);
    }

//    deprecated
    public function getOrder($order_id) {
        $qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mono_orders` WHERE `Orderid` = '" . (int)$order_id . "' LIMIT 1");

        if ($qry->num_rows) {
            $order = $qry->row;
            return $order;
        } else {
            return false;
        }
    }
}