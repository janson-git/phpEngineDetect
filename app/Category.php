<?php
/**
 * @author Ivan Lisitskiy ivan.li@livetex.ru
 * 3/31/14 3:31 PM
 */

 class Category
 {
     protected $categories = [];
     
     public function __construct(JsonLoader $loader, $jsonFilePath)
     {
         $json = json_decode($loader->load($jsonFilePath), true);
         $this->categories = $json['categories'];
     }


     public function getCategoryById($id)
     {
         return array_key_exists($id, $this->categories) ? $this->categories[$id] : null;
     }
 }