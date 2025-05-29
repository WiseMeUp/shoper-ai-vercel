<?php

use DreamCommerce\ShopAppstoreLib\Client;
use DreamCommerce\ShopAppstoreLib\Handler;

class App
{
    protected $config;
    protected $client = null;
    protected $db = null;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function db()
    {
        if ($this->db === null) {
            $this->db = new PDO('sqlite:' . __DIR__ . '/../var/db/app.sqlite');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return $this->db;
    }

    public function install($shopUrl, $shop)
    {
        try {
            $db = $this->db();
            
            // Проверяем, не установлен ли уже магазин
            $stmt = $db->prepare('SELECT id FROM shops WHERE shop = ?');
            $stmt->execute([$shop]);
            if ($stmt->fetch()) {
                // Удаляем старые данные
                $db->prepare('DELETE FROM shops WHERE shop = ?')->execute([$shop]);
                $db->prepare('DELETE FROM access_tokens WHERE shop_id IN (SELECT id FROM shops WHERE shop = ?)')->execute([$shop]);
            }

            // Добавляем новый магазин
            $stmt = $db->prepare('INSERT INTO shops (shop, shop_url, version, installed) VALUES (?, ?, 1, 1)');
            $stmt->execute([$shop, $shopUrl]);
            
            // Создаем handler для установки
            $handler = new Handler(
                $this->config['appId'],
                $this->config['appSecret']
            );

            // Устанавливаем приложение
            return $handler->install($shopUrl);

        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    public function getShopData($shop)
    {
        $stmt = $this->db()->prepare('SELECT * FROM shops WHERE shop = ?');
        $stmt->execute([$shop]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
