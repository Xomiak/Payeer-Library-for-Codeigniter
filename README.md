# Payeer-Library-for-Codeigniter
Библиотека для подключения к сайту системы оплаты Payeer

Пример использования:
<?php
$this->load->library('Payeer');   // Подключаем библиотеку

$config = array(
  'm_shop'            => '345635860',
  'm_orderid'         => $order['id'],
  'm_amount'          => $order['price'],
  'm_curr'            => 'USD',
  'm_desc'           => $order['description']
);
$payeer = new Payeer($config);

$hash = $payeer->digital_signature();

$payAnswer = false;

if(isset($_GET['action']) && $_GET['action'] == 'payed'){
  $payAnswer = $payeer->payment_handler();  // Проверяем, прошла ли оплата
  if($payAnswer == 'success')
    echo 'Оплата прошла успешно!';
}
if($payAnswer != 'success'){
  echo $payeer->generateForm(); // выводим форму для оплаты
}
?>
