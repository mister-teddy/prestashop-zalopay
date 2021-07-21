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
class ZaloPayRedirectModuleFrontController extends ModuleFrontController
{
  /**
   * @see FrontController::postProcess()
   */
  public function postProcess()
  {
    Logger::addLog('[ZaloPay][Redirect] Trigger redirect: ' . json_encode($_REQUEST));

    $cart = $this->context->cart;
    if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
      $this->redirectToOrder();
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

    if ($_REQUEST["status"] != 1) {
      $this->redirectToOrder();
    }

    $customer = $this->context->customer;
    $appid = Configuration::get('ZALOPAY_APPID');
    $key2 = Configuration::get('ZALOPAY_KEY2');

    $hmacinput = $appid . "|" . $_REQUEST["apptransid"] . "|" . $_REQUEST["pmcid"] . "|" . $_REQUEST["bankcode"] . "|" . $_REQUEST["amount"] . "|" . $_REQUEST["discountamount"] . "|" . $_REQUEST["status"];
    $mac = hash_hmac("sha256", $hmacinput, $key2);

    $valid = $mac == $_REQUEST["checksum"];

    if ($valid) {
      // Payment success
      $customer = new Customer($cart->id_customer);
      if (!Validate::isLoadedObject($customer)) $this->redirectToOrder();
      $cart_id = explode("_", $_REQUEST["apptransid"])[1];
      $url = $this->context->link->getPageLink('order-confirmation', true, $customer->id_lang, 'key='.$customer->secure_key.'&id_cart='.$cart_id.'&id_module='.(int)$this->module->id);
      Logger::addLog('[ZaloPay][Redirect] Redirect to order confirmination: ' . $url);
      Tools::redirectLink($url);
    } else {
      $this->redirectToOrder();
    }
  }

  public function redirectToOrder()
  {
    Tools::redirect($this->context->link->getPageLink('order', true));
  }
}
