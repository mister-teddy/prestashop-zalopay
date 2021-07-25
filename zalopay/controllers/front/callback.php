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
class ZaloPayCallbackModuleFrontController extends ModuleFrontController
{
  /**
   * @see FrontController::postProcess()
   */
  public function postProcess()
  {
    $data = json_decode(file_get_contents('php://input'), true);

    $key2 = Configuration::get('ZALOPAY_KEY2');

    $response = [];
    try {
      $hmacinput = $data['data'];
      $mac = hash_hmac("sha256", $hmacinput, $key2);

      $valid = $mac == $data['mac'];

      if ($valid) {
        // Payment success
        $mailVars = array();

        $embed_data = json_decode(json_decode($data['data'], true)['embed_data'], true);

        $this->module->validateOrder(
          $embed_data['cart_id'],
          Configuration::get('PS_OS_PAYMENT'),
          $embed_data['total'],
          $this->module->displayName,
          NULL,
          $mailVars,
          (int)$embed_data['currency_id'],
          false,
          $embed_data['secure_key']
        );
        $response["return_code"] = 1;
        $response["return_message"] = "MAC hợp lệ, đơn hàng đã được tạo thành công!";
      } else {
        $response["return_code"] = -1;
        $response["return_message"] = "MAC không hợp lệ!.";
      }
    } catch (\Throwable $th) {
      $response["return_code"] = 0; // ZaloPay server sẽ callback lại (tối đa 3 lần)
      $response["return_message"] = sprintf("Lỗi không xác định - %s", $th->getMessage());
    } finally {
      Logger::addLog(sprintf("[ZaloPay][Callback] Xử lý callback %s! Chi tiết: %s.", $response['return_code'] == 1 ? 'thành công' : 'thất bại', $response['return_message']));
      header('Content-Type: application/json');
      die(Tools::jsonEncode($response));
    }
  }
}
