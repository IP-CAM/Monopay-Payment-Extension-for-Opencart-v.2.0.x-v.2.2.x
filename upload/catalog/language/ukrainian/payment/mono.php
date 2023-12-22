<?php
// Text
$_['text_title'] = 'Оплата онлайн з ';
$_['text_general_error'] = 'Помилка! Щось пішло не так...';
$_['text_currency_error'] = 'Помилка! Оплата приймається лише в гривні, доларах та євро';
$_['text_status_success'] ='monopay: Оплата пройшла успішно <button id="search-btn" class="btn btn-primary mt-3" onclick="const getParameterByName = (name, url = window.location.href) => (match = new RegExp(\'[?&]\' + name.replace(/[[\]]/g, \'\\\\$&\') + \'(=([^&#]*)|&|#|$)\').exec(url)) ? decodeURIComponent((match[2] || \'\').replace(/\+/g, \' \')) : null;window.open(\'/admin/index.php?route=payment/mono/order_info&token=\'+getParameterByName(\'token\')+\'&order_id=\'+getParameterByName(\'order_id\'))">Управління платежем</button>';
$_['text_status_created'] = 'monopay: Інвойс створено, але ще не оплачено';
$_['text_status_refund'] = 'monopay: Здійснено повернення';
$_['text_status_cancelled'] = 'monopay: Оплата не пройшла, причина — %s';
$_['text_status_process'] = 'monopay: Оплата в обробці';
$_['text_status_hold'] = 'monopay: Оплату захолдовано  <button id="search-btn" class="btn btn-primary mt-3" onclick="const getParameterByName = (name, url = window.location.href) => (match = new RegExp(\'[?&]\' + name.replace(/[[\]]/g, \'\\\\$&\') + \'(=([^&#]*)|&|#|$)\').exec(url)) ? decodeURIComponent((match[2] || \'\').replace(/\+/g, \' \')) : null;window.open(\'/admin/index.php?route=payment/mono/order_info&token=\'+getParameterByName(\'token\')+\'&order_id=\'+getParameterByName(\'order_id\'))">Управління холдом</button>';
$_['text_status_hold_cancelled'] = 'monopay: Холд відмінено';