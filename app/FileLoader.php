<?php
/**
 * @author Ivan Lisitskiy ivan.li@livetex.ru
 * 4/1/14 4:46 PM
 */


class FileLoader
{
    public function __construct() {}
    
    public function load($filePath)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception('File not exists or not readable');
        }
        return file_get_contents($filePath);
    }
}