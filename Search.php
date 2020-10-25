<?php
/**
 * Search Plugin for MantisBT
 * Copyright (C) 2020  Starikov Anton - starikov_aa@mail.ru
 * https://github.com/starikov-aa/mantisbt-search
 */

class SearchPlugin extends MantisPlugin
{
    function register()
    {
        $this->name = plugin_lang_get('title');
        $this->description = plugin_lang_get('description');
        $this->version = '0.0.1';
        $this->author = 'Starikov Anton';
        $this->contact = 'starikov_aa@mail.ru';
        $this->url = 'https://github.com/starikov-aa/mantisbt-search';
    }

    function install()
    {
        // set vars
        plugin_config_set('num_sym_before', 200);
        plugin_config_set('num_sym_after', 200);

        return true;
    }

    function hooks()
    {
        return [
            'EVENT_MENU_MAIN' => 'print_menu_search',
            'EVENT_LAYOUT_RESOURCES' => 'resources',
            'EVENT_DISPLAY_TEXT' => 'test'
        ];
    }

    function test($a, $b, $c)
    {
        $r = '<h1>DDDDDDDDDD</h1>';

        echo $b;
        return $b;
    }

    function resources()
    {
        $resources = '<link rel="stylesheet" type="text/css" href="' . plugin_file('css/style.css') . '" />';
        echo $resources;
    }

    function print_menu_search()
    {
        return [
            [
                'title' => plugin_lang_get('search_link'),
                'access_level' => DEVELOPER,
                'url' => plugin_page('search'),
                'icon' => 'fa-search'
            ]
        ];
    }


}

?>