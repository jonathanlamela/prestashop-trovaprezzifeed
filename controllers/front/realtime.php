<?php

use TrovaprezziFeed\Constants;

class TrovaprezziFeedRealtimeModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ajax = true;

    public $display_header = false;
    public $display_footer = false;

    public function getRamoCategoria($id_category)
    {
        $db = Db::getInstance();

        $query = "SELECT ramo FROM " . _DB_PREFIX_ . "webfeed_ramo_categoria WHERE id_ramo_categoria = " . (int)$id_category;
        $result = $db->getValue($query);

        return $result;
    }

    public function getProductImageFromCDN($id_product)
    {
        $db = Db::getInstance();

        $internal_code = $db->getValue("SELECT internal_code FROM " . _DB_PREFIX_ . "webfeed_product WHERE id_product = " . (int)$id_product);

        if (!$internal_code) {
            return null;
        }

        $image_url = $db->getValue("SELECT image_url FROM " . _DB_PREFIX_ . "webfeed_images WHERE internal_code = '" . pSQL($internal_code) . "'");

        if (!$image_url) {
            return null;
        }

        return $image_url;
    }

    public function initContent(): void
    {
        parent::initContent();

        // Disabilita il template
        $this->setTemplate("module:trovaprezzifeed/views/templates/front/index.html.twig");

        $this->writeFeed();
    }

    public function writeFeed()
    {

        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '1G');

        $filePath = _PS_ROOT_DIR_ . "/datafeed/trovaprezzi.txt";
        $file = fopen($filePath, "w");

        $db = Db::getInstance();

        $str_query = "
            SELECT
                pm.name AS `brand`,
                p.id_category_default,
                p.id_supplier,
                p.reference,
                p.id_product,
                pl.name,
                pl.description_short,
                pl.link_rewrite,
                wi.image_url,
                p.price,
                p.ean13,
                st.quantity,
                wp.internal_code
            FROM `" . _DB_PREFIX_ . "product` `p`
            INNER JOIN `" . _DB_PREFIX_ . "product_lang` `pl` ON `p`.`id_product` = `pl`.`id_product` AND `pl`.`id_lang` = 1
            INNER JOIN `" . _DB_PREFIX_ . "stock_available` `st` ON `st`.`id_product` = `p`.`id_product` AND `st`.`quantity` > 0
            LEFT JOIN `" . _DB_PREFIX_ . "manufacturer` `pm` ON `pm`.`id_manufacturer` = `p`.`id_manufacturer`
            INNER JOIN `" . _DB_PREFIX_ . "webfeed_product` `wp` ON `wp`.`id_product` = `p`.`id_product`
            INNER JOIN `" . _DB_PREFIX_ . "webfeed_images` `wi` ON `wp`.`internal_code` = `wi`.`internal_code` AND `wi`.`image_url` IS NOT NULL
            WHERE `p`.`id_category_default` >= 2
        ";



        $result = $db->executeS($str_query);


        $suppliers = [];
        $suppliers_query = $db->executeS("SELECT id_supplier FROM " . _DB_PREFIX_ . Constants::APP_PREFIX . "supplier_blacklist");

        foreach ($suppliers_query as $supplier) {
            $suppliers[$supplier['id_supplier']] = $supplier['id_supplier'];
        }

        $categories = [];
        $categories_query = $db->executeS("SELECT id_category FROM " . _DB_PREFIX_ . Constants::APP_PREFIX . "category_blacklist");

        foreach ($categories_query as $category) {
            $categories[$category['id_category']] = $category['id_category'];
        }

        $internal_codes = [];
        $internal_codes_query = $db->executeS("SELECT internal_code FROM " . _DB_PREFIX_ . Constants::APP_PREFIX . "product_blacklist");

        foreach ($internal_codes_query as $item) {
            $internal_codes[] = $item["internal_code"];
        }

        $rami_query = $db->executeS("SELECT id_ramo_categoria, ramo FROM " . _DB_PREFIX_ . "webfeed_ramo_categoria");
        $rami = [];

        foreach ($rami_query as $ramo) {
            $rami[$ramo["id_ramo_categoria"]] = $ramo["ramo"];
        }

        //Ottieni i prezzi specifici attivi
        $specific_prices_query = $db->executeS("SELECT * FROM " . _DB_PREFIX_ . "specific_price WHERE `to` > NOW()");
        $specific_prices = [];

        foreach ($specific_prices_query as $specific_price) {
            $specific_prices[$specific_price["id_product"]] = [
                "reduction" => $specific_price["reduction"],
                "reduction_type" => $specific_price["reduction_type"],
                "price" => $specific_price["price"],
            ];
        }


        $header = [
            "Categoria",
            "Marca",
            "Codice OEM",
            "Codice commerciante",
            "Nome prodotto",
            "Descrizione",
            "URL_Prodotto",
            "URL_Immagine",
            "Prezzo Vendita",
            "Spese di Spedizione",
            "EAN",
            "Valuta",
            "Stock",
            "Fine",
        ];


        if ($result) {

            fputcsv($file, $header, "|");

            foreach ($result as $row) {

                if (in_array($row["internal_code"], $internal_codes)) {
                    continue;
                }

                if (in_array($row["id_supplier"], $suppliers)) {
                    continue;
                }

                if (in_array($row["id_category_default"], $categories)) {
                    continue;
                }

                if (isset($rami[$row["id_category_default"]])) {
                    $row["category_tree"] = $rami[$row["id_category_default"]];
                } else {
                    continue; // Skip products with no category
                }

                if (isset($specific_prices[$row["id_product"]])) {
                    $specific_price = $specific_prices[$row["id_product"]];
                    if ($specific_price["reduction_type"] == "amount") {
                        $row["price"] = $specific_price["price"];
                    }
                }

                $link = "https://" . Tools::getShopDomain() . "/" . $row["id_product"] . "-" . $row["link_rewrite"] . ".html?utm_source=trovaprezzi";

                fputcsv($file, [
                    $row["category_tree"],
                    $row["brand"],
                    $row["reference"],
                    $row["id_product"],
                    $row["name"],
                    $row["description_short"],
                    $link,
                    $row["image_url"],
                    round($row["price"] * 1.22, 2),
                    0, // Spese di spedizione
                    $row["ean13"],
                    "EUR", // Valuta
                    $row["quantity"],
                    "<endrecord>"
                ], "|");
            }
        }

        fclose($file);

        // Controlla se il file è vuoto
        if (filesize($filePath) === 0) {
            http_response_code(500);
            exit('Errore: il file trovaprezzi.txt è vuoto.');
        }

        ini_set('max_execution_time', '60');
        ini_set('memory_limit', '256M');


        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="trovaprezzi.txt"');
        readfile($filePath);
        exit;
    }
}
