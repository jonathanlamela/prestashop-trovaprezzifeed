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

        $filePath = _PS_ROOT_DIR_ . "/datafeed/trovaprezzi.txt";
        $file = fopen($filePath, "w");

        $db = Db::getInstance();

        $str_query = "
            SELECT
                rc.ramo AS Categoria,
                pm.name AS Marca,
                p.id_category_default,
                ps.id_supplier,
                p.reference AS `Codice OEM`,
                p.id_product AS `Codice commerciante`,
                pl.name AS `Nome prodotto`,
                COALESCE(pl.description, pl.description_short) AS `Descrizione`,
                CONCAT('https://','" . Tools::getShopDomain() . "','/',  p.id_product, '-', pl.link_rewrite, '.html?utm_source=trovaprezzi') AS `URL_Prodotto`,
                wi.image_url AS `URL_Immagine`,
                TRUNCATE(ROUND(p.price * 1.22, 2), 2) AS `Prezzo Vendita`,
                0 AS `Spese di Spedizione`,
                p.ean13 AS `EAN`,
                'EUR' AS `Valuta`,
                st.quantity AS `Stock`,
                '<endrecord>' AS `Fine`
            FROM `" . _DB_PREFIX_ . "product` `p`
            INNER JOIN `" . _DB_PREFIX_ . "product_lang` `pl` ON `p`.`id_product` = `pl`.`id_product` AND `pl`.`id_lang` = 1
            INNER JOIN `" . _DB_PREFIX_ . "webfeed_ramo_categoria` `rc` ON `p`.`id_category_default` = `rc`.`id_ramo_categoria`
            INNER JOIN `" . _DB_PREFIX_ . "stock_available` `st` ON `st`.`id_product` = `p`.`id_product` AND `st`.`quantity` > 0
            LEFT JOIN `" . _DB_PREFIX_ . "manufacturer` `pm` ON `pm`.`id_manufacturer` = `p`.`id_manufacturer`
            INNER JOIN `" . _DB_PREFIX_ . "webfeed_product` `wp` ON `wp`.`id_product` = `p`.`id_product`
            INNER JOIN `" . _DB_PREFIX_ . "webfeed_images` `wi` ON `wp`.`internal_code` = `wi`.`internal_code` AND `wi`.`image_url` IS NOT NULL
            LEFT JOIN `" . _DB_PREFIX_ . "product_supplier` `ps` ON `ps`.`id_product` = `p`.`id_product`
            WHERE `rc`.`ramo` IS NOT NULL
            AND `p`.`id_category_default` >= 2
        ";

        $result = $db->executeS($str_query);

        $skipped_by_supplier = 0;
        $skipped_by_category = 0;

        $suppliers_config = Configuration::get('TROVAPREZZI_FEED_SUPPLIERS');
        $suppliers = [];

        if ($suppliers_config) {
            $suppliers = explode(",", $suppliers_config);
        }

        $categories_config = Configuration::get('TROVAPREZZI_FEED_CATEGORIES');
        $categories = [];

        if ($categories_config) {
            $categories = explode(",", $categories_config);
        }


        if ($result) {
            $headerPrinted = false;

            foreach ($result as $row) {
                if (!$headerPrinted) {
                    fputcsv($file, array_keys($row), "|");
                    $headerPrinted = true;
                }

                if (!empty($suppliers) && !in_array($row["id_supplier"], $suppliers)) {
                    $skipped_by_supplier++;
                    continue; // Skip products not in suppliers
                }

                if (!empty($categories) && in_array($row["id_category_default"], $categories)) {
                    $skipped_by_category++;
                    continue; // Skip products from excluded suppliers
                }

                fputcsv($file, $row, "|");
            }
        }

        fclose($file);

        // Controlla se il file è vuoto
        if (filesize($filePath) === 0) {
            http_response_code(500);
            exit('Errore: il file trovaprezzi.txt è vuoto.');
        }

        $this->ajaxRender(json_encode([
            "url" => Tools::getHttpHost(true) . __PS_BASE_URI__ . "/datafeed/trovaprezzi.txt?v=" . time(),
            "filesize" => filesize($filePath),
            "skipped_by_supplier" => $skipped_by_supplier,
            "skipped_by_category" => $skipped_by_category,
        ]));
    }
}
