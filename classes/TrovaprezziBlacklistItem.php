<?php


class TrovaprezziBlacklistItem extends ObjectModel
{
    public $internal_code = null;

    public static $definition = array(
        'table' => 'trovaprezzifeed_blacklist',
        'primary' => 'id_trovaprezzi_blacklist',
        'fields' => array(
            'internal_code' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 100),
        ),
    );
}
