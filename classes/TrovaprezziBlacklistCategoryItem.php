<?php


class TrovaprezziBlacklistCategoryItem extends ObjectModel
{
    public $category_id = null;

    public static $definition = array(
        'table' => 'trovaprezzifeed_blacklist_categories',
        'primary' => 'id_trovaprezzi_blacklist_categories',
        'fields' => array(
            'category_id' => array('type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true),
        ),
    );
}
