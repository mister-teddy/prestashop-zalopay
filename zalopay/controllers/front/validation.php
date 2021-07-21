<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
class ZaloPayValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'zalopay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $order = $this->createOrder();
        print_r($order);

        if ($order['return_code'] == 1) {
            $url = $order['order_url'];
            Tools::redirect($url);
        } else {
            $this->context->smarty->assign([
                'result' => $order,
            ]);
            Tools::redirect('index.php?controller=order&step=1');
        }
    }

    public function createOrder()
    {
        $cart = $this->context->cart;
        $customer = $this->context->customer;

        $appid = Configuration::get('ZALOPAY_APPID');
        $key1 = Configuration::get('ZALOPAY_KEY1');

        $createOrderUrl = "https://sb-openapi.zalopay.vn/v2/create";
        $item = array_map(function ($product) {
            return [
                'id' => $product['id_product'],
                'name' => $product['name'],
                'quantity' => $product['cart_quantity'],
                'price' => $product['price']
            ];
        }, $cart->getProducts());
        $title = implode(", ", array_column($item, 'name'));
        $callback = $this->context->link->getModuleLink($this->module->name, 'callback', array(), true);
        $redirect = $this->context->link->getModuleLink($this->module->name, 'redirect', array(), true);
        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $payload = [
            "app_id" => $appid,
            "app_user" => $customer->email,
            "app_time" => round(microtime(true) * 1000),
            "amount" => $total,
            "app_trans_id" => date("ymd") . time() . '_' . $cart->id,
            "callback_url" => $callback,
            "order_type" => "GOODS",
            "embed_data" => json_encode([
                'redirecturl' => $redirect,
                'cart_id' => $cart->id,
                'total' => $total,
                'currency_id' => $currency->id,
                'secure_key' => $customer->secure_key
            ]),
            "item" => json_encode($item),
            "bank_code" => "",
            "title" => $title,
            "description" => sprintf("Đồng Hồ Phát Tài - Thanh toán đơn hàng của %s %s", $customer->id_gender == 1 ? "anh" : "chị", $customer->firstname),
            "email" => $customer->email,
        ];
        Logger::addLog('[ZaloPay] Send payload: ' . json_encode($payload));

        $hmacinput = $appid . "|" . $payload["app_trans_id"] . "|" . $payload["app_user"] . "|" . $payload["amount"] . "|" . $payload["app_time"] . "|" . $payload["embed_data"] . "|" . $payload["item"];
        $mac = hash_hmac("sha256", $hmacinput, $key1);
        $payload["mac"] = $mac;

        $result = $this->callAPI("POST", $createOrderUrl, $payload);
        Logger::addLog('[ZaloPay] Received result: ' . json_encode($result));

        return $result;
    }

    public function callAPI($method, $url, $data = false)
    {
        $curl = curl_init();

        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);

                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        // Optional Authentication:
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);

        curl_close($curl);

        if (!$result) {
            die('Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl));
        }
        return json_decode($result, true);
    }
}
