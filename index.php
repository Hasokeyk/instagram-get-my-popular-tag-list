<?php
    
    use instagram_get_my_popular_tag_list\instagram_get_my_popular_tag_list;
    
    require "vendor/autoload.php";
    require "src/instagram_get_my_popular_tag_list.php";
    
    $instagram = new instagram_get_my_popular_tag_list('','');
    $tags = $instagram->get_my_popular_tag_list();
    print_r($tags);