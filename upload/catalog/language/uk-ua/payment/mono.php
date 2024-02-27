<?php
// Text
$_['text_title'] = 'Оплата карткою, Apple Pay, Google Pay';
$_['text_general_error'] = 'Помилка! Щось пішло не так...';
$_['text_currency_error'] = 'Помилка! Оплата приймається лише в гривні, доларах та євро';
$_['text_status_success'] ='plata by mono: Оплата пройшла успішно <button id="search-btn" class="btn btn-primary mt-3" onclick="const getParameterByName = (name, url = window.location.href) => (match = new RegExp(\'[?&]\' + name.replace(/[[\]]/g, \'\\\\$&\') + \'(=([^&#]*)|&|#|$)\').exec(url)) ? decodeURIComponent((match[2] || \'\').replace(/\+/g, \' \')) : null;window.open(\'/admin/index.php?route=payment/mono/order_info&token=\'+getParameterByName(\'token\')+\'&order_id=\'+getParameterByName(\'order_id\'))">Управління платежем</button>';
$_['text_status_created'] = 'plata by mono: Інвойс створено, але ще не оплачено';
$_['text_status_refund'] = 'plata by mono: Здійснено повернення';
$_['text_status_cancelled'] = 'plata by mono: Оплата не пройшла, причина — %s';
$_['text_status_process'] = 'plata by mono: Оплата в обробці';
$_['text_status_hold'] = 'plata by mono: Оплату захолдовано  <button id="search-btn" class="btn btn-primary mt-3" onclick="const getParameterByName = (name, url = window.location.href) => (match = new RegExp(\'[?&]\' + name.replace(/[[\]]/g, \'\\\\$&\') + \'(=([^&#]*)|&|#|$)\').exec(url)) ? decodeURIComponent((match[2] || \'\').replace(/\+/g, \' \')) : null;window.open(\'/admin/index.php?route=payment/mono/order_info&token=\'+getParameterByName(\'token\')+\'&order_id=\'+getParameterByName(\'order_id\'))">Управління холдом</button>';
$_['text_status_hold_cancelled'] = 'plata by mono: Холд відмінено';