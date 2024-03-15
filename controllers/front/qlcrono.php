<?php

/**
 * 2014-2016 MyQuickList
 *
 * NOTICE OF LICENSE
 *
 * E' vietata la riproduzione parziale e non del modulo ,
 * la vendita e la distribuzione non autorizzata dalla MyQuickList.
 *
 *  @author    Jonathan La Mela <jonathan.la.mela@gmail.com>
 *  @copyright 2007-2016 MyQuickList
 *  @license   http://www.creativecommons.it/ Creative Commons
 */


class TrovaprezziFeedQlcronoModuleFrontController extends ModuleFrontController
{

    public $products = array();

    public function init()
    {
        $this->page_name = 'qlcrono'; // page_name and body id

        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::init();
    }

    public function initContent()
    {
        parent::initContent();


        if (!file_exists(_PS_ROOT_DIR_ . "/datafeed/")) {
            mkdir(_PS_ROOT_DIR_ . "/datafeed/");
        }

        $this->setTemplate("module:trovaprezzifeed/views/templates/front/qlcrono.tpl");


        $this->writeFeed();
    }

    public function writeFeed()
    {

        $file = fopen(_PS_ROOT_DIR_ . "/datafeed/trovaprezzi.txt", "w");

        $db = Db::getInstance();


        $str_query = "
        select
        `rc`.`ramo` AS `Categoria`,
        `pm`.`name` AS `Marca`,
        `p`.`reference` AS `Codice OEM`,
        `p`.`id_product` AS `Codice commerciante`,
        `pl`.`name` AS `Nome prodotto`,
        ifnull(`pl`.`description`,`pl`.`description_short`) AS `Descrizione`,
        concat('" . Tools::getHttpHost(true) . __PS_BASE_URI__  . "',`p`.`id_product`,'-',`pl`.`link_rewrite`,'.html?utm_source=trovaprezzi') AS `URL_Prodotto`,
        concat('" . Tools::getHttpHost(true) . __PS_BASE_URI__  . "',`pi`.`id_image`,'-large_default/',`pl`.`link_rewrite`,'.jpg') AS `URL_Immagine`,
        truncate(round((`p`.`price`)*1.22,2),2) AS `Prezzo Vendita`,
        0 AS `Spese di Spedizione`,
        `p`.`ean13` as 'EAN',
        'EUR' as `Valuta`,
        `st`.`quantity` as `Stock`,
        '<endrecord>' as `Fine`
from `" . _DB_PREFIX_ . "product` `p`
left join `" . _DB_PREFIX_ . "product_lang` `pl` on `p`.`id_product` = `pl`.`id_product`
left join `" . _DB_PREFIX_ . "webfeed_ramo_categoria` `rc` on `p`.`id_category_default` = `rc`.`id_ramo_categoria`
left join `" . _DB_PREFIX_ . "image` `pi` on `pi`.`id_product` = `p`.`id_product` AND `pi`.`cover` = 1
left join `" . _DB_PREFIX_ . "manufacturer` `pm` on `pm`.`id_manufacturer` = `p`.`id_manufacturer`
left join `" . _DB_PREFIX_ . "stock_available` `st` on `st`.`id_product` = `p`.`id_product`
left join `" . _DB_PREFIX_ . "webfeed_product` `wp` on `wp`.`prestashop_id`=`p`.`id_product`
where `rc`.`ramo` is not null
and `p`.`id_category_default` >= 2
and `pl`.`id_lang` = 1
and `pi`.`id_image` is not null
and `st`.`quantity` > 0
        ";


        ini_set('memory_limit', '512M');

        $products = $db->executeS($str_query);

        fputcsv($file, array_keys($products[0]), "|");


        foreach ($products as $p) {
            fputcsv($file, $p, "|");
        }

        fclose($file);

        ini_set('memory_limit', '256M');



        echo "<a href='/datafeed/trovaprezzi.txt' download>Scarica feed</a>";
    }
}
