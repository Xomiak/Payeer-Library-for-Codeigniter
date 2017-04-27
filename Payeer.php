<?php
/**
 * Payeer Payment Library
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is available through the world-wide-web at this URL:
 * https://choosealicense.com/licenses/gpl-3.0/
 *
 * @category        Payeer
 * @package         codeigniter/libraries
 * @version         1.0
 * @author          XomiaK <xomiak@rap.org.ua>
 * @copyright       Copyright (c) 2017 XomiaK
 * @license         https://choosealicense.com/licenses/gpl-3.0/
 *
 * EXTENSION INFORMATION
 *
 * PAYEER       https://payeer.com
 *
 */
class Payeer
{
    private $m_shop = '0';                                      // id мерчанта
    private $m_orderid = '0';                                   // номер счета в системе учета мерчанта
    private $m_amount = '1';                                    // сумма счета с двумя знаками после точки
    private $m_curr = 'USD';                                    // валюта счета
    private $m_desc = '';                                       // описание счета, в дальнейшем, закодированное с помощью алгоритма base64 (в конструкторе)
    private $m_key = '';                                        // секретный ключ
    private $m_api_url = 'https://payeer.com/merchant/';        // путь к API, куда отправляем данные
    private $m_params = false;
    private $m_encryption_key = '';                             // Ключ для шифрования дополнительных параметров

    /**
     * Constructor.
     *
     * @param string $config
     *
     */
    public function __construct()
    {
        // Присваеваем приватным переменным переданные в конструктор настройки
        if (isset($config['m_shop'])) $this->m_shop = $config['m_shop'];
        if (isset($config['m_orderid'])) $this->m_orderid = $config['m_orderid'];
        if (isset($config['m_amount'])) $this->m_amount = number_format($config['m_amount'], 2, '.', '');
        if (isset($config['m_curr'])) $this->m_curr = $config['m_curr'];
        if (isset($config['m_key'])) $this->m_key = $config['m_key'];
        if (isset($config['m_encryption_key'])) $this->m_encryption_key = $config['m_encryption_key'];
        if (isset($config['m_api_url'])) $this->m_api_url = $config['m_api_url'];

        if (isset($config['m_desc'])) $payDescr = $config['m_desc'];

        $this->m_desc = base64_encode($payDescr);

        //Формируем массив дополнительных параметров
        $arParams = array(
            'success_url' => 'https://'.$_SERVER['SERVER_NAME'].'/payment/?action=payed&type=payeer&status=success',
            'fail_url' => 'https://'.$_SERVER['SERVER_NAME'].'/payment/?action=payed&type=payeer&status=fail',
            'status_url' => 'https://'.$_SERVER['SERVER_NAME'].'/payment/?action=payed&type=payeer&status=pending',
        );

        //Формируем ключ для шифрования
        $key = md5($this->m_encryption_key.$this->m_orderid);

        //$this->m_params = urlencode(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, json_encode($arParams), MCRYPT_MODE_ECB)));
    }

    /**
     * Формируем подпись
     *
     *
     * @return string
     */
    public function digital_signature()
    {
        $arHash = array(
            $this->m_shop,
            $this->m_orderid,
            $this->m_amount,
            $this->m_curr,
            $this->m_desc
        );

        //vd($arHash);
        // Добавляем доп. параметры, если Вы их задали

        if ($this->m_params) {
            $arHash[] = $this->m_params;
        }

        // Добавляем секретный ключ
        $arHash[] = $this->m_key;

        // Формируем подпись
        //vd($arHash);
        //vd(implode(":", $arHash));
        $sign = strtoupper(hash('sha256', implode(":", $arHash)));
        return $sign;
    }

    /**
     * Обработчик платежа
     *
     *
     * @return string
     */
    public function payment_handler()
    {
        if(isset($_GET) && !$_POST) {           // Если результат вернулся через $_GET:
            $_POST = $_GET;
        }
        //vd($_POST);
        if (isset($_POST['m_operation_id']) && isset($_POST['m_sign'])) {
            // Формируем массив для генерации подписи
            $arHash = array(
                $_POST['m_operation_id'],
                $_POST['m_operation_ps'],
                $_POST['m_operation_date'],
                $_POST['m_operation_pay_date'],
                $_POST['m_shop'],
                $_POST['m_orderid'],
                $_POST['m_amount'],
                $_POST['m_curr'],
                $_POST['m_desc'],
                $_POST['m_status']
            );
            // Если были переданы дополнительные параметры, то добавляем их в массив
            if (isset($_POST['m_params'])) {
                $arHash[] = $_POST['m_params'];
            }
            // Добавляем в массив секретный ключ
            $arHash[] = $this->m_key;
            // Формируем подпись
            $sign_hash = strtoupper(hash('sha256', implode(':', $arHash)));

            // Если подписи совпадают и статус платежа “Выполнен”
            if ($_POST['m_sign'] == $sign_hash && $_POST['m_status'] == 'success') {
                // Возвращаем, что платеж был успешно обработан
                return 'success';
            }
            // В противном случае возвращаем ошибку
            return 'error';
        }
    }

    /**
     * generateForm
     *
     *
     * @return string
     */
    public function generateForm()
    {
        $html = '';

        //Формируем подпись
        $sign = $this->digital_signature();

        $html .= '
        <form method="post" action="'.$this->m_api_url.'">
            <input type="hidden" name="m_shop" value="' . $this->m_shop . '">
            <input type="hidden" name="m_orderid" value="' . $this->m_orderid . '">
            <input type="hidden" name="m_amount" value="' . $this->m_amount . '">
            <input type="hidden" name="m_curr" value="' . $this->m_curr . '">
            <input type="hidden" name="m_desc" value="' . $this->m_desc . '">
            <input type="hidden" size="100" name="m_sign" value="' . $sign . '">
            <!--<input type="hidden" name="form[ps]" value="2609">
            <input type="hidden" name="form[curr[2609]]" value="USD">-->';
        if($this->m_params) {
            $html .= '<input type="hidden" name="m_params" value="' . $this->m_params . '">';
        }
        $html .='<!-- <input type="hidden" name="m_cipher_method" value="AES-256-CBC">-->
            <input type="submit" name="m_process" value="'.getLine('Оплатить').'"/>
        </form>';

        return $html;
    }

}
