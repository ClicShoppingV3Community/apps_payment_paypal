<?php
/**
 *
 *  @copyright 2008 - https://www.clicshopping.org
 *  @Brand : ClicShopping(Tm) at Inpi all right Reserved
 *  @Licence GPL 2 & MIT
 *  @licence MIT - Portion of osCommerce 2.4
 *  @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\EC\Params;

use ClicShopping\OM\HTML;

class instant_update extends \ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
    public $default = '1';
    public $sort_order = 400;

    protected function init()
    {
        $this->title = $this->app->getDef('cfg_ec_instant_update_title');
        $this->description = $this->app->getDef('cfg_ec_instant_update_desc');
    }

    public function getInputField()
    {
        $value = $this->getInputValue();

      $input =  HTML::radioField($this->key, '1', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_ec_instant_update_enabled') . '<br /> ';
      $input .=  HTML::radioField($this->key, '0', $value, 'id="' . $this->key . '0" autocomplete="off"') . $this->app->getDef('cfg_ec_instant_update_disabled') . '<br />';

        return $input;
    }
}
