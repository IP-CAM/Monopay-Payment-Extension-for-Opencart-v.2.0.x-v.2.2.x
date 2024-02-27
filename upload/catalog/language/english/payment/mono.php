<?php
// Text
$_['text_title'] = 'Pay with card, Apple Pay, Google Pay';
$_['text_general_error'] = 'Error! Something went wrong...';
$_['text_currency_error'] = 'Error! Invalid currency, only UAH, USD and EUR are allowed';
$_['text_status_success'] = 'plata by mono: Payment was successful <button id="search-btn" class="btn btn-primary mt-3" onclick="const getParameterByName = (name, url = window.location.href) => (match = new RegExp(\'[?&]\' + name.replace(/[[\]]/g, \'\\\\$&\') + \'(=([^&#]*)|&|#|$)\').exec(url)) ? decodeURIComponent((match[2] || \'\').replace(/\+/g, \' \')) : null;window.open(\'/admin/index.php?route=payment/mono/order_info&token=\'+getParameterByName(\'token\')+\'&order_id=\'+getParameterByName(\'order_id\'))">Manage payment</button>';
$_['text_status_created'] = 'plata by mono: Invoice created but not payed yet';
$_['text_status_refund'] = 'plata by mono: Successful refund';
$_['text_status_cancelled'] = 'plata by mono: Payment failed, reason — %s';
$_['text_status_expired'] = 'invoice link expired';
$_['text_status_hold'] = 'plata by mono: Payment on hold <button id="search-btn" class="btn btn-primary mt-3" onclick="const getParameterByName = (name, url = window.location.href) => (match = new RegExp(\'[?&]\' + name.replace(/[[\]]/g, \'\\\\$&\') + \'(=([^&#]*)|&|#|$)\').exec(url)) ? decodeURIComponent((match[2] || \'\').replace(/\+/g, \' \')) : null;window.open(\'/admin/index.php?route=payment/mono/order_info&token=\'+getParameterByName(\'token\')+\'&order_id=\'+getParameterByName(\'order_id\'))">Manage hold</button>';
$_['text_status_process'] = 'plata by mono: Payment is processing';
$_['text_status_hold_cancelled'] = 'plata by mono: Hold cancelled';